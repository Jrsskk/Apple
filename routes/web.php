<?php

use App\Http\Controllers\Admin\AcademicYearController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\ClassController as AdminClassController;
use App\Http\Controllers\Admin\FileController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubjectController as AdminSubjectController;
use App\Http\Controllers\Admin\SyncController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\Student\AnnouncementController as StudentAnnouncementController;
use App\Http\Controllers\Student\AssignmentSubmitController;
use App\Http\Controllers\Student\ClassController as StudentClassController;
use App\Http\Controllers\Student\MaterialController as StudentMaterialController;
use App\Http\Controllers\Student\ProfileController as StudentProfileController;
use App\Http\Controllers\Student\QuizTakeController;
use App\Http\Controllers\Student\SyncMonitorController;
use App\Http\Controllers\Teacher\AnalyticsController as TeacherAnalyticsController;
use App\Http\Controllers\Teacher\AnnouncementController as TeacherAnnouncementController;
use App\Http\Controllers\Teacher\AssignmentController as TeacherAssignmentController;
use App\Http\Controllers\Teacher\ClassController as TeacherClassController;
use App\Http\Controllers\Teacher\GradeController as TeacherGradeController;
use App\Http\Controllers\Teacher\MaterialController as TeacherMaterialController;
use App\Http\Controllers\Teacher\ProfileController as TeacherProfileController;
use App\Http\Controllers\Teacher\QuizController as TeacherQuizController;
use App\Http\Controllers\Teacher\ReportController as TeacherReportController;
use App\Http\Controllers\Teacher\SubjectController as TeacherSubjectController;
use App\Http\Controllers\Teacher\SubmissionController as TeacherSubmissionController;
use App\Http\Controllers\Teacher\SyncController as TeacherSyncController;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');
Route::get('/offline', fn () => view('offline'))->name('offline');

Route::middleware(['auth', 'verified', 'active'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/profile/photo', [ProfilePhotoController::class, 'show'])->name('profile.photo');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('profile', [App\Http\Controllers\Admin\ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [App\Http\Controllers\Admin\ProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [App\Http\Controllers\Admin\ProfileController::class, 'updatePassword'])->name('profile.password');

        Route::resource('users', UserController::class)->except(['show']);
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('users.reset-password');
        Route::resource('academic-years', AcademicYearController::class)->except(['show', 'destroy']);
        Route::post('academic-years/{academicYear}/activate', [AcademicYearController::class, 'activate'])->name('academic-years.activate');
        Route::post('academic-years/{academicYear}/close', [AcademicYearController::class, 'close'])->name('academic-years.close');
        Route::resource('subjects', AdminSubjectController::class)->except(['show']);
        Route::delete('subjects/{subject}', [AdminSubjectController::class, 'destroy'])->name('subjects.destroy');
        Route::resource('classes', AdminClassController::class)->except(['destroy']);
        Route::delete('classes/{class}', [AdminClassController::class, 'destroy'])->name('classes.destroy');
        Route::post('classes/{class}/enroll', [AdminClassController::class, 'enroll'])->name('classes.enroll');
        Route::delete('classes/{class}/unenroll', [AdminClassController::class, 'unenroll'])->name('classes.unenroll');

        Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        Route::delete('announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('sync', [SyncController::class, 'index'])->name('sync.index');

        Route::get('files', [FileController::class, 'index'])->name('files.index');
        Route::get('files/materials/{material}/download', [FileController::class, 'downloadMaterial'])->name('files.materials.download');
        Route::get('files/submissions/{submission}/download', [FileController::class, 'downloadSubmission'])->name('files.submissions.download');
        Route::delete('files/materials/{material}', [FileController::class, 'destroyMaterial'])->name('files.materials.destroy');

        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/system/export', [ReportController::class, 'exportSystem'])->name('reports.system.export');
        Route::get('reports/academic/export', [ReportController::class, 'exportAcademic'])->name('reports.academic.export');
        Route::get('reports/users/export', [ReportController::class, 'exportUsers'])->name('reports.users.export');
        Route::get('reports/sync/export', [ReportController::class, 'exportSync'])->name('reports.sync.export');

        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('backups/{backup}/download', [BackupController::class, 'download'])->name('backups.download');
        Route::post('backups/{backup}/restore', [BackupController::class, 'restore'])->name('backups.restore');
        Route::delete('backups/{backup}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    Route::middleware('role:teacher,admin')->prefix('teacher')->name('teacher.')->group(function () {
        Route::get('profile', [TeacherProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [TeacherProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [TeacherProfileController::class, 'updatePassword'])->name('profile.password');
        Route::post('profile/avatar', [TeacherProfileController::class, 'updateAvatar'])->name('profile.avatar');
        Route::delete('profile/avatar', [TeacherProfileController::class, 'deleteAvatar'])->name('profile.avatar.destroy');

        Route::get('classes', [TeacherClassController::class, 'index'])->name('classes.index');
        Route::get('classes/create', [TeacherClassController::class, 'create'])->name('classes.create');
        Route::post('classes', [TeacherClassController::class, 'store'])->name('classes.store');
        Route::get('classes/{class}', [TeacherClassController::class, 'show'])->name('classes.show');
        Route::put('classes/{class}', [TeacherClassController::class, 'update'])->name('classes.update');
        Route::post('classes/{class}/enroll', [TeacherClassController::class, 'enroll'])->name('classes.enroll');
        Route::delete('classes/{class}/unenroll', [TeacherClassController::class, 'unenroll'])->name('classes.unenroll');

        Route::get('subjects', [TeacherSubjectController::class, 'index'])->name('subjects.index');
        Route::post('subjects', [TeacherSubjectController::class, 'store'])->name('subjects.store');
        Route::put('subjects/{subject}', [TeacherSubjectController::class, 'update'])->name('subjects.update');

        Route::post('quizzes/generate-from-pdf', [TeacherQuizController::class, 'generateFromPdf'])->name('quizzes.generate-from-pdf');
        Route::resource('quizzes', TeacherQuizController::class);
        Route::post('quizzes/{quiz}/duplicate', [TeacherQuizController::class, 'duplicate'])->name('quizzes.duplicate');
        Route::post('quizzes/{quiz}/publish', [TeacherQuizController::class, 'publish'])->name('quizzes.publish');

        Route::resource('assignments', TeacherAssignmentController::class)->except(['show', 'destroy']);
        Route::get('assignments/{assignment}/attachment', [TeacherAssignmentController::class, 'downloadAttachment'])->name('assignments.attachment');
        Route::delete('assignments/{assignment}/attachment', [TeacherAssignmentController::class, 'deleteAttachment'])->name('assignments.attachment.destroy');
        Route::post('assignments/{assignment}/publish', [TeacherAssignmentController::class, 'publish'])->name('assignments.publish');
        Route::post('submissions/{submission}/grade', [TeacherAssignmentController::class, 'grade'])->name('submissions.grade');

        Route::get('materials', [TeacherMaterialController::class, 'index'])->name('materials.index');
        Route::post('materials', [TeacherMaterialController::class, 'store'])->name('materials.store');
        Route::post('materials/{material}/replace', [TeacherMaterialController::class, 'replace'])->name('materials.replace');
        Route::delete('materials/{material}', [TeacherMaterialController::class, 'destroy'])->name('materials.destroy');
        Route::get('materials/{material}/download', [TeacherMaterialController::class, 'download'])->name('materials.download');

        Route::get('submissions', [TeacherSubmissionController::class, 'index'])->name('submissions.index');
        Route::get('submissions/quiz/{attempt}', [TeacherSubmissionController::class, 'showQuizAttempt'])->name('submissions.quiz');
        Route::get('submissions/assignment/{submission}', [TeacherSubmissionController::class, 'showAssignmentSubmission'])->name('submissions.assignment');
        Route::post('submissions/assignment/{submission}/grade', [TeacherSubmissionController::class, 'gradeAssignment'])->name('submissions.assignment.grade');
        Route::get('submissions/assignment/{submission}/download', [TeacherSubmissionController::class, 'downloadSubmissionFile'])->name('submissions.assignment.download');

        Route::get('grades', [TeacherGradeController::class, 'index'])->name('grades.index');
        Route::get('analytics', [TeacherAnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('announcements', [TeacherAnnouncementController::class, 'index'])->name('announcements.index');
        Route::post('announcements', [TeacherAnnouncementController::class, 'store'])->name('announcements.store');
        Route::put('announcements/{announcement}', [TeacherAnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('announcements/{announcement}', [TeacherAnnouncementController::class, 'destroy'])->name('announcements.destroy');

        Route::get('reports', [TeacherReportController::class, 'index'])->name('reports.index');
        Route::get('reports/quiz/{quiz}/export', [TeacherReportController::class, 'exportQuiz'])->name('reports.quiz.export');
        Route::get('reports/assignment/{assignment}/export', [TeacherReportController::class, 'exportAssignment'])->name('reports.assignment.export');
        Route::get('reports/class/{class}/export', [TeacherReportController::class, 'exportClass'])->name('reports.class.export');

        Route::get('sync', [TeacherSyncController::class, 'index'])->name('sync.index');
    });

    Route::middleware('role:student')->prefix('student')->name('student.')->group(function () {
        Route::get('profile', [StudentProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('profile', [StudentProfileController::class, 'update'])->name('profile.update');
        Route::put('profile/password', [StudentProfileController::class, 'updatePassword'])->name('profile.password');

        Route::get('classes', [StudentClassController::class, 'index'])->name('classes.index');
        Route::post('classes/join', [StudentClassController::class, 'join'])->name('classes.join');
        Route::get('classes/{class}', [StudentClassController::class, 'show'])->name('classes.show');
        Route::get('activities', fn () => view('student.activities.index', app(DashboardService::class)->studentStats(auth()->user())))->name('activities.index');
        Route::get('announcements', [StudentAnnouncementController::class, 'index'])->name('announcements.index');
        Route::get('materials', [StudentMaterialController::class, 'index'])->name('materials.index');
        Route::get('sync', [SyncMonitorController::class, 'index'])->name('sync.index');
        Route::get('grades', function () {
            $student = auth()->user();

            return view('student.grades.index', [
                'quizGrades' => $student->quizAttempts()
                    ->where('status', 'graded')
                    ->whereHas('quiz', fn ($query) => $query->whereHas(
                        'schoolClass',
                        fn ($classQuery) => $classQuery->whereColumn('school_classes.subject_id', 'quizzes.subject_id')
                    ))
                    ->with(['quiz.subject', 'quiz.schoolClass'])
                    ->latest('submitted_at')
                    ->get(),
                'assignmentGrades' => $student->assignmentSubmissions()
                    ->whereIn('status', ['graded', 'returned', 'submitted', 'late'])
                    ->whereHas('assignment', fn ($query) => $query->whereHas(
                        'schoolClass',
                        fn ($classQuery) => $classQuery->whereColumn('school_classes.subject_id', 'assignments.subject_id')
                    ))
                    ->with(['assignment.subject', 'assignment.schoolClass'])
                    ->latest('submitted_at')
                    ->get(),
            ]);
        })->name('grades.index');
        Route::get('notifications', fn () => view('student.notifications', ['notifications' => auth()->user()->notifications()->latest()->paginate(20)]))->name('notifications');
        Route::get('quizzes/{quiz}', [QuizTakeController::class, 'show'])->name('quizzes.show');
        Route::post('quizzes/{quiz}/start', [QuizTakeController::class, 'start'])->name('quizzes.start');
        Route::get('quizzes/{quiz}/offline', [QuizTakeController::class, 'offlineTake'])->name('quizzes.offline');
        Route::get('quizzes/{quiz}/take/{attempt}', [QuizTakeController::class, 'take'])->name('quizzes.take');
        Route::post('quizzes/{quiz}/take/{attempt}/answer', [QuizTakeController::class, 'saveAnswer'])->name('quizzes.save-answer');
        Route::post('quizzes/{quiz}/take/{attempt}/submit', [QuizTakeController::class, 'submit'])->name('quizzes.submit');
        Route::get('quizzes/{quiz}/result/{attempt}', [QuizTakeController::class, 'result'])->name('quizzes.result');
        Route::get('quizzes/{quiz}/download', [QuizTakeController::class, 'download'])->name('quizzes.download');
        Route::get('assignments', [AssignmentSubmitController::class, 'index'])->name('assignments.index');
        Route::get('assignments/{assignment}', [AssignmentSubmitController::class, 'show'])->name('assignments.show');
        Route::get('assignments/{assignment}/download', [AssignmentSubmitController::class, 'download'])->name('assignments.download');
        Route::get('assignments/{assignment}/attachment', [AssignmentSubmitController::class, 'downloadAttachment'])->name('assignments.attachment');
        Route::get('assignments/{assignment}/submission-file', [AssignmentSubmitController::class, 'downloadSubmission'])->name('assignments.submission-file');
        Route::delete('assignments/{assignment}/submission-file', [AssignmentSubmitController::class, 'deleteSubmissionFile'])->name('assignments.submission-file.destroy');
        Route::post('assignments/{assignment}/draft', [AssignmentSubmitController::class, 'saveDraft'])->name('assignments.draft');
        Route::post('assignments/{assignment}/submit', [AssignmentSubmitController::class, 'submit'])->name('assignments.submit');
        Route::get('materials/{material}/download', [StudentMaterialController::class, 'downloadJson'])->name('materials.download');
        Route::get('materials/{material}/file', [StudentMaterialController::class, 'downloadFile'])->name('materials.file');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('student/materials/{material}/secure-file', [StudentMaterialController::class, 'secureFile'])
    ->middleware('signed')
    ->name('student.materials.secure-file');

require __DIR__.'/auth.php';
