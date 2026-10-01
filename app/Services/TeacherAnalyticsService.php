<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherAnalyticsService
{
    private const COMPLETED_SUBMISSION_STATUSES = ['submitted', 'late', 'graded', 'returned'];

    /**
     * Build analytics only from classes, activities, and enrolled learners owned by this teacher.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function dashboard(User $teacher, array $filters): array
    {
        $baseClasses = SchoolClass::query()->where('teacher_id', $teacher->id);
        $academicYears = AcademicYear::query()
            ->whereIn('id', (clone $baseClasses)->select('academic_year_id'))
            ->orderByDesc('start_date')
            ->get(['id', 'name']);

        $subjects = DB::table('subjects')
            ->join('school_classes', 'school_classes.subject_id', '=', 'subjects.id')
            ->where('school_classes.teacher_id', $teacher->id)
            ->whereNull('subjects.deleted_at')
            ->when($filters['academic_year_id'] ?? null, fn ($query, $id) => $query->where('school_classes.academic_year_id', $id))
            ->select('subjects.id', 'subjects.name')
            ->distinct()
            ->orderBy('subjects.name')
            ->get();

        $classOptions = (clone $baseClasses)
            ->with('subject:id,name')
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->orderBy('name')
            ->orderBy('section')
            ->get(['id', 'name', 'section', 'subject_id', 'academic_year_id'])
            ->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->display_name,
                'subject_id' => $class->subject_id,
                'academic_year_id' => $class->academic_year_id,
            ]);

        $selectedClassIds = (clone $baseClasses)
            ->when($filters['academic_year_id'] ?? null, fn (Builder $query, $id) => $query->where('academic_year_id', $id))
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->when($filters['school_class_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->pluck('id');

        $this->validateScopedFilters($filters, $academicYears, $subjects, $classOptions, $selectedClassIds);

        $classRows = SchoolClass::query()
            ->whereIn('id', $selectedClassIds)
            ->with('subject:id,name')
            ->get(['id', 'name', 'section', 'subject_id']);

        $rosterRows = DB::table('class_students')
            ->join('users', 'users.id', '=', 'class_students.student_id')
            ->whereIn('class_students.school_class_id', $selectedClassIds)
            ->where('class_students.status', 'enrolled')
            ->where('users.role', UserRole::Student->value)
            ->whereNull('users.deleted_at')
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->where('users.id', $id))
            ->select(
                'class_students.school_class_id',
                'users.id',
                'users.first_name',
                'users.last_name',
                'users.email'
            )
            ->orderBy('users.last_name')
            ->orderBy('users.first_name')
            ->get();

        $rosterByClass = $rosterRows->groupBy('school_class_id');
        $students = $rosterRows->unique('id')->values();
        $studentIds = $students->pluck('id');
        if (isset($filters['student_id']) && ! $students->contains('id', (int) $filters['student_id'])) {
            throw ValidationException::withMessages(['student_id' => 'The selected student is not enrolled in your selected classes.']);
        }
        $quizzes = $this->quizQuery($selectedClassIds, $filters)->get();
        $assignments = $this->assignmentQuery($selectedClassIds, $filters)->get();

        $attempts = QuizAttempt::query()
            ->with(['quiz:id,title,subject_id,school_class_id,total_points', 'quiz.subject:id,name', 'student:id,first_name,last_name'])
            ->whereIn('quiz_id', $quizzes->pluck('id'))
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['submitted', 'graded'])
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('submitted_at', '<=', $date))
            ->orderByDesc('submitted_at')
            ->get();

        $attempts = $attempts->filter(fn (QuizAttempt $attempt) => $rosterByClass
            ->get($attempt->quiz->school_class_id, collect())
            ->contains('id', $attempt->student_id))
            ->values();

        $assignmentSubmissions = AssignmentSubmission::query()
            ->with(['assignment:id,title,subject_id,school_class_id,max_score', 'assignment.subject:id,name', 'student:id,first_name,last_name'])
            ->whereIn('assignment_id', $assignments->pluck('id'))
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', self::COMPLETED_SUBMISSION_STATUSES)
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('submitted_at', '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate('submitted_at', '<=', $date))
            ->orderByDesc('version')
            ->orderByDesc('submitted_at')
            ->get();

        $assignmentSubmissions = $assignmentSubmissions->filter(fn (AssignmentSubmission $submission) => $rosterByClass
            ->get($submission->assignment->school_class_id, collect())
            ->contains('id', $submission->student_id))
            ->values();
        $assignmentSubmissions = $assignmentSubmissions
            ->unique(fn (AssignmentSubmission $submission) => $submission->student_id.':'.$submission->assignment_id)
            ->values();

        $gradedAttempts = $attempts->where('status', 'graded')->filter(fn (QuizAttempt $attempt) => $attempt->percentage !== null);
        $gradedAssignments = $assignmentSubmissions->where('status', 'graded')->filter(fn (AssignmentSubmission $submission) => $submission->score !== null);
        $assignmentPercentages = $gradedAssignments->map(fn (AssignmentSubmission $submission) => $this->assignmentPercentage($submission));
        $scores = $gradedAttempts->pluck('percentage')->concat($assignmentPercentages)->filter(fn ($score) => $score !== null);

        $quizCompletion = $this->completionCounts($quizzes, $rosterByClass, $attempts, fn (Quiz $quiz) => $quiz->school_class_id, fn (QuizAttempt $attempt) => $attempt->quiz_id);
        $assignmentCompletion = $this->completionCounts($assignments, $rosterByClass, $assignmentSubmissions, fn (Assignment $assignment) => $assignment->school_class_id, fn (AssignmentSubmission $submission) => $submission->assignment_id);
        $totalOpportunities = $quizCompletion['opportunities'] + $assignmentCompletion['opportunities'];
        $completedCount = $quizCompletion['completed'] + $assignmentCompletion['completed'];
        $activityCounts = $this->activityStatusCounts(
            $quizzes,
            $assignments,
            $rosterByClass,
            $attempts,
            $assignmentSubmissions
        );

        $studentRows = $this->studentPerformance($students, $attempts, $assignmentSubmissions, $assignments);
        $subjectPerformance = $this->subjectPerformance($classRows, $gradedAttempts, $gradedAssignments);
        $progress = $this->progressOverTime($attempts, $assignmentSubmissions, $filters);
        $gradeDistribution = $this->gradeDistribution($scores);
        $questionPerformance = $this->questionPerformance($gradedAttempts);
        $participatingStudents = $attempts->pluck('student_id')
            ->merge($assignmentSubmissions->pluck('student_id'))
            ->unique()
            ->count();
        $rosterCount = $students->count();

        $studentOptions = $rosterRows->unique('id')->map(fn ($student) => [
            'id' => $student->id,
            'name' => trim($student->first_name.' '.$student->last_name),
        ])->values();

        return [
            'filters' => $filters,
            'options' => [
                'subjects' => $subjects,
                'classes' => $classOptions,
                'quizzes' => $this->quizOptions($selectedClassIds, $filters),
                'assignments' => $this->assignmentOptions($selectedClassIds, $filters),
                'students' => $studentOptions,
                'academicYears' => $academicYears,
            ],
            'stats' => [
                'students' => $rosterCount,
                'average_score' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
                'average_quiz_score' => $gradedAttempts->isNotEmpty() ? round($gradedAttempts->avg('percentage'), 1) : null,
                'average_assignment_score' => $assignmentPercentages->isNotEmpty() ? round($assignmentPercentages->avg(), 1) : null,
                'quiz_completion_rate' => $this->percentage($quizCompletion['completed'], $quizCompletion['opportunities']),
                'assignment_completion_rate' => $this->percentage($assignmentCompletion['completed'], $assignmentCompletion['opportunities']),
                'submission_rate' => $this->percentage($completedCount, $totalOpportunities),
                'participation_rate' => $this->percentage($participatingStudents, $rosterCount),
                'participants' => $participatingStudents,
                'completed_activities' => $activityCounts['completed'],
                'pending_activities' => $activityCounts['pending'],
                'overdue_activities' => $activityCounts['overdue'],
            ],
            'charts' => [
                'subjectPerformance' => $subjectPerformance,
                'progress' => $progress,
                'gradeDistribution' => $gradeDistribution,
                'questionPerformance' => $questionPerformance,
            ],
            'students' => $studentRows,
            'empty' => [
                'hasRoster' => $rosterCount > 0,
                'hasScores' => $scores->isNotEmpty(),
                'hasActivities' => $quizzes->isNotEmpty() || $assignments->isNotEmpty(),
            ],
        ];
    }

    private function quizQuery(Collection $classIds, array $filters): Builder
    {
        return Quiz::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'published')
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->when($filters['quiz_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate(DB::raw('COALESCE(starts_at, created_at)'), '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate(DB::raw('COALESCE(starts_at, created_at)'), '<=', $date));
    }

    private function assignmentQuery(Collection $classIds, array $filters): Builder
    {
        return Assignment::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'published')
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->when($filters['assignment_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
            ->when($filters['from_date'] ?? null, fn (Builder $query, $date) => $query->whereDate(DB::raw('COALESCE(posted_at, created_at)'), '>=', $date))
            ->when($filters['to_date'] ?? null, fn (Builder $query, $date) => $query->whereDate(DB::raw('COALESCE(posted_at, created_at)'), '<=', $date));
    }

    private function quizOptions(Collection $classIds, array $filters): Collection
    {
        return Quiz::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'published')
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Quiz $quiz) => ['id' => $quiz->id, 'name' => $quiz->title]);
    }

    private function assignmentOptions(Collection $classIds, array $filters): Collection
    {
        return Assignment::query()
            ->whereIn('school_class_id', $classIds)
            ->where('status', 'published')
            ->when($filters['subject_id'] ?? null, fn (Builder $query, $id) => $query->where('subject_id', $id))
            ->orderBy('title')
            ->get(['id', 'title'])
            ->map(fn (Assignment $assignment) => ['id' => $assignment->id, 'name' => $assignment->title]);
    }

    private function validateScopedFilters(
        array $filters,
        Collection $academicYears,
        Collection $subjects,
        Collection $classes,
        Collection $classIds
    ): void {
        $invalid = (isset($filters['academic_year_id']) && ! $academicYears->contains('id', (int) $filters['academic_year_id']))
            || (isset($filters['subject_id']) && ! $subjects->contains('id', (int) $filters['subject_id']))
            || (isset($filters['school_class_id']) && ! $classes->contains('id', (int) $filters['school_class_id']));

        if ($invalid || ((isset($filters['quiz_id']) || isset($filters['assignment_id'])) && $classIds->isEmpty())) {
            throw ValidationException::withMessages(['filters' => 'The selected analytics filters are not available for your classes.']);
        }

        if (isset($filters['quiz_id']) && ! $this->quizOptions($classIds, $filters)->contains('id', (int) $filters['quiz_id'])) {
            throw ValidationException::withMessages(['quiz_id' => 'The selected quiz is not available for your classes.']);
        }

        if (isset($filters['assignment_id']) && ! $this->assignmentOptions($classIds, $filters)->contains('id', (int) $filters['assignment_id'])) {
            throw ValidationException::withMessages(['assignment_id' => 'The selected assignment is not available for your classes.']);
        }
    }

    private function completionCounts(
        Collection $activities,
        Collection $rosterByClass,
        Collection $events,
        callable $classId,
        callable $eventActivityId
    ): array {
        $pairs = [];
        foreach ($events as $event) {
            $pairs[$event->student_id.':'.$eventActivityId($event)] = true;
        }

        $opportunities = 0;
        foreach ($activities as $activity) {
            $opportunities += $rosterByClass->get($classId($activity), collect())->count();
        }

        return [
            'opportunities' => $opportunities,
            'completed' => count($pairs),
        ];
    }

    private function activityStatusCounts(
        Collection $quizzes,
        Collection $assignments,
        Collection $rosterByClass,
        Collection $attempts,
        Collection $submissions
    ): array {
        $completedPairs = [];
        foreach ($attempts as $attempt) {
            $completedPairs[$attempt->student_id.':quiz:'.$attempt->quiz_id] = true;
        }
        foreach ($submissions as $submission) {
            $completedPairs[$submission->student_id.':assignment:'.$submission->assignment_id] = true;
        }

        $counts = ['completed' => count($completedPairs), 'pending' => 0, 'overdue' => 0];
        foreach ($quizzes as $quiz) {
            $this->countOutstanding(
                $counts,
                $rosterByClass->get($quiz->school_class_id, collect()),
                $quiz->id,
                'quiz',
                $quiz->deadline,
                $completedPairs
            );
        }
        foreach ($assignments as $assignment) {
            $this->countOutstanding(
                $counts,
                $rosterByClass->get($assignment->school_class_id, collect()),
                $assignment->id,
                'assignment',
                $assignment->deadline,
                $completedPairs
            );
        }

        return $counts;
    }

    private function countOutstanding(array &$counts, Collection $roster, int $activityId, string $type, mixed $deadline, array $completedPairs): void
    {
        $isOverdue = $deadline !== null && Carbon::parse($deadline)->isPast();
        foreach ($roster as $student) {
            if (isset($completedPairs[$student->id.':'.$type.':'.$activityId])) {
                continue;
            }

            $counts[$isOverdue ? 'overdue' : 'pending']++;
        }
    }

    private function studentPerformance(Collection $students, Collection $attempts, Collection $submissions, Collection $assignments): array
    {
        $assignmentMaxScores = $assignments->keyBy('id');
        $attemptsByStudent = $attempts->groupBy('student_id');
        $submissionsByStudent = $submissions->groupBy('student_id');

        return $students->map(function ($student) use ($attemptsByStudent, $submissionsByStudent, $assignmentMaxScores) {
            $studentAttempts = $attemptsByStudent->get($student->id, collect());
            $studentSubmissions = $submissionsByStudent->get($student->id, collect());
            $quizScores = $studentAttempts->where('status', 'graded')->pluck('percentage')->filter(fn ($score) => $score !== null);
            $assignmentScores = $studentSubmissions->where('status', 'graded')
                ->filter(fn (AssignmentSubmission $submission) => $submission->score !== null)
                ->map(function (AssignmentSubmission $submission) use ($assignmentMaxScores) {
                    $max = (float) ($assignmentMaxScores->get($submission->assignment_id)?->max_score ?? 0);

                    return $max > 0 ? min(100, ((float) $submission->score / $max) * 100) : null;
                })
                ->filter(fn ($score) => $score !== null);
            $scores = $quizScores->concat($assignmentScores);

            return [
                'id' => $student->id,
                'name' => trim($student->first_name.' '.$student->last_name),
                'email' => $student->email,
                'quiz_score' => $quizScores->isNotEmpty() ? round($quizScores->avg(), 1) : null,
                'assignment_score' => $assignmentScores->isNotEmpty() ? round($assignmentScores->avg(), 1) : null,
                'average_score' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
                'completed' => $studentAttempts->pluck('quiz_id')->unique()->count() + $studentSubmissions->pluck('assignment_id')->unique()->count(),
            ];
        })->values()->all();
    }

    private function subjectPerformance(Collection $classes, Collection $attempts, Collection $submissions): array
    {
        $subjects = $classes->pluck('subject')->filter()->unique('id');

        return $subjects->map(function ($subject) use ($classes, $attempts, $submissions) {
            $subjectClassIds = $classes->where('subject_id', $subject->id)->pluck('id');
            $quizScores = $attempts->filter(fn (QuizAttempt $attempt) => $attempt->quiz && $subjectClassIds->contains($attempt->quiz->school_class_id))
                ->pluck('percentage');
            $assignmentScores = $submissions->filter(fn (AssignmentSubmission $submission) => $submission->assignment && $subjectClassIds->contains($submission->assignment->school_class_id))
                ->map(fn (AssignmentSubmission $submission) => $this->assignmentPercentage($submission))
                ->filter(fn ($score) => $score !== null);

            return [
                'subject' => $subject->name,
                'quiz' => $quizScores->isNotEmpty() ? round($quizScores->avg(), 1) : null,
                'assignment' => $assignmentScores->isNotEmpty() ? round($assignmentScores->avg(), 1) : null,
            ];
        })->values()->all();
    }

    private function progressOverTime(Collection $attempts, Collection $submissions, array $filters): array
    {
        $dates = collect();
        foreach ($attempts->where('status', 'graded')->filter(fn (QuizAttempt $attempt) => $attempt->percentage !== null) as $attempt) {
            if ($attempt->submitted_at) {
                $dates->push(['date' => $attempt->submitted_at, 'score' => (float) $attempt->percentage]);
            }
        }
        foreach ($submissions->where('status', 'graded')->filter(fn (AssignmentSubmission $submission) => $submission->score !== null) as $submission) {
            if ($submission->submitted_at) {
                $dates->push(['date' => $submission->submitted_at, 'score' => $this->assignmentPercentage($submission)]);
            }
        }
        $scoresByMonth = $dates->groupBy(fn ($row) => Carbon::parse($row['date'])->format('Y-m'));

        $start = isset($filters['from_date']) ? Carbon::parse($filters['from_date'])->startOfMonth() : now()->subMonths(5)->startOfMonth();
        $end = isset($filters['to_date']) ? Carbon::parse($filters['to_date'])->startOfMonth() : now()->startOfMonth();
        if ($end->lt($start)) {
            $end = $start->copy();
        }
        $months = [];
        for ($month = $start->copy(); $month->lte($end); $month->addMonth()) {
            $bucket = $scoresByMonth->get($month->format('Y-m'), collect())->pluck('score');
            $months[] = [
                'label' => $month->format('M Y'),
                'average' => $bucket->isNotEmpty() ? round($bucket->avg(), 1) : null,
                'submissions' => $bucket->count(),
            ];
        }

        return $months;
    }

    private function gradeDistribution(Collection $scores): array
    {
        $bands = [
            ['label' => 'A · 90–100%', 'min' => 90, 'max' => 100],
            ['label' => 'B · 80–89%', 'min' => 80, 'max' => 90],
            ['label' => 'C · 70–79%', 'min' => 70, 'max' => 80],
            ['label' => 'D · 60–69%', 'min' => 60, 'max' => 70],
            ['label' => 'Below 60%', 'min' => 0, 'max' => 60],
        ];

        return collect($bands)->map(fn ($band) => [
            'label' => $band['label'],
            'count' => $scores->filter(fn ($score) => $score >= $band['min'] && ($band['max'] === 100 ? $score <= 100 : $score < $band['max']))->count(),
        ])->all();
    }

    private function questionPerformance(Collection $gradedAttempts): array
    {
        if ($gradedAttempts->isEmpty()) {
            return ['byQuestion' => [], 'byType' => [], 'highest' => null, 'lowest' => null];
        }

        $questions = DB::table('quiz_answers')
            ->join('quiz_questions', 'quiz_questions.id', '=', 'quiz_answers.quiz_question_id')
            ->join('quizzes', 'quizzes.id', '=', 'quiz_questions.quiz_id')
            ->whereIn('quiz_answers.quiz_attempt_id', $gradedAttempts->pluck('id'))
            ->select(
                'quiz_questions.id',
                'quiz_questions.type',
                'quiz_questions.order',
                'quiz_questions.question_text',
                'quizzes.title as quiz_title',
                DB::raw('AVG(CASE WHEN quiz_answers.is_correct = 1 THEN 100 WHEN quiz_answers.is_correct = 0 THEN 0 ELSE NULL END) as accuracy'),
                DB::raw('SUM(CASE WHEN quiz_answers.is_correct = 1 THEN 1 ELSE 0 END) as correct_responses'),
                DB::raw('SUM(CASE WHEN quiz_answers.is_correct IS NOT NULL THEN 1 ELSE 0 END) as scored_responses'),
                DB::raw('COUNT(quiz_answers.id) as responses')
            )
            ->groupBy('quiz_questions.id', 'quiz_questions.type', 'quiz_questions.order', 'quiz_questions.question_text', 'quizzes.title')
            ->orderBy('quiz_questions.order')
            ->get();

        $byQuestion = $questions->map(fn ($row) => [
            'id' => (int) $row->id,
            'label' => $row->quiz_title.' · Q'.($row->order + 1),
            'question' => $row->question_text,
            'type' => $this->questionTypeLabel($row->type),
            'accuracy' => $row->accuracy !== null ? round((float) $row->accuracy, 1) : null,
            'responses' => (int) $row->responses,
        ]);
        $byType = $questions->groupBy('type')->map(function (Collection $rows, string $type) {
            $weighted = $rows->filter(fn ($row) => $row->accuracy !== null);
            $responseCount = (int) $rows->sum('responses');
            $scoredResponses = (int) $rows->sum('scored_responses');

            return [
                'type' => $this->questionTypeLabel($type),
                'accuracy' => $scoredResponses > 0 ? round(((int) $rows->sum('correct_responses') / $scoredResponses) * 100, 1) : null,
                'responses' => $responseCount,
            ];
        })->filter(fn ($row) => $row['accuracy'] !== null)->values();

        return [
            'byQuestion' => $byQuestion->all(),
            'byType' => $byType->all(),
            'highest' => $byType->sortByDesc('accuracy')->first(),
            'lowest' => $byType->sortBy('accuracy')->first(),
        ];
    }

    private function questionTypeLabel(string $type): string
    {
        return str($type)->replace('_', ' ')->title()->toString();
    }

    private function assignmentPercentage(AssignmentSubmission $submission): ?float
    {
        $max = (float) ($submission->assignment?->max_score ?? 0);

        return $max > 0 && $submission->score !== null
            ? min(100, ((float) $submission->score / $max) * 100)
            : null;
    }

    private function percentage(int $numerator, int $denominator): ?float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 1) : null;
    }
}
