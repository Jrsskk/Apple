@extends('layouts.teacher')

@section('header', 'Reports')

@section('content')
@php
    $classIds = auth()->user()->taughtClasses()->pluck('id');
    $publishedQuizzes = \App\Models\Quiz::whereIn('school_class_id', $classIds)->where('status', 'published')->count();
    $publishedAssignments = \App\Models\Assignment::whereIn('school_class_id', $classIds)->where('status', 'published')->count();
    $pendingGrading = \App\Models\AssignmentSubmission::whereHas('assignment', fn($q) => $q->whereIn('school_class_id', $classIds))->whereIn('status', ['submitted', 'late'])->count();
@endphp
<div class="grid sm:grid-cols-3 gap-4">
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <p class="text-sm text-slate-500">Active Quizzes</p>
        <p class="text-2xl font-bold text-emerald-600">{{ $publishedQuizzes }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <p class="text-sm text-slate-500">Active Assignments</p>
        <p class="text-2xl font-bold text-emerald-600">{{ $publishedAssignments }}</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <p class="text-sm text-slate-500">Pending Grading</p>
        <p class="text-2xl font-bold text-orange-600">{{ $pendingGrading }}</p>
    </div>
</div>
@endsection
