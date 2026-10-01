@extends('layouts.student')

@section('header', 'My Tasks')

@section('content')
<p class="text-sm text-slate-500 mb-4">Pending: <span class="font-semibold text-indigo-600">{{ $pending_count }}</span></p>

<section class="mb-6">
    <h3 class="font-semibold text-slate-700 mb-2">Upcoming Quizzes</h3>
    @forelse($upcoming_quizzes as $quiz)
    <a href="{{ route('student.quizzes.show', $quiz) }}" class="block bg-white rounded-xl p-4 shadow-sm border mb-2">
        <p class="font-medium">{{ $quiz->title }}</p>
        <p class="text-xs text-slate-500 mt-1">Due {{ $quiz->deadline?->format('M d, Y g:i A') ?? 'No deadline' }}</p>
    </a>
    @empty
    <p class="text-sm text-slate-500">No upcoming quizzes.</p>
    @endforelse
</section>

<section>
    <h3 class="font-semibold text-slate-700 mb-2">Upcoming Assignments</h3>
    @forelse($upcoming_assignments as $assignment)
    <a href="{{ route('student.assignments.show', $assignment) }}" class="block bg-white rounded-xl p-4 shadow-sm border mb-2">
        <p class="font-medium">{{ $assignment->title }}</p>
        <p class="text-xs text-slate-500 mt-1">Due {{ $assignment->deadline?->format('M d, Y g:i A') ?? 'No deadline' }}</p>
    </a>
    @empty
    <p class="text-sm text-slate-500">No upcoming assignments.</p>
    @endforelse
</section>
@endsection
