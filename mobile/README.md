# EduSync Student App

Run against the Laravel server with:

```powershell
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

For a physical device, replace `10.0.2.2` with your computer's LAN IP. The app stores the Sanctum bearer token in secure storage, caches downloaded work in SQLite, and queues UUID-idempotent submissions in SQLite until connectivity returns.
