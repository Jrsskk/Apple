<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\GradingService;
use App\Services\QuizService;
use App\Services\SyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class QuizTakeController extends Controller
{
    public function __construct(
        private QuizService $quizService,
        private GradingService $gradingService,
        private SyncService $syncService,
    ) {}

    public function show(Quiz $quiz): View|RedirectResponse
    {
        $this->authorizeStudentAccess($quiz);

        $activeAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', auth()->id())
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->first();

        if ($activeAttempt) {
            return redirect()->route('student.quizzes.take', [$quiz, $activeAttempt]);
        }

        $attempts = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', auth()->id())->count();
        $canStart = ! $quiz->deadline || now()->lessThanOrEqualTo($quiz->deadline);

        return view('student.quizzes.show', compact('quiz', 'attempts', 'canStart'));
    }

    public function start(Quiz $quiz): RedirectResponse
    {
        $this->authorizeStudentAccess($quiz);
        abort_if($quiz->deadline && now()->gt($quiz->deadline), 403, 'The quiz deadline has passed.');

        $activeAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', auth()->id())
            ->where('status', 'in_progress')
            ->latest('started_at')
            ->first();

        if ($activeAttempt) {
            return redirect()->route('student.quizzes.take', [$quiz, $activeAttempt]);
        }

        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', auth()->id())->count();
        abort_if($attemptCount >= $quiz->max_attempts, 403, 'Maximum attempts reached.');

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => auth()->id(),
            'attempt_number' => $attemptCount + 1,
            'sync_uuid' => Str::uuid(),
            'started_at' => now(),
            'status' => 'in_progress',
            'device_id' => session('device_id', SyncService::generateDeviceId()),
        ]);

        session(['device_id' => $attempt->device_id]);

        return redirect()->route('student.quizzes.take', [$quiz, $attempt]);
    }

    public function take(Quiz $quiz, QuizAttempt $attempt): View
    {
        $this->authorizeAttempt($quiz, $attempt);
        $attempt->loadMissing('answers');
        $questions = $this->quizService->getQuestionsForAttempt($quiz);

        return view('student.quizzes.take', compact('quiz', 'attempt', 'questions'));
    }

    public function offlineTake(Quiz $quiz): View
    {
        $this->authorizeStudentAccess($quiz);
        abort_if($quiz->deadline && now()->gt($quiz->deadline), 403, 'The quiz deadline has passed.');

        return view('student.quizzes.offline-take', compact('quiz'));
    }

    public function saveAnswer(Request $request, Quiz $quiz, QuizAttempt $attempt): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAttempt($quiz, $attempt);
        abort_unless($attempt->status === 'in_progress', 403);
        $attemptDeadline = $attempt->started_at?->copy()->addMinutes($quiz->duration_minutes);
        abort_if($attemptDeadline && now()->greaterThanOrEqualTo($attemptDeadline), 403, 'Quiz time has expired.');
        abort_if($quiz->deadline && now()->gt($quiz->deadline), 403, 'The quiz deadline has passed.');
        $data = $request->validate([
            'question_id' => 'required|exists:quiz_questions,id',
            'answer_text' => 'nullable|string',
            'selected_options' => 'nullable|array',
        ]);
        abort_unless($quiz->questions()->whereKey($data['question_id'])->exists(), 403);

        $attempt->answers()->updateOrCreate(
            ['quiz_question_id' => $data['question_id']],
            ['answer_text' => $data['answer_text'] ?? null, 'selected_options' => $data['selected_options'] ?? null]
        );

        return response()->json(['success' => true]);
    }

    public function submit(Request $request, Quiz $quiz, QuizAttempt $attempt): RedirectResponse
    {
        $this->authorizeAttempt($quiz, $attempt);
        abort_unless($attempt->status === 'in_progress', 403);
        $submittedAt = now();
        $attemptDeadline = $attempt->started_at?->copy()->addMinutes($quiz->duration_minutes);

        if ($attemptDeadline && $submittedAt->gt($attemptDeadline)) {
            $submittedAt = $attemptDeadline;
        }

        if ($quiz->deadline && $submittedAt->gt($quiz->deadline)) {
            $submittedAt = $quiz->deadline;
        }

        $attempt->update([
            'submitted_at' => $submittedAt,
            'completed_at' => $submittedAt,
            'status' => 'submitted',
        ]);
        $this->gradingService->autoGradeAttempt($attempt);

        return redirect()->route('student.quizzes.result', [$quiz, $attempt])->with('success', 'Quiz submitted.');
    }

    public function result(Quiz $quiz, QuizAttempt $attempt): View
    {
        $this->authorizeAttempt($quiz, $attempt);

        $attemptStatus = $attempt->status instanceof \BackedEnum ? $attempt->status->value : (string) ($attempt->status ?? '');

        abort_unless(
            $quiz->show_results && ($attemptStatus === 'graded' || $attempt->submitted_at),
            403,
            'Results are not available yet.'
        );
        $attempt->load(['answers.question']);

        return view('student.quizzes.result', compact('quiz', 'attempt'));
    }

    public function download(Quiz $quiz): \Illuminate\Http\JsonResponse
    {
        $this->authorizeStudentAccess($quiz);
        $seed = crc32(auth()->id().'-'.$quiz->id);
        $attemptCount = QuizAttempt::where('quiz_id', $quiz->id)->where('student_id', auth()->id())->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $quiz->id,
                'quiz' => $quiz->load(['schoolClass.subject']),
                'questions' => $this->quizService->buildStudentQuestionSet($quiz, $seed),
                'attempt_count' => $attemptCount,
                'max_attempts' => $quiz->max_attempts,
                'duration_minutes' => $quiz->duration_minutes,
                'downloaded_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function authorizeStudentAccess(Quiz $quiz): void
    {
        abort_unless($quiz->isPublished(), 404);
        $enrolled = auth()->user()->enrolledClasses()->where('school_classes.id', $quiz->school_class_id)->exists();
        abort_unless($enrolled, 403);
    }

    private function authorizeAttempt(Quiz $quiz, QuizAttempt $attempt): void
    {
        abort_unless(
            $attempt->student_id === auth()->id() && $attempt->quiz_id === $quiz->id,
            403
        );
    }
}
