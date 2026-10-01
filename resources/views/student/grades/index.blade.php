@extends('layouts.student')

@section('header', 'My Grades')

@section('content')

@php
    $subjectGrades = collect();

    foreach ($quizGrades as $attempt) {
        $subject = $attempt->quiz?->subject;

        if (! $subject) {
            continue;
        }

        $key = (string) $subject->id;

        if (! $subjectGrades->has($key)) {
            $subjectGrades->put($key, [
                'subject' => $subject,
                'activities' => collect()
            ]);
        }

        $attemptStatus = $attempt->status instanceof \BackedEnum
            ? $attempt->status->value
            : (string) ($attempt->status ?? '');

        $subjectGrades->get($key)['activities']->push([
            'type' => 'Quiz',
            'title' => $attempt->quiz->title,
            'score' => $attempt->score,
            'total' => $attempt->total_points ?? $attempt->quiz->total_points,
            'date' => $attempt->submitted_at,
            'status' => $attemptStatus,
            'feedback' => null,
            'href' => route(
                'student.quizzes.result',
                [$attempt->quiz, $attempt]
            ),
        ]);
    }

    foreach ($assignmentGrades as $submission) {
        $subject = $submission->assignment?->subject;

        if (! $subject) {
            continue;
        }

        $key = (string) $subject->id;

        if (! $subjectGrades->has($key)) {
            $subjectGrades->put($key, [
                'subject' => $subject,
                'activities' => collect()
            ]);
        }

        $submissionStatus = $submission->status instanceof \BackedEnum
            ? $submission->status->value
            : (string) ($submission->status ?? '');

        $subjectGrades->get($key)['activities']->push([
            'type' => 'Assignment',
            'title' => $submission->assignment->title,
            'score' => $submission->score,
            'total' => $submission->assignment->max_score,
            'date' => $submission->submitted_at,
            'status' => $submissionStatus,
            'feedback' => $submission->feedback,
            'href' => null,
        ]);
    }

    $subjectGrades = $subjectGrades->sortBy(
        fn ($group) => mb_strtolower($group['subject']->name)
    );
@endphp


{{-- Page Introduction --}}
<div class="mb-6">

    <div class="flex items-start justify-between gap-3">

        <div>
            <h2 class="text-xl font-bold text-slate-900">
                My Grades
            </h2>

            <p class="mt-1 text-sm text-slate-500">
                View your quiz and assignment performance by subject.
            </p>
        </div>

        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
            <svg
                class="h-6 w-6"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.8"
                    d="M5 20V10m7 10V4m7 16v-7"
                />
            </svg>
        </div>

    </div>

</div>


@forelse($subjectGrades as $group)

    @php
        $activities = $group['activities']->sortByDesc(
            fn ($activity) => $activity['date']?->getTimestamp() ?? 0
        );

        $gradedActivities = $activities->filter(
            fn ($activity) =>
                $activity['score'] !== null &&
                (float) $activity['total'] > 0
        );

        $earnedScore = $gradedActivities->sum(
            fn ($activity) => (float) $activity['score']
        );

        $possibleScore = $gradedActivities->sum(
            fn ($activity) => (float) $activity['total']
        );

        $performance = $possibleScore > 0
            ? round(($earnedScore / $possibleScore) * 100, 1)
            : null;

        $performanceColor = match (true) {
            $performance === null => 'text-slate-500 bg-slate-100',
            $performance >= 90 => 'text-emerald-700 bg-emerald-50',
            $performance >= 75 => 'text-indigo-700 bg-indigo-50',
            $performance >= 60 => 'text-amber-700 bg-amber-50',
            default => 'text-red-700 bg-red-50',
        };
    @endphp


    {{-- Subject Card --}}
    <section class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        {{-- Subject Header --}}
        <div class="border-b border-slate-100 p-4 sm:p-5">

            <div class="flex items-start justify-between gap-3">

                <div class="flex min-w-0 items-center gap-3">

                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">

                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20M4 19.5A2.5 2.5 0 0 0 6.5 22H20V4H6.5A2.5 2.5 0 0 0 4 6.5v13Z"
                            />
                        </svg>

                    </div>

                    <div class="min-w-0">

                        <h2 class="truncate text-base font-bold text-slate-900">
                            {{ $group['subject']->name }}
                        </h2>

                        <p class="mt-0.5 text-xs text-slate-500">
                            {{ $activities->count() }}
                            {{ \Illuminate\Support\Str::plural('activity', $activities->count()) }}
                        </p>

                    </div>

                </div>


                {{-- Performance --}}
                <div class="shrink-0 text-right">

                    @if($performance !== null)

                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold {{ $performanceColor }}">
                            {{ $performance }}%
                        </span>

                        <p class="mt-1 text-[10px] text-slate-400">
                            Performance
                        </p>

                    @else

                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500">
                            Not graded
                        </span>

                    @endif

                </div>

            </div>


            @if($performance !== null)

                {{-- Progress Bar --}}
                <div class="mt-4">

                    <div class="mb-1.5 flex justify-between text-[11px] text-slate-500">
                        <span>Overall performance</span>
                        <span>
                            {{ $earnedScore }}/{{ $possibleScore }}
                        </span>
                    </div>

                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">

                        <div
                            class="h-full rounded-full bg-indigo-600 transition-all"
                            style="width: {{ min($performance, 100) }}%"
                        ></div>

                    </div>

                </div>

            @endif

        </div>


        {{-- MOBILE ACTIVITY CARDS --}}
        <div class="divide-y divide-slate-100 sm:hidden">

            @foreach($activities as $activity)

                @php
                    $isQuiz = $activity['type'] === 'Quiz';

                    $status = strtolower($activity['status']);

                    $statusClass = match ($status) {
                        'completed',
                        'graded',
                        'submitted' => 'bg-emerald-50 text-emerald-700',

                        'pending',
                        'processing' => 'bg-amber-50 text-amber-700',

                        'failed',
                        'late' => 'bg-red-50 text-red-700',

                        default => 'bg-slate-100 text-slate-600',
                    };
                @endphp

                <div class="p-4">

                    <div class="flex gap-3">

                        {{-- Activity Icon --}}
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl
                            {{ $isQuiz
                                ? 'bg-indigo-50 text-indigo-600'
                                : 'bg-violet-50 text-violet-600'
                            }}">

                            @if($isQuiz)

                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M9 5h6M7 8h10M7 12h10M7 16h6"
                                    />
                                </svg>

                            @else

                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 4h4m-4 4h4m-4 4h2"
                                    />
                                </svg>

                            @endif

                        </div>


                        {{-- Activity Information --}}
                        <div class="min-w-0 flex-1">

                            @if($activity['href'])

                                <a
                                    href="{{ $activity['href'] }}"
                                    class="block truncate text-sm font-semibold text-slate-800 hover:text-indigo-600"
                                >
                                    {{ $activity['title'] }}
                                </a>

                            @else

                                <p class="truncate text-sm font-semibold text-slate-800">
                                    {{ $activity['title'] }}
                                </p>

                            @endif

                            <div class="mt-1 flex flex-wrap items-center gap-2">

                                <span class="text-xs text-slate-400">
                                    {{ $activity['type'] }}
                                </span>

                                @if($activity['date'])

                                    <span class="text-slate-300">•</span>

                                    <span class="text-xs text-slate-400">
                                        {{ $activity['date']->format('M d, Y') }}
                                    </span>

                                @endif

                            </div>

                            @if($activity['feedback'])

                                <div class="mt-2 rounded-lg bg-slate-50 p-2.5 text-xs leading-relaxed text-slate-600">
                                    {{ $activity['feedback'] }}
                                </div>

                            @endif

                        </div>


                        {{-- Score --}}
                        <div class="shrink-0 text-right">

                            @if($activity['score'] !== null)

                                <p class="text-sm font-bold text-indigo-600">
                                    {{ $activity['score'] }}/{{ $activity['total'] ?? '—' }}
                                </p>

                            @else

                                <p class="text-xs font-medium text-slate-400">
                                    Pending
                                </p>

                            @endif

                            @if($activity['status'])

                                <span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium {{ $statusClass }}">
                                    {{ ucfirst($activity['status']) }}
                                </span>

                            @endif

                        </div>

                    </div>

                </div>

            @endforeach

        </div>


        {{-- DESKTOP TABLE --}}
        <div class="hidden overflow-x-auto sm:block">

            <table class="w-full text-left text-sm">

                <thead class="border-b border-slate-100 bg-slate-50 text-xs uppercase tracking-wide text-slate-500">

                    <tr>
                        <th class="px-5 py-3 font-semibold">Activity</th>
                        <th class="px-5 py-3 font-semibold">Type</th>
                        <th class="px-5 py-3 font-semibold">Score</th>
                        <th class="px-5 py-3 font-semibold">Date</th>
                        <th class="px-5 py-3 font-semibold">Status</th>
                    </tr>

                </thead>

                <tbody class="divide-y divide-slate-100">

                    @foreach($activities as $activity)

                        <tr class="transition hover:bg-slate-50">

                            <td class="px-5 py-4">

                                @if($activity['href'])

                                    <a
                                        href="{{ $activity['href'] }}"
                                        class="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                                    >
                                        {{ $activity['title'] }}
                                    </a>

                                @else

                                    <span class="font-semibold text-slate-800">
                                        {{ $activity['title'] }}
                                    </span>

                                @endif

                                @if($activity['feedback'])

                                    <p class="mt-1 max-w-md rounded-lg bg-slate-50 p-2 text-xs text-slate-600">
                                        {{ $activity['feedback'] }}
                                    </p>

                                @endif

                            </td>

                            <td class="px-5 py-4">

                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                                    {{ $activity['type'] }}
                                </span>

                            </td>

                            <td class="px-5 py-4">

                                @if($activity['score'] !== null)

                                    <span class="font-bold text-indigo-600">
                                        {{ $activity['score'] }}/{{ $activity['total'] ?? '—' }}
                                    </span>

                                @else

                                    <span class="text-slate-400">
                                        Pending
                                    </span>

                                @endif

                            </td>

                            <td class="whitespace-nowrap px-5 py-4 text-slate-500">
                                {{ $activity['date']?->format('M d, Y') ?? '—' }}
                            </td>

                            <td class="px-5 py-4">

                                @if($activity['status'])

                                    <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClass }}">
                                        {{ ucfirst($activity['status']) }}
                                    </span>

                                @else

                                    <span class="text-slate-400">
                                        —
                                    </span>

                                @endif

                            </td>

                        </tr>

                    @endforeach

                </tbody>

            </table>

        </div>

    </section>

@empty

    {{-- Empty State --}}
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center shadow-sm">

        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">

            <svg
                class="h-8 w-8"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="1.7"
                    d="M9 17v-2m3 2v-4m3 4v-6M5 20h14a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1Z"
                />
            </svg>

        </div>

        <h3 class="mt-4 font-bold text-slate-800">
            No grades yet
        </h3>

        <p class="mx-auto mt-1 max-w-sm text-sm leading-relaxed text-slate-500">
            Your submitted quizzes and assignments will appear here,
            organized by subject.
        </p>

    </div>

@endforelse

@endsection