@extends('layouts.admin')

@section('header', 'Create Subject')

@section('content')
<form method="POST" action="{{ route('admin.subjects.store') }}" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium mb-1">Name</label>
        <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div class="grid md:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Grade Level</label>
            <select name="grade_level" required class="w-full rounded-lg border-slate-300">
                <option value="">Select grade level</option>
                @foreach(['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'] as $grade)
                    <option value="{{ $grade }}" @selected(old('grade_level') === $grade)>{{ $grade }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Academic Year</label>
            <select name="academic_year_id" required class="w-full rounded-lg border-slate-300">
                @foreach($years as $year)
                <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Teacher</label>
        <select name="teacher_id" class="w-full rounded-lg border-slate-300">
            <option value="">— None —</option>
            @foreach($teachers as $teacher)
            <option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>{{ $teacher->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full rounded-lg border-slate-300">{{ old('description') }}</textarea>
    </div>
    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg">Create Subject</button>
</form>
@endsection
