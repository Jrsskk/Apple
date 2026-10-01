@extends('layouts.admin')

@section('header', 'Create Academic Year')

@section('content')
<form method="POST" action="{{ route('admin.academic-years.store') }}" class="bg-white rounded-xl shadow-sm border p-6 max-w-lg space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium mb-1">Name</label>
        <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. SY 2025-2026" class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Start Date</label>
        <input type="date" name="start_date" value="{{ old('start_date') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">End Date</label>
        <input type="date" name="end_date" value="{{ old('end_date') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg">Create</button>
</form>
@endsection
