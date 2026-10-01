<?php

namespace App\Http\Controllers\Teacher;

use App\Events\QuizPublished;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreQuizRequest;
use App\Models\Quiz;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Services\PdfQuizGeneratorService;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuizController extends Controller
{
    public function __construct(
        private QuizService $quizService,
        private AuditLogService $auditLog,
        private NotificationService $notifications,
        private PdfQuizGeneratorService $pdfQuizGenerator,
    ) {}

    public function index(Request $request): Response
    {
        $quizzes = Quiz::where('teacher_id', $request->user()->id)
            ->with(['schoolClass', 'subject'])
            ->withCount('questions')
            ->latest()
            ->paginate(15);

        return Inertia::render('Teacher/Quizzes/Index', [
            'quizzes' => $quizzes,
        ]);
    }

    public function create(Request $request): Response
    {
        $classes = $request->user()->taughtClasses()->with('subject')->get();

        return Inertia::render('Teacher/Quizzes/Create', [
            'classes' => $classes,
            'subjects' => $classes->pluck('subject')->filter()->unique('id')->values(),
        ]);
    }

    public function generateFromPdf(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pdf_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'question_count' => ['required', 'integer', 'min:1', 'max:12'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_type' => ['required', 'in:multiple_choice,true_false,identification'],
        ]);

        try {
            $questions = $this->pdfQuizGenerator->generate(
                $request->file('pdf_file'),
                (int) $validated['question_count'],
                $validated['difficulty'],
                $validated['question_type'],
            );

            return response()->json([
                'questions' => $questions,
                'message' => 'Generated '.count($questions).' questions from the uploaded PDF.',
            ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }

    public function store(StoreQuizRequest $request): RedirectResponse
    {
        $quiz = $this->quizService->create($request->validated(), $request->user());

        return redirect()->route('teacher.quizzes.edit', $quiz)->with('success', 'Quiz created.');
    }

    public function edit(Quiz $quiz): Response
    {
        $this->authorizeTeacher($quiz);
        $quiz->load(['questions.options', 'schoolClass', 'subject']);

        return Inertia::render('Teacher/Quizzes/Edit', [
            'quiz' => $quiz,
        ]);
    }

    public function update(StoreQuizRequest $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeTeacher($quiz);
        $this->quizService->update($quiz, $request->validated());

        return back()->with('success', 'Quiz updated.');
    }

    public function destroy(Quiz $quiz): RedirectResponse
    {
        $this->authorizeTeacher($quiz);
        $quiz->delete();

        return redirect()->route('teacher.quizzes.index')->with('success', 'Quiz archived.');
    }

    public function duplicate(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeTeacher($quiz);
        $new = $this->quizService->duplicate($quiz, $request->user());

        return redirect()->route('teacher.quizzes.edit', $new)->with('success', 'Quiz duplicated.');
    }

    public function publish(Request $request, Quiz $quiz): RedirectResponse
    {
        $this->authorizeTeacher($quiz);
        $errors = $this->quizService->publishValidationErrors($quiz);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $this->quizService->publish($quiz, $request->user());
        $quiz->load('schoolClass.students');
        foreach ($quiz->schoolClass->students as $student) {
            $this->notifications->notifyNewQuiz($student, $quiz->title);
            QuizPublished::dispatch($quiz, $student->id);
        }

        return back()->with('success', 'Quiz published.');
    }

    private function authorizeTeacher(Quiz $quiz): void
    {
        abort_unless($quiz->teacher_id === auth()->id() || auth()->user()->isAdmin(), 403);
    }
}
