@extends('layouts.teacher')

@section('header', 'Create Class')

@section('content')
<div class="mb-4">
    <a href="{{ route('teacher.classes.index') }}" class="text-sm text-slate-600 hover:text-slate-800">← Back to classes</a>
</div>

<form method="POST" action="{{ route('teacher.classes.store') }}" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl space-y-4">
    @csrf

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Name</label>
            <input type="text" name="name" value="{{ old('name') }}" required class="w-full rounded-lg border-slate-300">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Section</label>
            <select name="section" required class="w-full rounded-lg border-slate-300">
                <option value="">Select section</option>
                <option value="A" @selected(old('section') == 'A')>A</option>
                <option value="B" @selected(old('section') == 'B')>B</option>
                <option value="C" @selected(old('section') == 'C')>C</option>
                <option value="D" @selected(old('section') == 'D')>D</option>
            </select>
        </div>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Grade Level</label>
        <select name="grade_level" required class="w-full rounded-lg border-slate-300">
            <option value="">Select grade level</option>
            <option value="Grade 7" @selected(old('grade_level') == 'Grade 7')>Grade 7</option>
            <option value="Grade 8" @selected(old('grade_level') == 'Grade 8')>Grade 8</option>
            <option value="Grade 9" @selected(old('grade_level') == 'Grade 9')>Grade 9</option>
            <option value="Grade 10" @selected(old('grade_level') == 'Grade 10')>Grade 10</option>
            <option value="Grade 11" @selected(old('grade_level') == 'Grade 11')>Grade 11</option>
            <option value="Grade 12" @selected(old('grade_level') == 'Grade 12')>Grade 12</option>
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Subject</label>
        <select name="subject_id" required class="w-full rounded-lg border-slate-300">
            <option value="">Select subject</option>
            @foreach($subjects as $subject)
                <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Academic Year</label>
        <select name="academic_year_id" required class="w-full rounded-lg border-slate-300">
            <option value="">Select academic year</option>
            @foreach($academicYears as $year)
                <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Schedule</label>
            <input type="text" name="schedule" value="{{ old('schedule') }}" class="w-full rounded-lg border-slate-300">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Room</label>
            <select name="room" class="w-full rounded-lg border-slate-300">
                <option value="">Select room</option>
                <option value="Room 1" @selected(old('room') == 'Room 1')>Room 1</option>
                <option value="Room 2" @selected(old('room') == 'Room 2')>Room 2</option>
                <option value="Room 3" @selected(old('room') == 'Room 3')>Room 3</option>
            </select>
        </div>
    </div>

    @if($errors->any())
        <div class="space-y-1">
            @foreach($errors->all() as $error)
                <p class="text-sm text-red-600">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg">Create Class</button>
</form>
@endsection
