@extends('layouts.teacher')

@section('header', 'Teacher Dashboard')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
    @foreach([
        ['Students', $stats['students']],
        ['Active Quizzes', $stats['active_quizzes']],
        ['Assignments', $stats['active_assignments']],
        ['Pending Submissions', $stats['pending_submissions']],
        ['Pending Grading', $stats['pending_grading']],
    ] as [$label, $value])
    <div class="bg-white rounded-xl shadow-sm p-4 border">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="text-2xl font-bold text-emerald-600 mt-1">{{ $value }}</p>
    </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border">
        <h2 class="font-semibold mb-3">My Classes</h2>
        <div class="space-y-2">
            @foreach($classes as $class)
            <div class="flex justify-between items-center p-3 bg-slate-50 rounded-lg">
                <div>
                    <p class="font-medium">{{ $class->display_name }}</p>
                    <p class="text-xs text-slate-500">{{ $class->subject->name ?? '' }}</p>
                </div>
                <span class="text-sm text-emerald-600 font-medium">{{ $class->students_count }} students</span>
            </div>
            @endforeach
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border">
        <h2 class="font-semibold mb-3">Upcoming Deadlines</h2>
        @forelse($upcoming_deadlines as $item)
        <div class="p-3 border-b text-sm">
            <p class="font-medium">{{ $item->title }}</p>
            <p class="text-slate-500">{{ $item->deadline?->format('M d, Y') }}</p>
        </div>
        @empty
        <p class="text-slate-500 text-sm">No upcoming deadlines.</p>
        @endforelse
    </div>
</div>
@endsection
