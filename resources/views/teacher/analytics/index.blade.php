@extends('layouts.teacher')

@section('header', 'Analytics')

@section('content')
@php
    $classIds = auth()->user()->taughtClasses()->pluck('id');
    $totalStudents = \App\Models\SchoolClass::whereIn('id', $classIds)->withCount('students')->get()->sum('students_count');
    $quizAttempts = \App\Models\QuizAttempt::whereHas('quiz', fn($q) => $q->whereIn('school_class_id', $classIds))->count();
    $avgScore = \App\Models\QuizAttempt::whereHas('quiz', fn($q) => $q->whereIn('school_class_id', $classIds))->where('status', 'graded')->avg('percentage');
@endphp
<div class="grid sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <p class="text-sm text-slate-500">Students</p>
        <p class="text-2xl font-bold text-emerald-600">{{ $totalStudents }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <p class="text-sm text-slate-500">Quiz Attempts</p>
        <p class="text-2xl font-bold text-emerald-600">{{ $quizAttempts }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <p class="text-sm text-slate-500">Avg Quiz Score</p>
        <p class="text-2xl font-bold text-emerald-600">{{ $avgScore ? round($avgScore, 1).'%' : '—' }}</p>
    </div>
</div>
@endsection
