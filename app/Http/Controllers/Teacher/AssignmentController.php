<?php

namespace App\Http\Controllers\Teacher;

use App\Exceptions\SupabaseStorageException;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\SchoolClass;
use App\Services\AssignmentFileService;
use App\Services\AssignmentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignmentController extends Controller
{
    public function __construct(
        private AssignmentService $assignmentService,
        private NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $classes = $request->user()->taughtClasses()->with('subject:id,name')->get();
        $classIds = $classes->pluck('id');
        $subjectId = $request->integer('subject_id') ?: null;
        $assignments = Assignment::where('teacher_id', $request->user()->id)
            ->whereIn('school_class_id', $classIds)
            ->whereHas('schoolClass', fn ($query) => $query
                ->whereColumn('school_classes.subject_id', 'assignments.subject_id'))
            ->when($subjectId, fn ($query) => $query->where('subject_id', $subjectId))
            ->with(['schoolClass', 'subject'])
            ->withCount('submissions')
            ->orderBy('subject_id')
            ->orderBy('school_class_id')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Teacher/Assignments/Index', [
            'assignments' => $assignments,
            'subjects' => $classes->pluck('subject')->filter()->unique('id')->values(),
            'filters' => ['subject_id' => $subjectId],
        ]);
    }

    public function create(Request $request): Response
    {
        $classes = $request->user()->taughtClasses()->with('subject')->get();

        return Inertia::render('Teacher/Assignments/Create', [
            'classes' => $classes,
            'subjects' => $classes->pluck('subject')->filter()->unique('id')->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'subject_id' => 'required|exists:subjects,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'starts_at' => 'nullable|date',
            'deadline' => 'nullable|date|after_or_equal:starts_at',
            'max_score' => 'required|numeric|min:1',
            'allow_resubmit' => 'boolean',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png',
        ]);

        if (! SchoolClass::whereKey($data['school_class_id'])
            ->where('subject_id', $data['subject_id'])
            ->exists()) {
            throw ValidationException::withMessages([
                'subject_id' => 'The selected subject does not belong to this class.',
            ]);
        }

        if ($request->user()->isTeacher()) {
            abort_unless($request->user()->taughtClasses()->whereKey($data['school_class_id'])->exists(), 403);
        }

        try {
            $this->assignmentService->create($request->user(), $data, $request->file('attachment'));
        } catch (SupabaseStorageException $exception) {
            return back()->withInput()->withErrors(['attachment' => $exception->getMessage()]);
        }

        return redirect()->route('teacher.assignments.index')->with('success', 'Assignment created.');
    }

    public function edit(Assignment $assignment): Response
    {
        abort_unless($assignment->teacher_id === auth()->id(), 403);
        $assignment->load(['schoolClass', 'submissions.student']);

        return Inertia::render('Teacher/Assignments/Edit', [
            'assignment' => $assignment,
        ]);
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->teacher_id === auth()->id(), 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'starts_at' => 'nullable|date',
            'deadline' => 'nullable|date|after_or_equal:starts_at',
            'max_score' => 'required|numeric|min:1',
            'allow_resubmit' => 'boolean',
            'attachment' => 'nullable|file|max:10240|mimes:pdf,doc,docx,ppt,pptx,jpg,jpeg,png',
        ]);

        try {
            $assignment = $this->assignmentService->update($assignment, $data, $request->file('attachment'));
            if ($assignment->status?->value === 'published') {
                $recipients = $this->notifications->studentsForClassSubject(
                    (int) $assignment->school_class_id,
                    (int) $assignment->subject_id,
                );
                $this->notifications->notifyAssignmentUpdated($assignment, $recipients);
            }
        } catch (SupabaseStorageException $exception) {
            return back()->withInput()->withErrors(['attachment' => $exception->getMessage()]);
        }

        return back()->with('success', 'Assignment updated.');
    }

    public function downloadAttachment(
        Request $request,
        Assignment $assignment,
        AssignmentFileService $files,
    ): StreamedResponse {
        abort_unless($assignment->teacher_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless($assignment->attachment_path, 404);

        return $files->response(
            $assignment->attachment_path,
            $assignment->attachment_storage_disk,
            $assignment->attachment_file_name ?: basename($assignment->attachment_path),
            $request->boolean('download', true),
            $assignment->attachment_file_type,
        );
    }

    public function deleteAttachment(Request $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->teacher_id === $request->user()->id || $request->user()->isAdmin(), 403);
        abort_unless($assignment->attachment_path, 404);

        $oldPath = $assignment->attachment_path;
        $oldDisk = $assignment->attachment_storage_disk;
        $oldMetadata = [
            'attachment_file_name' => $assignment->attachment_file_name,
            'attachment_file_type' => $assignment->attachment_file_type,
            'attachment_file_size' => $assignment->attachment_file_size,
        ];
        $assignment->update([
            'attachment_path' => null,
            'attachment_storage_disk' => null,
            'attachment_file_name' => null,
            'attachment_file_type' => null,
            'attachment_file_size' => null,
        ]);

        try {
            $this->assignmentService->deleteAssignmentAttachment($oldPath, $oldDisk);
        } catch (\RuntimeException $exception) {
            $assignment->update([
                'attachment_path' => $oldPath,
                'attachment_storage_disk' => $oldDisk,
                ...$oldMetadata,
            ]);

            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Assignment attachment deleted.');
    }

    public function publish(Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->teacher_id === auth()->id(), 403);
        $assignment->update(['status' => 'published', 'posted_at' => now()]);
        $recipients = $this->notifications->studentsForClassSubject(
            (int) $assignment->school_class_id,
            (int) $assignment->subject_id,
        );
        $this->notifications->notifyAssignmentPosted($assignment, $recipients);

        return back()->with('success', 'Assignment published.');
    }

    public function grade(Request $request, AssignmentSubmission $submission): RedirectResponse
    {
        abort_unless($submission->assignment->teacher_id === auth()->id(), 403);
        $data = $request->validate([
            'score' => 'required|numeric|min:0|max:'.$submission->assignment->max_score,
            'feedback' => 'nullable|string',
            'status' => 'required|in:graded,returned',
        ]);
        $submission->update($data);
        app(NotificationService::class)->notifyAssignmentGrade($submission);

        return back()->with('success', 'Submission graded.');
    }
}
