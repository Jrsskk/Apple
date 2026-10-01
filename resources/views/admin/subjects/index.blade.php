@extends('layouts.admin')

@section('header', 'Subjects')

@section('content')
<div class="flex justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $subjects->total() }} subject(s)</p>
    <a href="{{ route('admin.subjects.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Add Subject</a>
</div>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Name</th>
                    <th class="p-3 hidden md:table-cell">Grade</th>
                    <th class="p-3 hidden lg:table-cell">Teacher</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($subjects as $subject)
                <tr class="border-t">
                    <td class="p-3 font-medium">{{ $subject->name }}</td>
                    <td class="p-3 hidden md:table-cell">{{ $subject->grade_level }}</td>
                    <td class="p-3 hidden lg:table-cell">{{ $subject->teacher?->full_name ?? '—' }}</td>
                    <td class="p-3 flex flex-wrap gap-3">
                        <a href="{{ route('admin.subjects.edit', $subject) }}" class="text-indigo-600">Edit</a>
                        <form method="POST" action="{{ route('admin.subjects.destroy', $subject) }}" onsubmit="return confirm('Delete this subject?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="p-6 text-center text-slate-500">No subjects yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $subjects->links() }}</div>
</div>
@endsection
