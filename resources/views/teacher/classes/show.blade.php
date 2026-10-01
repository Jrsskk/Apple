@extends('layouts.teacher')

@section('header', $class->display_name)

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.classes.index') }}" class="text-sm text-slate-600 hover:text-slate-800">← Back to classes</a>
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <h2 class="font-semibold mb-3">Class Details</h2>
        <dl class="text-sm space-y-2">
            <div class="flex justify-between"><dt class="text-slate-500">Subject</dt><dd>{{ $class->subject?->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Grade</dt><dd>{{ $class->grade_level }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Academic Year</dt><dd>{{ $class->academicYear?->name }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Class Code</dt><dd class="font-mono text-emerald-700">{{ $class->class_code }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Schedule</dt><dd>{{ $class->schedule ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Room</dt><dd>{{ $class->room ?? '—' }}</dd></div>
        </dl>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-4">
        <h2 class="font-semibold mb-3">Enrolled Students ({{ $class->students->count() }})</h2>
        <ul class="text-sm space-y-1 mb-4 max-h-48 overflow-y-auto">
            @forelse($class->students as $student)
                <li>{{ $student->full_name }}</li>
            @empty
                <li class="text-slate-500">No students enrolled.</li>
            @endforelse
        </ul>

        <form method="POST" action="{{ route('teacher.classes.enroll', $class) }}" class="space-y-3">
            @csrf
            <label class="block text-sm font-medium">Add Students</label>
            <select name="student_ids[]" multiple class="w-full rounded-lg border-slate-300 h-32 text-sm">
                @foreach($availableStudents as $student)
                    <option value="{{ $student->id }}">{{ $student->full_name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Enroll Selected</button>
        </form>
    </div>
</div>
@endsection
