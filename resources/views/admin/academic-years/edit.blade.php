@extends('layouts.admin')

@section('header', 'Edit Academic Year')

@section('content')
<form method="POST" action="{{ route('admin.academic-years.update', $year) }}" class="bg-white rounded-xl shadow-sm border p-6 max-w-lg space-y-4">
    @csrf @method('PUT')
    <div>
        <label class="block text-sm font-medium mb-1">Name</label>
        <input type="text" name="name" value="{{ old('name', $year->name) }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Start Date</label>
        <input type="date" name="start_date" value="{{ old('start_date', $year->start_date->format('Y-m-d')) }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">End Date</label>
        <input type="date" name="end_date" value="{{ old('end_date', $year->end_date->format('Y-m-d')) }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Status</label>
        <select name="status" class="w-full rounded-lg border-slate-300">
            <option value="open" @selected(old('status', $year->status) === 'open')>Open</option>
            <option value="closed" @selected(old('status', $year->status) === 'closed')>Closed</option>
        </select>
    </div>
    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg">Save</button>
</form>
@endsection
