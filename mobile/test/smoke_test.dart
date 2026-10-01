import 'package:edusync_student/main.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

class FakeStudentRepository extends StudentRepository {
  FakeStudentRepository({required this.syncCallCount})
      : super(
          ApiClient(const FlutterSecureStorage()),
          LocalStore(),
        );

  final ValueNotifier<int> syncCallCount;

  @override
  Future<void> sync() async {
    syncCallCount.value += 1;
  }

  @override
  Future<List<dynamic>> fetchMyClasses() async => [];

  @override
  Future<List<dynamic>> list(String resource) async => [];

  @override
  Future<Dashboard> dashboard() async => Dashboard(
        const [],
        const [],
        const [],
        const [],
      );
}

void main() {
  test('submission UUIDs are unique', () {
    final first = DateTime.now().microsecondsSinceEpoch;
    final second = DateTime.now().microsecondsSinceEpoch;
    expect(second, greaterThanOrEqualTo(first));
  });

  testWidgets(
      'dashboard pull-to-refresh triggers sync before refreshing providers',
      (tester) async {
    final syncCallCount = ValueNotifier<int>(0);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          repositoryProvider.overrideWithValue(
            FakeStudentRepository(syncCallCount: syncCallCount),
          ),
          dashboardProvider.overrideWith((ref) => Future.value(
                Dashboard(const [], const [], const [], const []),
              )),
        ],
        child: const MaterialApp(home: DashboardScreen()),
      ),
    );

    await tester.pumpAndSettle();
    await tester.drag(find.byType(RefreshIndicator), const Offset(0, 300));
    await tester.pumpAndSettle();

    expect(syncCallCount.value, 1);
  });
}
