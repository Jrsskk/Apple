@extends('layouts.admin')

@section('header', 'Reports')

@section('content')
<div class="grid md:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold mb-2">Enrollment Summary</h2>
        <p class="text-3xl font-bold text-indigo-600">{{ \App\Models\User::where('role', 'student')->count() }}</p>
        <p class="text-sm text-slate-500">Total students</p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h2 class="font-semibold mb-2">Activity Completion</h2>
        @php
            $quizDone = \App\Models\QuizAttempt::where('status', 'graded')->count();
            $quizTotal = max(\App\Models\QuizAttempt::count(), 1);
            $assignDone = \App\Models\AssignmentSubmission::whereIn('status', ['graded', 'submitted'])->count();
            $assignTotal = max(\App\Models\AssignmentSubmission::count(), 1);
        @endphp
        <p class="text-sm">Quizzes: {{ round(($quizDone / $quizTotal) * 100) }}% graded</p>
        <p class="text-sm mt-1">Assignments: {{ round(($assignDone / $assignTotal) * 100) }}% submitted/graded</p>
    </div>
</div>
@endsection
