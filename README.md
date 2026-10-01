# EduSync

**EduSync** is a web-based educational management system with interactive quizzes, assignment management, and offline synchronization for students. Built with Laravel 12, PHP 8.2+, MySQL 8, Blade, Tailwind CSS, Alpine.js, and Chart.js.

## Features

- Role-based access control (Administrator, Teacher, Student)
- Admin dashboard with analytics and user management
- Teacher quiz builder with multiple question types and automatic grading
- Assignment management with manual grading
- Student mobile-first PWA interface with bottom navigation
- Offline quiz/assignment support via IndexedDB and service workers
- UUID-based idempotent synchronization
- Database notifications, audit logs, and reports

## Requirements

- PHP 8.2+
- Composer
- MySQL 8+
- Node.js 18+

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Configure MySQL in `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=edusync
DB_USERNAME=root
DB_PASSWORD=
```

Then run:

```bash
php artisan migrate --seed
php artisan storage:link
npm install
npm run build
php artisan serve
php artisan reverb:start
```

### Supabase Storage file uploads

New learning materials, assignment attachments, student assignment submissions,
and teacher profile photos are stored in private Supabase Storage buckets. EduSync
uses the existing relational records and file paths; no duplicate file tables are
created. Authenticated Laravel routes proxy downloads and retain the existing
class/teacher/student access checks. The Supabase service-role key is only used by
Laravel and must never be added to a `VITE_` variable or frontend code.

```env
SUPABASE_URL=https://your-project-ref.supabase.co
SUPABASE_KEY=your-project-anon-or-publishable-key
SUPABASE_SERVICE_ROLE_KEY=your-server-only-service-role-secret
```

To connect a Supabase project:

1. Create or select a Supabase project and copy its project URL, anon/publishable
   key, and service-role secret from **Project Settings → API**.
2. Put those values in the Laravel `.env` file shown above. Keep the service-role
   secret server-side and do not commit `.env`.
3. Run `php artisan migrate` to add nullable storage-disk metadata to existing
   material, assignment, submission, and user records. Existing records and their
   relationships are preserved.
4. Run `php artisan config:clear` and `php artisan supabase:storage-setup`. This
   creates the private `materials`, `assignments`, and `profiles` buckets and
   makes existing buckets private; the command is safe to rerun.

The app also ensures the corresponding private bucket exists before uploads. Files
are stored in `materials` (learning materials), `assignments` (assignment
attachments and student submissions), and `profiles` (profile images). Uploads,
replacements, and deletes update existing database records and clean up old/new
objects on failures. Existing Google Drive and Laravel-disk files remain readable;
new uploads use Supabase Storage.

### Legacy Google Drive files

Existing learning materials stored in Google Drive continue to be served through
the existing protected routes. Configure the Google Drive environment variables
only if you still need to access or migrate those files. New material uploads no
longer require Google Drive configuration.

Visit [http://127.0.0.1:8000](http://127.0.0.1:8000)

## Development Login Credentials

> **Development only — change in production**

| Role        | Email                 | Password |
|-------------|-----------------------|----------|
| Admin       | admin@edusync.test    | password |
| Teacher     | teacher@edusync.test  | password |
| Student     | student@edusync.test  | password |

## API (Offline Sync)

Authenticate via Sanctum:

```http
POST /api/v1/auth/login
```

Endpoints:

- `GET /api/v1/classes`
- `GET /api/v1/quizzes/{id}`
- `GET /api/v1/assignments/{id}`
- `POST /api/v1/sync`
- `POST /api/v1/sync/now`
- `GET /api/v1/grades`
- `GET /api/v1/announcements`

## Testing

```bash
php artisan test
```

## PWA

Students can install EduSync on their home screen. The service worker caches assets and IndexedDB stores offline quiz data and sync queue entries.

## License

MIT
