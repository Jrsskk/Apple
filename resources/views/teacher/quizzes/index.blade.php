@extends('layouts.teacher')

@section('header', 'Quizzes')

@section('content')
<div class="flex justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $quizzes->total() }} quiz(zes)</p>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('teacher.quizzes.create') }}" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Create Quiz</a>
        <a href="{{ route('teacher.quizzes.create') }}#pdf" class="px-4 py-2 bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-sm font-medium">Build from PDF</a>
    </div>
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
                @forelse($quizzes as $quiz)
                <tr class="border-t">
                    <td class="p-3 font-medium">{{ $quiz->title }}</td>
                    <td class="p-3 hidden md:table-cell">{{ $quiz->schoolClass?->display_name }}</td>
                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-slate-100 text-xs">{{ $quiz->status->value }}</span></td>
                    <td class="p-3 hidden lg:table-cell">{{ $quiz->deadline?->format('M d, Y') ?? '—' }}</td>
                    <td class="p-3"><a href="{{ route('teacher.quizzes.edit', $quiz) }}" class="text-emerald-600">Edit</a></td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-500">No quizzes yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $quizzes->links() }}</div>
</div>
@endsection
