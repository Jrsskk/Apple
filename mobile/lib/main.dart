import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:go_router/go_router.dart';
import 'package:path/path.dart';
import 'package:sqflite/sqflite.dart';
import 'package:sqflite_common_ffi/sqflite_ffi.dart' as ffi;
import 'package:uuid/uuid.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

const _configuredBaseUrl = String.fromEnvironment('API_BASE_URL');
final _baseUrl = _configuredBaseUrl.isNotEmpty
    ? _configuredBaseUrl
    : kIsWeb
        ? 'http://127.0.0.1:8000/api/v1'
        : 'http://192.168.1.10:8000/api/v1';

void _ensureSqliteFactory() {
  if (kIsWeb) return;
  try {
    databaseFactory;
  } catch (_) {
    ffi.sqfliteFfiInit();
    databaseFactory = ffi.databaseFactoryFfi;
  }
}

void main() {
  _ensureSqliteFactory();
  debugPrint('[EduSync Debug] API Base URL: $_baseUrl');
  runApp(const ProviderScope(child: EduSyncApp()));
}

final secureStorageProvider = Provider((_) => const FlutterSecureStorage());
final databaseProvider = Provider<LocalStore>((_) => LocalStore());
final apiProvider = Provider<ApiClient>(
  (ref) => ApiClient(ref.read(secureStorageProvider)),
);
final repositoryProvider = Provider<StudentRepository>(
  (ref) => StudentRepository(ref.read(apiProvider), ref.read(databaseProvider)),
);
final onlineProvider = StreamProvider<bool>(
  (_) => Connectivity()
      .onConnectivityChanged
      .map((r) => !r.contains(ConnectivityResult.none))
      .distinct(),
);
final authProvider = AsyncNotifierProvider<AuthController, User?>(
  AuthController.new,
);
final dashboardProvider = FutureProvider<Dashboard>(
  (ref) => ref.read(repositoryProvider).dashboard(),
);
final classesProvider = FutureProvider<List<dynamic>>(
  (ref) => ref.read(repositoryProvider).list('classes'),
);
final quizzesProvider = FutureProvider<List<dynamic>>(
  (ref) => ref.read(repositoryProvider).list('quizzes'),
);
final assignmentsProvider = FutureProvider<List<dynamic>>(
  (ref) => ref.read(repositoryProvider).list('assignments'),
);
final materialsProvider = FutureProvider<List<dynamic>>(
  (ref) => ref.read(repositoryProvider).list('materials'),
);
final gradesProvider = FutureProvider<List<dynamic>>(
  (ref) => ref.read(repositoryProvider).list('grades'),
);
final announcementsProvider = FutureProvider<List<dynamic>>(
  (ref) => ref.read(repositoryProvider).list('announcements'),
);
final notificationsProvider = FutureProvider<List<dynamic>>(
  (ref) => ref.read(repositoryProvider).list('notifications'),
);

class ApiClient {
  ApiClient(this._storage) {
    dio = Dio(
      BaseOptions(
        baseUrl: _baseUrl,
        headers: {'Accept': 'application/json'},
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 20),
      ),
    );
    dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _storage.read(key: 'token');
          if (token != null) options.headers['Authorization'] = 'Bearer $token';
          handler.next(options);
        },
      ),
    );
  }
  final FlutterSecureStorage _storage;
  late final Dio dio;
  Future<dynamic> get(String path) async {
    final response = await dio.get(path);
    debugPrint('EduSync API GET $path -> ${response.statusCode}');
    return _data(response, path: path, method: 'GET');
  }

  Future<dynamic> post(String path, dynamic data) async {
    final response = await dio.post(path, data: data);
    debugPrint('EduSync API POST $path -> ${response.statusCode}');
    return _data(response, path: path, method: 'POST');
  }

  Future<Map<String, dynamic>> postRaw(
    String path,
    Map<String, dynamic> data,
  ) async {
    final response = await dio.post(path, data: data);
    debugPrint('EduSync API POST RAW $path -> ${response.statusCode}');
    return (response.data as Map).cast<String, dynamic>();
  }

  Future<dynamic> getWithQuery(String path, Map<String, dynamic> query) async {
    final response = await dio.get(path, queryParameters: query);
    debugPrint(
        'EduSync API GET $path?${query.toString()} -> ${response.statusCode}');
    return _data(response, path: path, method: 'GET');
  }

  dynamic _data(Response response,
      {required String path, required String method}) {
    final payload = response.data is Map
        ? response.data as Map<String, dynamic>
        : <String, dynamic>{};
    debugPrint(
        'EduSync API $method $path response status=${response.statusCode} body=${jsonEncode(payload)}');
    return payload['data'];
  }
}

class RealtimeClient {
  RealtimeClient({
    required this.api,
    required this.studentId,
    required this.onQuizPublished,
  });

  final ApiClient api;
  final int studentId;
  final VoidCallback onQuizPublished;
  WebSocketChannel? _channel;
  Timer? _reconnectTimer;
  bool _closed = false;

  String get _channelName => 'private-student.$studentId';

  Future<void> connect() async {
    _closed = false;
    _reconnectTimer?.cancel();
    try {
      final apiUri = Uri.parse(_baseUrl);
      const scheme = String.fromEnvironment(
        'REVERB_SCHEME',
        defaultValue: 'http',
      );
      const configuredHost = String.fromEnvironment('REVERB_HOST');
      final host = configuredHost.isNotEmpty ? configuredHost : apiUri.host;
      final port = int.tryParse(const String.fromEnvironment(
            'REVERB_PORT',
            defaultValue: '8080',
          )) ??
          8080;
      const key = String.fromEnvironment(
        'REVERB_APP_KEY',
        defaultValue: 'edusync-key',
      );
      final uri = Uri(
        scheme: scheme == 'https' ? 'wss' : 'ws',
        host: host,
        port: port,
        path: '/app/$key',
        queryParameters: const {
          'protocol': '7',
          'client': 'flutter',
          'version': '1.0',
          'flash': 'false',
        },
      );
      final channel = WebSocketChannel.connect(uri);
      _channel = channel;
      channel.stream.listen(
        _handleMessage,
        onError: (_) => _scheduleReconnect(),
        onDone: _scheduleReconnect,
        cancelOnError: true,
      );
    } catch (_) {
      _scheduleReconnect();
    }
  }

  void _handleMessage(dynamic raw) {
    final message = jsonDecode(raw as String) as Map<String, dynamic>;
    final event = message['event'] as String?;
    if (event == 'pusher:connection_established') {
      final data =
          jsonDecode(message['data'] as String) as Map<String, dynamic>;
      unawaited(_subscribe(data['socket_id'] as String));
    } else if (event == 'quiz.published') {
      onQuizPublished();
    }
  }

  Future<void> _subscribe(String socketId) async {
    try {
      final auth = await api.postRaw('broadcasting/auth', {
        'socket_id': socketId,
        'channel_name': _channelName,
      });
      _channel?.sink.add(jsonEncode({
        'event': 'pusher:subscribe',
        'data': {
          'auth': auth['auth'],
          'channel': _channelName,
        },
      }));
    } catch (_) {
      _channel?.sink.close();
    }
  }

  void _scheduleReconnect() {
    if (_closed || _reconnectTimer?.isActive == true) return;
    _reconnectTimer = Timer(const Duration(seconds: 5), connect);
  }

  void dispose() {
    _closed = true;
    _reconnectTimer?.cancel();
    _channel?.sink.close();
  }
}

class LocalStore {
  Database? _db;
  final Map<String, dynamic> _webCache = {};
  final List<Map<String, Object?>> _webQueue = [];
  Future<Database> get db async {
    _ensureSqliteFactory();
    _db ??= await openDatabase(
      join(await getDatabasesPath(), 'edusync_student.db'),
      version: 2,
      onCreate: (db, _) async {
        await db.execute(
          'CREATE TABLE cache (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL)',
        );
        await db.execute(
          'CREATE TABLE queue (uuid TEXT PRIMARY KEY, endpoint TEXT NOT NULL, payload TEXT NOT NULL, created_at TEXT NOT NULL, attempts INTEGER NOT NULL DEFAULT 0)',
        );
        await db.execute(
          'CREATE TABLE records (resource TEXT NOT NULL, record_id TEXT NOT NULL, value TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (resource, record_id))',
        );
        await db.execute(
          'CREATE TABLE sync_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)',
        );
      },
      onUpgrade: (db, oldVersion, _) async {
        if (oldVersion < 2) {
          await db.execute(
            'CREATE TABLE records (resource TEXT NOT NULL, record_id TEXT NOT NULL, value TEXT NOT NULL, updated_at TEXT NOT NULL, PRIMARY KEY (resource, record_id))',
          );
          await db.execute(
            'CREATE TABLE sync_meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)',
          );
        }
      },
    );
    return _db!;
  }

  Future<List<dynamic>> records(String resource) async {
    if (kIsWeb) return (_webCache[resource] as List?)?.cast<dynamic>() ?? [];
    final rows = await (await db)
        .query('records', where: 'resource = ?', whereArgs: [resource]);
    return rows.map((row) => jsonDecode(row['value']! as String)).toList();
  }

  Future<int> saveClasses(List<dynamic> classes) async {
    var count = 0;
    if (kIsWeb) {
      final existing = [
        ...((_webCache['classes'] as List?) ?? const []),
      ].map((item) => (item as Map).cast<String, dynamic>());
      final merged = <String, Map<String, dynamic>>{
        for (final item in existing) _recordKey('classes', item): item,
      };
      for (final raw in classes) {
        final value = (raw as Map).cast<String, dynamic>();
        final id = _recordKey('classes', value);
        merged[id] = value;
        count++;
      }
      _webCache['classes'] = merged.values.toList();
    } else {
      final database = await db;
      await database.transaction((txn) async {
        for (final raw in classes) {
          final value = (raw as Map).cast<String, dynamic>();
          final id = _recordKey('classes', value);
          final updatedAt = value['updated_at']?.toString() ??
              DateTime.now().toIso8601String();
          await txn.insert(
            'records',
            {
              'resource': 'classes',
              'record_id': id,
              'value': jsonEncode(value),
              'updated_at': updatedAt,
            },
            conflictAlgorithm: ConflictAlgorithm.replace,
          );
          count++;
        }
      });
    }
    debugPrint(
        '[EduSync Debug] SQLite Insert/Update Result: saved $count classes');
    return count;
  }

  Future<void> applySync(Map<String, dynamic> data) async {
    final resources = [
      'classes',
      'quizzes',
      'assignments',
      'materials',
      'announcements',
      'grades',
    ];
    var inserted = 0;
    var deleted = 0;
    if (kIsWeb) {
      for (final resource in resources) {
        final existing = [
          ...((_webCache[resource] as List?) ?? const []),
        ].map((item) => (item as Map).cast<String, dynamic>());
        final merged = <String, Map<String, dynamic>>{
          for (final item in existing) _recordKey(resource, item): item,
        };
        for (final raw in (data[resource] as List? ?? [])) {
          final value = (raw as Map).cast<String, dynamic>();
          final id = _recordKey(resource, value);
          merged[id] = value;
          inserted++;
          debugPrint(
              'EduSync SQLite web upsert resource=$resource id=$id updated_at=${value['updated_at'] ?? data['synced_at']}');
        }
        for (final id in (data['deleted']?[resource] as List? ?? [])) {
          if (merged.remove('$id') != null) deleted++;
        }
        _webCache[resource] = merged.values.toList();
      }
      _webCache['_synced_at'] = data['synced_at'].toString();
    } else {
      final database = await db;
      await database.transaction((txn) async {
        for (final resource in resources) {
          for (final raw in (data[resource] as List? ?? [])) {
            final value = (raw as Map).cast<String, dynamic>();
            final id = _recordKey(resource, value);
            final updatedAt =
                value['updated_at']?.toString() ?? data['synced_at'].toString();
            await txn.insert(
                'records',
                {
                  'resource': resource,
                  'record_id': id,
                  'value': jsonEncode(value),
                  'updated_at': updatedAt,
                },
                conflictAlgorithm: ConflictAlgorithm.replace);
            inserted++;
            debugPrint(
                'EduSync SQLite upsert resource=$resource id=$id updated_at=$updatedAt');
          }
          for (final id in (data['deleted']?[resource] as List? ?? [])) {
            deleted += await txn.delete('records',
                where: 'resource = ? AND record_id = ?',
                whereArgs: [resource, '$id']);
            debugPrint('EduSync SQLite delete resource=$resource id=$id');
          }
        }
        await txn.insert('sync_meta',
            {'key': 'synced_at', 'value': data['synced_at'].toString()},
            conflictAlgorithm: ConflictAlgorithm.replace);
      });
    }
    debugPrint(
        'EduSync SQLite sync complete upserts=$inserted deletes=$deleted synced_at=${data['synced_at']}');
  }

  String _recordKey(String resource, Map<String, dynamic> value) =>
      resource == 'grades'
          ? '${value['type']}:${value['id']}'
          : '${value['id']}';

  Future<String?> lastSync() async {
    if (kIsWeb) return _webCache['_synced_at'] as String?;
    final rows = await (await db)
        .query('sync_meta', where: 'key = ?', whereArgs: ['synced_at']);
    return rows.isEmpty ? null : rows.first['value'] as String;
  }

  Future<void> cache(String key, dynamic value) async {
    if (kIsWeb) {
      _webCache[key] = value;
      return;
    }
    await (await db).insert(
      'cache',
      {
        'key': key,
        'value': jsonEncode(value),
        'updated_at': DateTime.now().toIso8601String(),
      },
      conflictAlgorithm: ConflictAlgorithm.replace,
    );
  }

  Future<dynamic> cached(String key) async {
    if (kIsWeb) return _webCache[key];
    final rows = await (await db).query(
      'cache',
      where: 'key = ?',
      whereArgs: [key],
    );
    return rows.isEmpty ? null : jsonDecode(rows.first['value']! as String);
  }

  Future<void> enqueue(
    String uuid,
    String endpoint,
    Map<String, dynamic> payload,
  ) async {
    if (kIsWeb) {
      _webQueue.add({
        'uuid': uuid,
        'endpoint': endpoint,
        'payload': jsonEncode(payload),
        'created_at': DateTime.now().toIso8601String(),
        'attempts': 0,
      });
      return;
    }
    await (await db).insert('queue', {
      'uuid': uuid,
      'endpoint': endpoint,
      'payload': jsonEncode(payload),
      'created_at': DateTime.now().toIso8601String(),
    });
  }

  Future<List<Map<String, Object?>>> pending() async {
    if (kIsWeb) return List<Map<String, Object?>>.of(_webQueue);
    return (await db).query('queue', orderBy: 'created_at');
  }

  Future<void> remove(String uuid) async => kIsWeb
      ? _webQueue.removeWhere((item) => item['uuid'] == uuid)
      : (await db).delete('queue', where: 'uuid = ?', whereArgs: [uuid]);

  Future<void> failed(String uuid) async {
    if (kIsWeb) {
      final item = _webQueue.firstWhere((item) => item['uuid'] == uuid);
      item['attempts'] = (item['attempts']! as int) + 1;
      return;
    }
    await (await db).rawUpdate(
      'UPDATE queue SET attempts = attempts + 1 WHERE uuid = ?',
      [uuid],
    );
  }
}

class StudentRepository {
  StudentRepository(this.api, this.store);
  final ApiClient api;
  final LocalStore store;
  Future<List<dynamic>> list(String resource) async {
    return store.records(resource);
  }

  Future<Dashboard> dashboard() async => Dashboard(
        await list('classes'),
        await list('quizzes'),
        await list('assignments'),
        await list('announcements'),
      );
  Future<dynamic> downloadQuiz(int id) async {
    final data = await api.get('/quizzes/$id/download');
    await store.cache('quiz:$id', data);
    return data;
  }

  Future<dynamic> downloadAssignment(int id) async {
    final data = await api.get('/assignments/$id/download');
    await store.cache('assignment:$id', data);
    return data;
  }

  Future<dynamic> quiz(int id) async {
    try {
      return await api.get('/quizzes/$id');
    } catch (_) {
      final saved = await store.cached('quiz:$id');
      if (saved == null) rethrow;
      return saved;
    }
  }

  Future<Map<String, dynamic>> join(String code) async {
    debugPrint('[EduSync Debug] API Base URL: $_baseUrl');
    final student = await api.get('/me');
    final studentId = (student as Map<String, dynamic>)['id'] as int? ?? 0;
    debugPrint('[EduSync Debug] Authenticated Student ID: $studentId');

    final res = await api.post('/classes/join', {'class_code': code});
    final enrollmentResponse =
        res is Map ? res.cast<String, dynamic>() : <String, dynamic>{};
    debugPrint(
        '[EduSync Debug] Enrollment Response: ${jsonEncode(enrollmentResponse)}');
    return enrollmentResponse;
  }

  Future<List<dynamic>> fetchMyClasses() async {
    debugPrint('[EduSync Debug] API Base URL: $_baseUrl');
    final res = await api.get('/classes');
    final classes = (res as List? ?? []).cast<dynamic>();
    final classIds =
        classes.map((item) => (item as Map<String, dynamic>)['id']).toList();
    debugPrint('[EduSync Debug] Enrolled Class IDs: ${classIds.join(",")}');
    debugPrint(
        '[EduSync Debug] My Classes API Response: ${jsonEncode(classes)}');
    debugPrint('[EduSync Debug] Number of Classes Received: ${classes.length}');
    final count = await store.saveClasses(classes);
    debugPrint(
        '[EduSync Debug] SQLite Insert/Update Result: saved $count classes');
    return classes;
  }

  Future<void> submitQuiz({
    required int quizId,
    required String uuid,
    required List<Map<String, dynamic>> answers,
  }) async {
    final payload = {
      'quiz_id': quizId,
      'sync_uuid': uuid,
      'submitted_at': DateTime.now().toIso8601String(),
      'answers': answers,
    };
    try {
      await api.post('/submissions/quiz', payload);
    } on DioException catch (error) {
      if (!_canQueue(error)) rethrow;
      await store.enqueue(uuid, '/submissions/quiz', payload);
    }
  }

  Future<void> submitAssignment({
    required int assignmentId,
    required String uuid,
    required String text,
  }) async {
    final payload = {
      'assignment_id': assignmentId,
      'sync_uuid': uuid,
      'text_response': text,
      'submitted_at': DateTime.now().toIso8601String(),
    };
    try {
      await api.post('/submissions/assignment', payload);
    } on DioException catch (error) {
      if (!_canQueue(error)) rethrow;
      await store.enqueue(uuid, '/submissions/assignment', payload);
    }
  }

  Future<void> sync() async {
    final student = await api.get('/me');
    final studentId = (student as Map<String, dynamic>)['id'] as int? ?? 0;
    debugPrint('[EduSync Debug] Authenticated Student ID: $studentId');
    debugPrint('[EduSync Debug] API Base URL: $_baseUrl');
    debugPrint(
        'EduSync sync started for student_id=$studentId at ${DateTime.now().toIso8601String()}');
    for (final item in await store.pending()) {
      try {
        await api.post(
          item['endpoint']! as String,
          jsonDecode(item['payload']! as String),
        );
        await store.remove(item['uuid']! as String);
        debugPrint(
            'EduSync queued sync item flushed uuid=${item['uuid']} endpoint=${item['endpoint']}');
      } on DioException catch (error) {
        if (!_canQueue(error)) {
          // A request that Laravel rejected (for example an expired quiz) must
          // not retry forever. Keep it locally for the student to resolve.
          debugPrint(
              'EduSync queued sync rejected by server uuid=${item['uuid']} status=${error.response?.statusCode}');
          continue;
        }
        await store.failed(item['uuid']! as String);
        debugPrint(
            'EduSync queued sync retry queued uuid=${item['uuid']} attempts=${item['attempts']}');
      }
    }
    try {
      final since = await store.lastSync();
      final payload = {
        if (since != null) 'since': since,
      };
      debugPrint(
          'EduSync sync request student_id=$studentId since=${since ?? 'none'} payload=${payload.toString()}');
      final data =
          await api.getWithQuery('/sync', payload) as Map<String, dynamic>;
      debugPrint(
          'EduSync sync response student_id=$studentId response=${jsonEncode(data)} synced_at=${data['synced_at']}');
      debugPrint(
          '[EduSync Debug] Sync record counts: classes=${(data['classes'] as List? ?? []).length}, quizzes=${(data['quizzes'] as List? ?? []).length}, assignments=${(data['assignments'] as List? ?? []).length}, materials=${(data['materials'] as List? ?? []).length}, announcements=${(data['announcements'] as List? ?? []).length}, grades=${(data['grades'] as List? ?? []).length}');
      await store.applySync(data);
      final currentClasses = await store.records('classes');
      final currentMaterials = await store.records('materials');
      debugPrint(
          '[EduSync Debug] Number of Classes Received: ${currentClasses.length}');
      debugPrint(
          '[EduSync Debug] Number of Materials Received: ${currentMaterials.length}');
      debugPrint(
          'EduSync sync completed student_id=$studentId at ${data['synced_at']}');
    } on DioException catch (error, stack) {
      debugPrint(
          'EduSync sync failed student_id=$studentId status=${error.response?.statusCode} error=$error\n$stack');
      if (!_canQueue(error)) rethrow;
    }
  }
}

Future<void> _syncAndRefresh(WidgetRef ref) async {
  final student = ref.read(authProvider).valueOrNull;
  if (student != null) {
    debugPrint('[EduSync Debug] Authenticated Student ID: ${student.id}');
  }
  debugPrint('[EduSync Debug] API Base URL: $_baseUrl');
  try {
    await ref.read(repositoryProvider).fetchMyClasses();
    await ref.read(repositoryProvider).sync();
  } catch (error, stack) {
    debugPrint('EduSync sync status=failed error=$error\n$stack');
    rethrow;
  } finally {
    ref.invalidate(dashboardProvider);
    ref.invalidate(classesProvider);
    ref.invalidate(quizzesProvider);
    ref.invalidate(assignmentsProvider);
    ref.invalidate(gradesProvider);
    ref.invalidate(announcementsProvider);
    ref.invalidate(materialsProvider);
    ref.invalidate(notificationsProvider);
    try {
      final classes = await ref.read(classesProvider.future);
      final materials = await ref.read(materialsProvider.future);
      final dashboard = await ref.read(dashboardProvider.future);
      debugPrint(
          '[EduSync Debug] Riverpod State: classes=${classes.length}, materials=${materials.length}, dashboard_classes=${dashboard.classes.length}');
    } catch (_) {}
  }
}

bool _canQueue(DioException error) {
  final status = error.response?.statusCode;
  return status == null ||
      status >= 500 ||
      error.type == DioExceptionType.connectionError ||
      error.type == DioExceptionType.connectionTimeout ||
      error.type == DioExceptionType.receiveTimeout ||
      error.type == DioExceptionType.sendTimeout;
}

class User {
  User(this.id, this.name, this.email);
  final int id;
  final String name, email;
  factory User.fromJson(Map<String, dynamic> j) =>
      User(j['id'] as int, j['name'] as String, j['email'] as String);
}

class Dashboard {
  Dashboard(this.classes, this.quizzes, this.assignments, this.announcements);
  final List<dynamic> classes, quizzes, assignments, announcements;
}

class AuthController extends AsyncNotifier<User?> {
  @override
  Future<User?> build() async {
    final token = await ref.read(secureStorageProvider).read(key: 'token');
    if (token == null) return null;
    try {
      final me = await ref.read(apiProvider).get('/me') as Map<String, dynamic>;
      final user = User.fromJson(me);
      debugPrint('[EduSync Debug] Authenticated Student ID: ${user.id}');
      debugPrint('[EduSync Debug] API Base URL: $_baseUrl');
      debugPrint(
          'EduSync authenticated student_id=${user.id} email=${user.email}');
      return user;
    } catch (error, stack) {
      debugPrint('EduSync auth bootstrap failed: $error\n$stack');
      return null;
    }
  }

  Future<void> login(String email, String password) async {
    state = const AsyncLoading();
    state = await AsyncValue.guard(() async {
      final result = await ref.read(apiProvider).post('/login', {
        'email': email,
        'password': password,
        'device_name': 'EduSync Flutter',
      });
      await ref
          .read(secureStorageProvider)
          .write(key: 'token', value: result['token'] as String);
      final user = User.fromJson(result['user'] as Map<String, dynamic>);
      debugPrint('[EduSync Debug] Authenticated Student ID: ${user.id}');
      debugPrint('[EduSync Debug] API Base URL: $_baseUrl');
      debugPrint(
          'EduSync login success student_id=${user.id} email=${user.email}');
      return user;
    });
  }

  Future<void> logout() async {
    try {
      await ref.read(apiProvider).post('/logout', {});
    } finally {
      await ref.read(secureStorageProvider).delete(key: 'token');
      state = const AsyncData(null);
    }
  }
}

class EduSyncApp extends ConsumerWidget {
  const EduSyncApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    ref.listen(onlineProvider, (_, next) {
      if (next.valueOrNull == true) unawaited(_syncAndRefresh(ref));
    });
    return MaterialApp.router(
      title: 'EduSync',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xff3156c9),
          brightness: Brightness.light,
        ),
        useMaterial3: true,
      ),
      routerConfig: router,
    );
  }
}

final router = GoRouter(
  initialLocation: '/',
  routes: [
    GoRoute(path: '/', builder: (_, __) => const Gate()),
    GoRoute(
      path: '/quiz/:id',
      builder: (_, s) => QuizScreen(id: int.parse(s.pathParameters['id']!)),
    ),
    GoRoute(
      path: '/assignment/:id',
      builder: (_, s) =>
          AssignmentScreen(id: int.parse(s.pathParameters['id']!)),
    ),
  ],
);

class Gate extends ConsumerWidget {
  const Gate({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final auth = ref.watch(authProvider);
    return auth.when(
      data: (u) => u == null ? const LoginScreen() : const HomeScreen(),
      loading: () =>
          const Scaffold(body: Center(child: CircularProgressIndicator())),
      error: (_, __) => const LoginScreen(),
    );
  }
}

class LoginScreen extends ConsumerStatefulWidget {
  const LoginScreen({super.key});
  @override
  ConsumerState<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends ConsumerState<LoginScreen> {
  final email = TextEditingController();
  final password = TextEditingController();
  @override
  Widget build(BuildContext context) {
    final auth = ref.watch(authProvider);
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(28),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Icon(Icons.school_rounded, size: 64),
                  const SizedBox(height: 18),
                  Text(
                    'Welcome to EduSync',
                    style: Theme.of(context).textTheme.headlineMedium,
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 28),
                  TextField(
                    controller: email,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 14),
                  TextField(
                    controller: password,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Password',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  if (auth.hasError)
                    Padding(
                      padding: const EdgeInsets.only(top: 12),
                      child: Text(
                        _loginMessage(auth.error),
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ),
                  const SizedBox(height: 20),
                  FilledButton(
                    onPressed: auth.isLoading
                        ? null
                        : () => ref
                            .read(authProvider.notifier)
                            .login(email.text.trim(), password.text),
                    child: Text(auth.isLoading ? 'Signing in...' : 'Sign in'),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

String _loginMessage(Object? error) {
  if (error is DioException) {
    final data = error.response?.data;
    if (data is Map && data['message'] != null) {
      return data['message'].toString();
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return 'Cannot reach the Laravel server. Check the API URL and that php artisan serve is running.';
    }
  }
  return 'Login failed. Check your details and server connection.';
}

class HomeScreen extends ConsumerStatefulWidget {
  const HomeScreen({super.key});
  @override
  ConsumerState<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends ConsumerState<HomeScreen>
    with WidgetsBindingObserver {
  int index = 0;

  Timer? _refreshTimer;
  RealtimeClient? _realtime;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _startRefreshTimer();
    final user = ref.read(authProvider).valueOrNull;
    if (user != null) {
      _realtime = RealtimeClient(
        api: ref.read(apiProvider),
        studentId: user.id,
        onQuizPublished: _refreshStudentData,
      );
      unawaited(_realtime!.connect());
    }
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) unawaited(_syncAndRefresh(ref));
    });
  }

  void _startRefreshTimer() {
    _refreshTimer?.cancel();
    _refreshTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _refreshStudentData();
    });
  }

  void _refreshStudentData() {
    if (!mounted) return;
    unawaited(_syncAndRefresh(ref));
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _startRefreshTimer();
      _refreshStudentData();
    } else {
      _refreshTimer?.cancel();
    }
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _realtime?.dispose();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pages = [
      const DashboardScreen(),
      const ClassesScreen(),
      const WorkScreen(),
      const MoreScreen(),
    ];
    return Scaffold(
      appBar: AppBar(
        title: const Text('EduSync'),
        actions: [
          const ConnectionChip(),
          IconButton(
            onPressed: () => unawaited(_syncAndRefresh(ref)),
            icon: const Icon(Icons.sync),
          ),
        ],
      ),
      body: pages[index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: index,
        onDestinationSelected: (v) => setState(() => index = v),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.groups_outlined),
            label: 'Classes',
          ),
          NavigationDestination(
            icon: Icon(Icons.assignment_outlined),
            label: 'Work',
          ),
          NavigationDestination(icon: Icon(Icons.more_horiz), label: 'More'),
        ],
      ),
    );
  }
}

class ConnectionChip extends ConsumerWidget {
  const ConnectionChip({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final online = ref.watch(onlineProvider).valueOrNull ?? false;
    return Padding(
      padding: const EdgeInsets.only(right: 12),
      child: Chip(
        avatar: Icon(online ? Icons.cloud_done : Icons.cloud_off, size: 16),
        label: Text(online ? 'Online' : 'Offline'),
      ),
    );
  }
}

class DashboardScreen extends ConsumerWidget {
  const DashboardScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => AsyncBody<Dashboard>(
        value: ref.watch(dashboardProvider),
        builder: (d) => RefreshIndicator(
          onRefresh: () async {
            debugPrint(
                'EduSync dashboard refresh requested via pull-to-refresh');
            await _syncAndRefresh(ref);
          },
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              Text(
                'Your learning, in sync.',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 16),
              Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  Stat('Classes', d.classes.length, Icons.groups),
                  Stat('Quizzes', d.quizzes.length, Icons.quiz),
                  Stat('Assignments', d.assignments.length, Icons.assignment),
                ],
              ),
              const SizedBox(height: 24),
              Text(
                'Latest announcements',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              ...d.announcements.take(5).map(
                    (a) => Card(
                      child: ListTile(
                        leading: const Icon(Icons.campaign),
                        title: Text(a['title']?.toString() ?? 'Announcement'),
                        subtitle: Text(
                          a['body']?.toString() ??
                              a['content']?.toString() ??
                              '',
                        ),
                      ),
                    ),
                  ),
            ],
          ),
        ),
      );
}

class Stat extends StatelessWidget {
  const Stat(this.label, this.value, this.icon, {super.key});
  final String label;
  final int value;
  final IconData icon;
  @override
  Widget build(BuildContext context) => SizedBox(
        width: 150,
        child: Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(icon),
                Text('$value',
                    style: Theme.of(context).textTheme.headlineMedium),
                Text(label),
              ],
            ),
          ),
        ),
      );
}

class ClassesScreen extends ConsumerWidget {
  const ClassesScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => AsyncBody<List<dynamic>>(
        value: ref.watch(classesProvider),
        builder: (items) => RefreshIndicator(
          onRefresh: () async => _syncAndRefresh(ref),
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              FilledButton.icon(
                onPressed: () => _join(context, ref),
                icon: const Icon(Icons.add),
                label: const Text('Join class with code'),
              ),
              const SizedBox(height: 14),
              ...items.map(
                (c) => Card(
                  child: ListTile(
                    leading: const CircleAvatar(child: Icon(Icons.class_)),
                    title: Text(c['name']?.toString() ?? 'Class'),
                    subtitle: Text(
                      '${c['subject']?['name'] ?? ''} • ${c['teacher']?['name'] ?? ''}',
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
}

Future<void> _join(BuildContext context, WidgetRef ref) async {
  final code = TextEditingController();
  final ok = await showDialog<bool>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: const Text('Join a class'),
      content: TextField(
        controller: code,
        textCapitalization: TextCapitalization.characters,
        decoration: const InputDecoration(labelText: 'Class code'),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () => Navigator.pop(ctx, true),
          child: const Text('Join'),
        ),
      ],
    ),
  );
  if (ok == true) {
    try {
      final repository = ref.read(repositoryProvider);
      await repository.join(code.text.trim());
      await repository.fetchMyClasses();
      try {
        await repository.sync();
      } catch (e) {
        debugPrint('Sync after join: $e');
      }
      ref.invalidate(dashboardProvider);
      ref.invalidate(classesProvider);
      ref.invalidate(quizzesProvider);
      ref.invalidate(assignmentsProvider);
      ref.invalidate(gradesProvider);
      ref.invalidate(announcementsProvider);
      ref.invalidate(materialsProvider);
      ref.invalidate(notificationsProvider);

      final updatedClasses = await ref.read(classesProvider.future);
      final updatedMaterials = await ref.read(materialsProvider.future);
      final updatedDashboard = await ref.read(dashboardProvider.future);
      debugPrint(
          '[EduSync Debug] Riverpod State: classes=${updatedClasses.length}, materials=${updatedMaterials.length}, dashboard_classes=${updatedDashboard.classes.length}');

      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Class joined successfully.')),
        );
      }
    } on DioException catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              e.response?.data['message']?.toString() ?? 'Could not join class',
            ),
          ),
        );
      }
    }
  }
}

class WorkScreen extends ConsumerWidget {
  const WorkScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final quizzes = ref.watch(quizzesProvider);
    final assignments = ref.watch(assignmentsProvider);
    final materials = ref.watch(materialsProvider);
    Widget quizItem(dynamic item) => Card(
          child: ListTile(
            leading: const Icon(Icons.quiz),
            title: Text(item['title']?.toString() ?? 'Quiz'),
            subtitle: Text(
                'Due ${item['deadline'] ?? item['ends_at'] ?? item['due_at'] ?? '-'}'),
            trailing: IconButton(
              icon: const Icon(Icons.download),
              onPressed: () async {
                await ref
                    .read(repositoryProvider)
                    .downloadQuiz(item['id'] as int);
              },
            ),
            onTap: () => context.push('/quiz/${item['id']}'),
          ),
        );
    Widget assignmentItem(dynamic item) => Card(
          child: ListTile(
            leading: const Icon(Icons.assignment),
            title: Text(item['title']?.toString() ?? 'Assignment'),
            subtitle: Text(
                'Due ${item['deadline'] ?? item['due_at'] ?? item['ends_at'] ?? '-'}'),
            trailing: IconButton(
              icon: const Icon(Icons.download),
              onPressed: () async {
                await ref
                    .read(repositoryProvider)
                    .downloadAssignment(item['id'] as int);
              },
            ),
            onTap: () => context.push('/assignment/${item['id']}'),
          ),
        );
    Widget materialItem(dynamic item) => Card(
          child: ListTile(
            leading: const Icon(Icons.attach_file),
            title: Text(item['title']?.toString() ?? 'Material'),
            subtitle: Text(
              '${item['file_type'] ?? 'File'}${item['description'] != null && item['description'].toString().isNotEmpty ? ' • ${item['description']}' : ''}',
            ),
            trailing: const Icon(Icons.download_rounded),
          ),
        );
    return RefreshIndicator(
      onRefresh: () async => _syncAndRefresh(ref),
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text('Quizzes', style: Theme.of(context).textTheme.titleLarge),
          AsyncBody<List<dynamic>>(
            value: quizzes,
            builder: (items) => Column(children: items.map(quizItem).toList()),
          ),
          const SizedBox(height: 20),
          Text('Assignments', style: Theme.of(context).textTheme.titleLarge),
          AsyncBody<List<dynamic>>(
            value: assignments,
            builder: (items) =>
                Column(children: items.map(assignmentItem).toList()),
          ),
          const SizedBox(height: 20),
          Text('Learning materials',
              style: Theme.of(context).textTheme.titleLarge),
          AsyncBody<List<dynamic>>(
            value: materials,
            builder: (items) =>
                Column(children: items.map(materialItem).toList()),
          ),
        ],
      ),
    );
  }
}

class QuizScreen extends ConsumerStatefulWidget {
  const QuizScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<QuizScreen> createState() => _QuizScreenState();
}

class _QuizScreenState extends ConsumerState<QuizScreen> {
  late final Future<dynamic> data;
  final answers = <int, dynamic>{};
  String get draftKey => 'quiz-draft:${widget.id}';
  @override
  void initState() {
    super.initState();
    data = _loadQuiz();
  }

  Future<dynamic> _loadQuiz() async {
    final repository = ref.read(repositoryProvider);
    final quiz = await repository.quiz(widget.id);
    final draft = await repository.store.cached(draftKey);
    if (draft is Map) {
      for (final entry in draft.entries) {
        final value = entry.value;
        answers[int.parse(entry.key.toString())] = value is List
            ? List<dynamic>.from(value)
            : value is Map
                ? Map<String, dynamic>.from(value)
                : value;
      }
    }
    return quiz;
  }

  Future<void> _saveDraft() => ref.read(repositoryProvider).store.cache(
        draftKey,
        answers.map((key, value) => MapEntry(key.toString(), value)),
      );

  void _setAnswer(int questionId, dynamic value) {
    setState(() => answers[questionId] = value);
    unawaited(_saveDraft());
  }

  Widget _questionInput(Map<String, dynamic> question) {
    final id = question['id'] as int;
    final type = question['type']?.toString() ?? 'multiple_choice';
    final options = question['options'] as List? ?? [];
    final current = answers[id];
    String optionTitle(Map option) => option['option_text']?.toString() ?? '';

    Widget optionLabel(Map option) {
      final image = option['image_path']?.toString();
      return Row(children: [
        if (image != null && image.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child:
                Image.network(image, width: 48, height: 48, fit: BoxFit.cover),
          ),
        Expanded(child: Text(optionTitle(option))),
      ]);
    }

    if (type == 'identification') {
      return TextFormField(
        initialValue: current?.toString() ?? '',
        onChanged: (value) => _setAnswer(id, value),
        decoration: const InputDecoration(
            labelText: 'Your answer', border: OutlineInputBorder()),
      );
    }

    if (type == 'matching') {
      final left = options.where((o) => o['is_correct'] != true).toList();
      final right = options.where((o) => o['is_correct'] == true).toList();
      final selected = current is Map
          ? Map<String, dynamic>.from(current)
          : <String, dynamic>{};
      return Column(
          children: left.map((item) {
        final itemId = item['id'].toString();
        return DropdownButtonFormField<String>(
          initialValue: selected[itemId]?.toString(),
          decoration: InputDecoration(labelText: optionTitle(item)),
          items: right
              .map((match) => DropdownMenuItem<String>(
                    value: match['match_key']?.toString(),
                    child: Text(optionTitle(match)),
                  ))
              .toList(),
          onChanged: (value) {
            if (value != null) _setAnswer(id, {...selected, itemId: value});
          },
        );
      }).toList());
    }

    if (type == 'sequencing' || type == 'drag_drop') {
      final ordered = current is List
          ? current
              .map((value) => options.firstWhere(
                  (o) => o['id'].toString() == value.toString(),
                  orElse: () => {'id': value, 'option_text': value}))
              .toList()
          : List<dynamic>.from(options);
      return ReorderableListView(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        onReorderItem: (oldIndex, newIndex) {
          final updated = List<dynamic>.from(ordered);
          final item = updated.removeAt(oldIndex);
          updated.insert(newIndex, item);
          _setAnswer(id, updated.map((o) => o['id']).toList());
        },
        children: [
          for (final option in ordered)
            ListTile(
              key: ValueKey(option['id']),
              leading: const Icon(Icons.drag_handle),
              title: optionLabel(option),
            ),
        ],
      );
    }

    if (type == 'multiple_selection') {
      final selected = (current is List ? current : const [])
          .map((v) => v.toString())
          .toSet();
      return Column(
          children: options.map((option) {
        final optionId = option['id'].toString();
        return CheckboxListTile(
          value: selected.contains(optionId),
          title: optionLabel(option),
          onChanged: (checked) {
            final updated = {...selected};
            checked == true ? updated.add(optionId) : updated.remove(optionId);
            _setAnswer(id, updated.toList());
          },
        );
      }).toList());
    }

    return RadioGroup<String>(
      groupValue: current?.toString(),
      onChanged: (value) => _setAnswer(id, value),
      child: Column(
        children: options
            .map((option) => RadioListTile<String>(
                  value: option['id'].toString(),
                  title: optionLabel(option),
                ))
            .toList(),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Quiz')),
        body: FutureBuilder<dynamic>(
          future: data,
          builder: (_, s) {
            if (!s.hasData) {
              return const Center(child: CircularProgressIndicator());
            }
            final d = s.data as Map;
            final questions = (d['questions'] as List? ?? []);
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  d['quiz']?['title']?.toString() ?? 'Quiz',
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                ...questions.map((q) => Card(
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(q['question_text']?.toString() ?? 'Question'),
                            if (q['image_path'] != null &&
                                q['image_path'].toString().isNotEmpty)
                              Padding(
                                padding:
                                    const EdgeInsets.symmetric(vertical: 12),
                                child: Image.network(q['image_path'].toString(),
                                    fit: BoxFit.contain),
                              ),
                            const SizedBox(height: 8),
                            _questionInput((q as Map).cast<String, dynamic>()),
                          ],
                        ),
                      ),
                    )),
                FilledButton(
                  onPressed: () async {
                    final uuid = const Uuid().v4();
                    await ref.read(repositoryProvider).submitQuiz(
                          quizId: widget.id,
                          uuid: uuid,
                          answers: answers.entries
                              .map(
                                (e) => {
                                  'question_id': e.key,
                                  'answer_text':
                                      e.value is String ? e.value : null,
                                  'selected_options':
                                      e.value is String ? [e.value] : e.value,
                                },
                              )
                              .toList(),
                        );
                    await ref
                        .read(repositoryProvider)
                        .store
                        .cache(draftKey, null);
                    if (context.mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text(
                            'Submission saved. It will sync automatically when online.',
                          ),
                        ),
                      );
                      context.pop();
                    }
                  },
                  child: const Text('Submit quiz'),
                ),
              ],
            );
          },
        ),
      );
}

class AssignmentScreen extends ConsumerStatefulWidget {
  const AssignmentScreen({super.key, required this.id});
  final int id;
  @override
  ConsumerState<AssignmentScreen> createState() => _AssignmentScreenState();
}

class _AssignmentScreenState extends ConsumerState<AssignmentScreen> {
  final response = TextEditingController();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Assignment')),
        body: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Write your assignment response. It is queued safely if you are offline.',
              ),
              const SizedBox(height: 16),
              Expanded(
                child: TextField(
                  controller: response,
                  maxLines: null,
                  expands: true,
                  textAlignVertical: TextAlignVertical.top,
                  decoration: const InputDecoration(
                    border: OutlineInputBorder(),
                    hintText: 'Your response',
                  ),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: () async {
                  await ref.read(repositoryProvider).submitAssignment(
                        assignmentId: widget.id,
                        uuid: const Uuid().v4(),
                        text: response.text,
                      );
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Assignment saved for submission.'),
                      ),
                    );
                    context.pop();
                  }
                },
                child: const Text('Submit assignment'),
              ),
            ],
          ),
        ),
      );
}

class MoreScreen extends ConsumerWidget {
  const MoreScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final user = ref.watch(authProvider).valueOrNull;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        ListTile(
          leading: const CircleAvatar(child: Icon(Icons.person)),
          title: Text(user?.name ?? 'Student'),
          subtitle: Text(user?.email ?? ''),
          trailing: const Icon(Icons.verified_user_outlined),
        ),
        ListTile(
          leading: const Icon(Icons.grade),
          title: const Text('Grades & feedback'),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const GradesScreen()),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.notifications_outlined),
          title: const Text('Notifications & announcements'),
          onTap: () => Navigator.push(
            context,
            MaterialPageRoute(builder: (_) => const AnnouncementsScreen()),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.logout),
          title: const Text('Log out'),
          onTap: () => ref.read(authProvider.notifier).logout(),
        ),
      ],
    );
  }
}

class GradesScreen extends ConsumerWidget {
  const GradesScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
        appBar: AppBar(title: const Text('Grades & feedback')),
        body: AsyncBody<List<dynamic>>(
          value: ref.watch(gradesProvider),
          builder: (grades) => ListView(
            children: grades
                .map(
                  (g) => ListTile(
                    leading: const Icon(Icons.grade),
                    title: Text(g['title']?.toString() ?? ''),
                    subtitle: Text(
                      '${g['class'] ?? ''}\n${g['feedback'] ?? 'No feedback yet'}',
                    ),
                    isThreeLine: true,
                    trailing:
                        Text('${g['score'] ?? '—'}/${g['max_score'] ?? '—'}'),
                  ),
                )
                .toList(),
          ),
        ),
      );
}

class AnnouncementsScreen extends ConsumerWidget {
  const AnnouncementsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
        appBar: AppBar(title: const Text('Notifications')),
        body: AsyncBody<List<dynamic>>(
          value: ref.watch(notificationsProvider),
          builder: (a) => ListView(
            children: a
                .map(
                  (x) => ListTile(
                    leading: const Icon(Icons.notifications),
                    title: Text(x['data']?['title']?.toString() ??
                        x['type']?.toString() ??
                        'Notification'),
                    subtitle: Text(
                      x['data']?['message']?.toString() ??
                          x['data']?['body']?.toString() ??
                          '',
                    ),
                  ),
                )
                .toList(),
          ),
        ),
      );
}

class AsyncBody<T> extends StatelessWidget {
  const AsyncBody({super.key, required this.value, required this.builder});
  final AsyncValue<T> value;
  final Widget Function(T) builder;
  @override
  Widget build(BuildContext context) => value.when(
        data: builder,
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (e, _) => Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Text(
              'Unable to load data. Saved offline content will appear when available.\n$e',
              textAlign: TextAlign.center,
            ),
          ),
        ),
      );
}
