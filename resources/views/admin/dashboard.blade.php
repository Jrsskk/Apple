@extends('layouts.admin')

@section('header', 'Administrator Dashboard')

@section('content')
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    @foreach([
        ['Students', $stats['students'], 'indigo'],
        ['Teachers', $stats['teachers'], 'emerald'],
        ['Classes', $stats['classes'], 'blue'],
        ['Quizzes', $stats['quizzes'], 'purple'],
        ['Assignments', $stats['assignments'], 'orange'],
        ['Submissions', $stats['submissions'], 'pink'],
        ['Active Users', $stats['active_users'], 'teal'],
        ['Subjects', $stats['subjects'], 'cyan'],
    ] as [$label, $value, $color])
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-100">
        <p class="text-sm text-slate-500">{{ $label }}</p>
        <p class="text-2xl font-bold text-{{ $color }}-600 mt-1">{{ $value }}</p>
    </div>
    @endforeach
</div>

<div class="grid lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border">
        <h2 class="font-semibold mb-4">Enrollment Trend</h2>
        <canvas id="enrollmentChart" height="200"></canvas>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border">
        <h2 class="font-semibold mb-4">Completion Rates</h2>
        <canvas id="completionChart" height="200"></canvas>
    </div>
</div>

<div class="grid lg:grid-cols-2 gap-6 mt-6">
    <div class="bg-white rounded-xl shadow-sm p-4 border">
        <h2 class="font-semibold mb-3">Recent Activities</h2>
        <div class="space-y-2 max-h-64 overflow-y-auto">
            @forelse($recent_activities as $log)
            <div class="text-sm border-b pb-2">
                <p class="text-slate-800">{{ $log->description }}</p>
                <p class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</p>
            </div>
            @empty
            <p class="text-slate-500 text-sm">No activity yet.</p>
            @endforelse
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border">
        <h2 class="font-semibold mb-3">Recent Sync Activity</h2>
        <div class="space-y-2 max-h-64 overflow-y-auto">
            @forelse($recent_sync as $log)
            <div class="text-sm border-b pb-2">
                <p class="text-slate-800">{{ $log->message }}</p>
                <p class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }}</p>
            </div>
            @empty
            <p class="text-slate-500 text-sm">No sync activity yet.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script type="module">
import Chart from 'chart.js/auto';
new Chart(document.getElementById('enrollmentChart'), {
    type: 'line',
    data: {
        labels: @json($enrollment_chart['labels']),
        datasets: [{ label: 'Students', data: @json($enrollment_chart['values']), borderColor: '#4f46e5', tension: 0.3 }]
    },
    options: { responsive: true, maintainAspectRatio: false }
});
new Chart(document.getElementById('completionChart'), {
    type: 'bar',
    data: {
        labels: @json($completion_chart['labels']),
        datasets: [{ label: 'Completion %', data: @json($completion_chart['values']), backgroundColor: ['#4f46e5', '#10b981'] }]
    },
    options: { responsive: true, maintainAspectRatio: false, scales: { y: { max: 100 } } }
});
</script>
@endpush
