@extends('layouts.student')

@section('header', 'Offline')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm border text-center">
    <div class="text-4xl mb-3">📡</div>
    <h2 class="text-lg font-bold text-slate-800">You're offline</h2>
    <p class="text-sm text-slate-500 mt-2">Downloaded quizzes and assignments are still available. Your submissions will sync when you're back online.</p>
    <a href="{{ route('student.sync.index') }}" class="inline-block mt-4 px-4 py-2 bg-indigo-600 text-white rounded-xl text-sm">View Sync Status</a>
</div>
@endsection
