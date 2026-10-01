@extends('layouts.student')

@section('header', 'Quiz Result')

@section('content')
<div class="bg-white rounded-xl p-6 shadow-sm border text-center">
    <p class="text-slate-500 text-sm">Your Score</p>
    <p class="text-4xl font-bold text-indigo-600 mt-2">{{ $attempt->score ?? 0 }}/{{ $attempt->total_points ?? $quiz->total_points }}</p>
    @if($attempt->percentage !== null)
    <p class="text-lg mt-1 {{ $attempt->passed ? 'text-green-600' : 'text-orange-600' }}">{{ round($attempt->percentage, 1) }}% {{ $attempt->passed ? '· Passed' : '· Review needed' }}</p>
    @endif
    <p class="text-sm text-slate-500 mt-4">{{ $attempt->correct_count ?? 0 }} correct · {{ $attempt->incorrect_count ?? 0 }} incorrect</p>
</div>

@if($quiz->show_results && $quiz->allow_review && $attempt->answers->count())
<section class="mt-6">
    <h3 class="font-semibold text-slate-700 mb-2">Review</h3>
    @foreach($attempt->answers as $answer)
    <div class="bg-white rounded-xl p-4 shadow-sm border mb-2 text-sm">
        <p class="font-medium">{{ $answer->question?->question_text }}</p>
        <p class="mt-1 {{ $answer->is_correct ? 'text-green-600' : 'text-red-600' }}">
            {{ $answer->is_correct ? 'Correct' : 'Incorrect' }} · {{ $answer->points_earned }} pt(s)
        </p>
    </div>
    @endforeach
</section>
@endif

<a href="{{ route('dashboard') }}" class="block w-full py-3 mt-6 bg-slate-100 text-slate-700 rounded-xl text-center font-medium">Back to Home</a>
@endsection
