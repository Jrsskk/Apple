@extends('layouts.student')

@section('header', 'Assignments')

@section('content')
<div class="space-y-3">
    @forelse($assignments as $assignment)
    <a href="{{ route('student.assignments.show', $assignment) }}" class="block bg-white rounded-xl p-4 shadow-sm border active:scale-[0.98] transition">
        <p class="font-medium text-slate-800">{{ $assignment->title }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ $assignment->schoolClass?->display_name }}</p>
        <p class="text-xs text-indigo-600 mt-1">Due {{ $assignment->deadline?->format('M d, Y') ?? 'No deadline' }}</p>
    </a>
    @empty
    <p class="text-slate-500 text-sm">No assignments available.</p>
    @endforelse
</div>
@endsection
