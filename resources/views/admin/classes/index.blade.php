@extends('layouts.admin')

@section('header', 'Classes')

@section('content')
<div class="flex justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $classes->total() }} class(es)</p>
    <a href="{{ route('admin.classes.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Add Class</a>
</div>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Class</th>
                    <th class="p-3 hidden md:table-cell">Subject</th>
                    <th class="p-3 hidden lg:table-cell">Teacher</th>
                    <th class="p-3">Students</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($classes as $class)
                <tr class="border-t">
                    <td class="p-3 font-medium">{{ $class->display_name }}</td>
                    <td class="p-3 hidden md:table-cell">{{ $class->subject?->name }}</td>
                    <td class="p-3 hidden lg:table-cell">{{ $class->teacher?->full_name }}</td>
                    <td class="p-3">{{ $class->students_count }}</td>
                    <td class="p-3 flex flex-wrap gap-3">
                        <a href="{{ route('admin.classes.show', $class) }}" class="text-indigo-600">Manage</a>
                        <form method="POST" action="{{ route('admin.classes.destroy', $class) }}" onsubmit="return confirm('Delete this class?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-500">No classes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $classes->links() }}</div>
</div>
@endsection
