@extends('layouts.teacher')

@section('header', 'Assignments')

@section('content')
<div class="flex justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $assignments->total() }} assignment(s)</p>
    <a href="{{ route('teacher.assignments.create') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Create Assignment</a>
</div>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Title</th>
                    <th class="p-3 hidden md:table-cell">Class</th>
                    <th class="p-3">Status</th>
                    <th class="p-3 hidden lg:table-cell">Deadline</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assignments as $assignment)
                <tr class="border-t">
                    <td class="p-3 font-medium">{{ $assignment->title }}</td>
                    <td class="p-3 hidden md:table-cell">{{ $assignment->schoolClass?->display_name }}</td>
                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-slate-100 text-xs">{{ $assignment->status->value }}</span></td>
                    <td class="p-3 hidden lg:table-cell">{{ $assignment->deadline?->format('M d, Y') ?? '—' }}</td>
                    <td class="p-3"><a href="{{ route('teacher.assignments.edit', $assignment) }}" class="text-emerald-600">Edit</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-500">No assignments yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $assignments->links() }}</div>
</div>
@endsection
