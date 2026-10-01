@extends('layouts.student')

@section('header', 'Sync Status')

@section('content')
<div id="sync-monitor" class="space-y-4">
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-white rounded-xl p-4 shadow-sm border text-center">
            <p class="text-2xl font-bold text-yellow-600" id="local-pending">—</p>
            <p class="text-xs text-slate-500 mt-1">Local queue</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-sm border text-center">
            <p class="text-2xl font-bold text-indigo-600">{{ $serverPending + $serverFailed }}</p>
            <p class="text-xs text-slate-500 mt-1">Server queue</p>
        </div>
    </div>

    <button type="button" id="sync-now-btn" class="w-full py-3 bg-indigo-600 text-white rounded-xl font-medium">Sync Now</button>

    <section class="bg-white rounded-xl p-4 shadow-sm border">
        <h3 class="font-semibold text-sm mb-3">Downloaded for Offline</h3>
        <div id="downloads-list" class="text-sm text-slate-600 space-y-2">Loading...</div>
    </section>

    <section class="bg-white rounded-xl p-4 shadow-sm border">
        <h3 class="font-semibold text-sm mb-3">Local Sync Queue</h3>
        <div id="queue-list" class="text-sm space-y-2">Loading...</div>
    </section>

    @if($recentLogs->count())
    <section class="bg-white rounded-xl p-4 shadow-sm border">
        <h3 class="font-semibold text-sm mb-3">Recent Server Sync</h3>
        @foreach($recentLogs as $log)
        <div class="text-sm border-b py-2 last:border-0">
            <p>{{ $log->message }}</p>
            <p class="text-xs text-slate-400">{{ $log->created_at->diffForHumans() }} · {{ $log->status }}</p>
        </div>
        @endforeach
    </section>
    @endif
</div>
@endsection

@push('scripts')
<script>
async function refreshSyncMonitor() {
    if (!window.EduSyncOffline) return;
    document.getElementById('local-pending').textContent = await window.EduSyncOffline.getPendingCount();
    const downloads = await window.EduSyncOffline.listDownloads();
    document.getElementById('downloads-list').innerHTML = [
        `<p><strong>${downloads.quizzes.length}</strong> quiz(zes)</p>`,
        `<p><strong>${downloads.assignments.length}</strong> assignment(s)</p>`,
        `<p><strong>${downloads.materials.length}</strong> material(s)</p>`,
    ].join('') || '<p>Nothing downloaded yet.</p>';
    const queue = await window.EduSyncOffline.getSyncQueue();
    const pending = queue.filter(i => i.status === 'pending' || i.status === 'failed');
    document.getElementById('queue-list').innerHTML = pending.length
        ? pending.map(i => `<div class="border rounded-lg p-2"><p class="font-medium">${i.entity_type.replace('_',' ')}</p><p class="text-xs text-slate-500">${i.status} · ${i.created_at?.slice(0,16)}</p></div>`).join('')
        : '<p class="text-slate-500">All synced.</p>';
}
document.getElementById('sync-now-btn')?.addEventListener('click', async () => {
    await window.EduSyncOffline?.processQueue();
    await refreshSyncMonitor();
    alert('Sync complete.');
});
window.addEventListener('edusync:sync-complete', refreshSyncMonitor);
refreshSyncMonitor();
</script>
@endpush
