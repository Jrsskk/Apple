@extends('layouts.student')

@section('header', $quiz->title)

@section('content')
<div class="bg-white rounded-xl p-4 shadow-sm border mb-4">
    @if($quiz->instructions)
    <p class="text-sm text-slate-600 whitespace-pre-line">{{ $quiz->instructions }}</p>
    @endif
    <dl class="text-sm mt-4 space-y-2">
        <div class="flex justify-between"><dt class="text-slate-500">Duration</dt><dd>{{ $quiz->duration_minutes }} min</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Attempts</dt><dd>{{ $attempts }}/{{ $quiz->max_attempts }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Deadline</dt><dd>{{ $quiz->deadline?->format('M d, Y g:i A') ?? 'None' }}</dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Questions</dt><dd>{{ $quiz->questions()->count() }}</dd></div>
    </dl>
    <p id="download-status" class="text-xs text-emerald-600 mt-3 hidden">✓ Available offline</p>
</div>

@if($attempts < $quiz->max_attempts && $canStart)
<form method="POST" action="{{ route('student.quizzes.start', $quiz) }}">
    @csrf
    <button type="submit" class="w-full py-4 bg-indigo-600 text-white rounded-xl font-semibold text-lg active:scale-[0.98] transition">Take Quiz</button>
</form>
@elseif($attempts >= $quiz->max_attempts)
<p class="text-center text-slate-500 text-sm">Maximum attempts reached.</p>
@else
<p class="text-center text-slate-500 text-sm">The quiz deadline has passed.</p>
@endif

<div class="grid grid-cols-2 gap-3 mt-4">
    <button type="button" id="download-quiz" class="py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-medium">Download Offline</button>
    <a href="{{ route('student.quizzes.offline', $quiz) }}" id="offline-take-link" class="hidden py-3 bg-emerald-600 text-white rounded-xl text-sm font-medium text-center">Take Offline</a>
</div>
@endsection

@push('scripts')
<script>
(async function() {
    const quizId = {{ $quiz->id }};
    const statusEl = document.getElementById('download-status');
    const offlineLink = document.getElementById('offline-take-link');
    const offline = await window.EduSyncOfflineReady;

    if (offline && await offline.isQuizDownloaded(quizId)) {
        statusEl.classList.remove('hidden');
        offlineLink.classList.remove('hidden');
    }

    document.getElementById('download-quiz')?.addEventListener('click', async () => {
        if (!offline) return alert('Offline module unavailable. Reload this page while online.');
        try {
            const json = await offline.downloadQuiz(quizId);
            if (json.success) {
                statusEl.classList.remove('hidden');
                offlineLink.classList.remove('hidden');
                alert('Quiz saved for offline use.');
            } else {
                alert('Download failed.');
            }
        } catch { alert('Download failed. Check your connection.'); }
    });
})();
</script>
@endpush
