@extends('layouts.teacher')

@section('header', 'Grades')

@section('content')
@php
    $classIds = auth()->user()->taughtClasses()->pluck('id');
    $quizAttempts = \App\Models\QuizAttempt::whereHas('quiz', fn($q) => $q->whereIn('school_class_id', $classIds))->where('status', 'graded')->with(['student', 'quiz'])->latest()->take(20)->get();
    $submissions = \App\Models\AssignmentSubmission::whereHas('assignment', fn($q) => $q->whereIn('school_class_id', $classIds))->where('status', 'graded')->with(['student', 'assignment'])->latest()->take(20)->get();
@endphp
<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <h2 class="font-semibold mb-3">Quiz Grades</h2>
        @forelse($quizAttempts as $attempt)
        <div class="py-2 border-b text-sm flex justify-between">
            <span>{{ $attempt->student?->full_name }} — {{ $attempt->quiz?->title }}</span>
            <span class="font-medium">{{ $attempt->score }}/{{ $attempt->total_points }}</span>
        </div>
        @empty
        <p class="text-slate-500 text-sm">No graded quizzes.</p>
        @endforelse
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <h2 class="font-semibold mb-3">Assignment Grades</h2>
        @forelse($submissions as $sub)
        <div class="py-2 border-b text-sm flex justify-between">
            <span>{{ $sub->student?->full_name }} — {{ $sub->assignment?->title }}</span>
            <span class="font-medium">{{ $sub->score }}/{{ $sub->assignment?->max_score }}</span>
        </div>
        @empty
        <p class="text-slate-500 text-sm">No graded assignments.</p>
        @endforelse
    </div>
</div>
@endsection
