@extends('layouts.teacher')

@section('header', 'Edit Assignment')

@section('content')
<form method="POST" action="{{ route('teacher.assignments.update', $assignment) }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl space-y-4">
    @csrf @method('PUT')
    <div>
        <label class="block text-sm font-medium mb-1">Title</label>
        <input type="text" name="title" value="{{ old('title', $assignment->title) }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full rounded-lg border-slate-300">{{ old('description', $assignment->description) }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Instructions</label>
        <textarea name="instructions" rows="3" class="w-full rounded-lg border-slate-300">{{ old('instructions', $assignment->instructions) }}</textarea>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Deadline</label>
            <input type="datetime-local" name="deadline" value="{{ old('deadline', $assignment->deadline?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Max Score</label>
            <input type="number" name="max_score" value="{{ old('max_score', $assignment->max_score) }}" min="1" required class="w-full rounded-lg border-slate-300">
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_resubmit" value="1" @checked(old('allow_resubmit', $assignment->allow_resubmit))> Allow resubmit</label>
    <div>
        <label class="block text-sm font-medium mb-1">Replace Attachment</label>
        <input type="file" name="attachment" class="w-full text-sm">
        @if($assignment->attachment_path)<p class="text-xs text-slate-500 mt-1">Current file attached</p>@endif
    </div>
    <div class="flex gap-3">
        <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg">Save</button>
        @if($assignment->status->value !== 'published')
        <form method="POST" action="{{ route('teacher.assignments.publish', $assignment) }}">@csrf<button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Publish</button></form>
        @endif
    </div>
</form>

@if($assignment->submissions->count())
<hr class="my-6">
<h3 class="font-semibold mb-3">Submissions</h3>
<div class="space-y-3">
    @foreach($assignment->submissions as $submission)
    <div class="bg-slate-50 rounded-lg p-4 text-sm">
        <p class="font-medium">{{ $submission->student?->full_name }}</p>
        <p class="text-slate-500">Status: {{ $submission->status->value }} @if($submission->score !== null)— Score: {{ $submission->score }}/{{ $assignment->max_score }}@endif</p>
        @if(in_array($submission->status->value, ['submitted', 'late']))
        <form method="POST" action="{{ route('teacher.submissions.grade', $submission) }}" class="mt-3 flex flex-wrap gap-2 items-end">
            @csrf
            <input type="number" name="score" step="0.01" min="0" max="{{ $assignment->max_score }}" placeholder="Score" class="rounded border-slate-300 text-sm w-24" required>
            <input type="text" name="feedback" placeholder="Feedback" class="rounded border-slate-300 text-sm flex-1 min-w-[120px]">
            <input type="hidden" name="status" value="graded">
            <button type="submit" class="px-3 py-1.5 bg-emerald-600 text-white rounded text-sm">Grade</button>
        </form>
        @endif
    </div>
    @endforeach
</div>
@endif
@endsection
