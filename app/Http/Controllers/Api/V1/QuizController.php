<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Quiz;
use App\Services\QuizService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends ApiController
{
    public function __construct(private QuizService $quizService) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $classIds = $user->enrolledClasses()->pluck('school_classes.id');

        $quizzes = Quiz::whereIn('school_class_id', $classIds)
            ->where('status', 'published')
            ->with('schoolClass.subject')
            ->latest('starts_at')
            ->get();

        return $this->success($quizzes);
    }

    public function show(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorize('view', $quiz);

        $seed = $request->integer('seed') ?: null;

        return $this->success([
            'quiz' => $quiz->load('schoolClass.subject'),
            'questions' => $this->quizService->buildStudentQuestionSet($quiz, $seed),
        ]);
    }

    public function download(Request $request, Quiz $quiz): JsonResponse
    {
        $this->authorize('view', $quiz);

        $seed = crc32($request->user()->id.'-'.$quiz->id);

        return $this->success([
            'quiz' => $quiz->load('schoolClass.subject'),
            'questions' => $this->quizService->buildStudentQuestionSet($quiz, $seed),
            'downloaded_at' => now()->toIso8601String(),
        ], 'Quiz data ready for offline use');
    }
}
