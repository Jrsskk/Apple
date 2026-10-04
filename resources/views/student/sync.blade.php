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
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-3">
            <h3 class="font-semibold text-sm">Local Sync Queue</h3>
            <button type="button" id="delete-selected-btn" class="hidden self-start sm:self-auto rounded-lg bg-red-600 px-3 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50">
                Delete selected failed
            </button>
        </div>
        <p id="queue-message" class="hidden mb-3 rounded-lg border px-3 py-2 text-sm" role="status" aria-live="polite"></p>
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
function showQueueMessage(message, isError = false) {
    const element = document.getElementById('queue-message');
    element.textContent = message;
    element.className = `mb-3 rounded-lg border px-3 py-2 text-sm ${isError
        ? 'border-red-200 bg-red-50 text-red-700'
        : 'border-green-200 bg-green-50 text-green-700'}`;
    element.setAttribute('role', isError ? 'alert' : 'status');
}

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
    const queueList = document.getElementById('queue-list');
    const deleteSelectedButton = document.getElementById('delete-selected-btn');
    queueList.replaceChildren();

    const orderedQueue = queue.sort((a, b) => (b.created_at || '').localeCompare(a.created_at || ''));
    const failedCount = orderedQueue.filter(item => item.status === 'failed').length;
    const unfinishedCount = orderedQueue.filter(item => item.status !== 'synced').length;
    deleteSelectedButton.classList.toggle('hidden', failedCount === 0);
    deleteSelectedButton.textContent = `Delete selected failed (${failedCount})`;

    if (!orderedQueue.length) {
        queueList.textContent = 'All synced.';
        queueList.className = 'text-sm space-y-2 text-slate-500';
        return;
    }
    if (unfinishedCount === 0) {
        showQueueMessage('All offline submissions synchronized successfully.', false);
    }

    queueList.className = 'text-sm space-y-2';
    orderedQueue.forEach(sync => {
        const item = document.createElement('article');
        item.className = 'rounded-lg border border-slate-200 p-3';

        const header = document.createElement('div');
        header.className = 'flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between';

        const details = document.createElement('div');
        details.className = 'min-w-0 flex-1';
        const title = document.createElement('p');
        title.className = 'font-medium capitalize';
        title.textContent = (sync.entity_type || 'activity').replaceAll('_', ' ');
        const metadata = document.createElement('p');
        metadata.className = 'mt-1 text-xs text-slate-500';
        const createdAt = sync.created_at ? new Date(sync.created_at) : null;
        metadata.textContent = `Status: ${sync.status} · Retries: ${Number(sync.attempts) || 0} · Date: ${
            createdAt && !Number.isNaN(createdAt.getTime()) ? createdAt.toLocaleString() : 'Unknown'
        }`;
        details.append(title, metadata);
        header.appendChild(details);

        if (sync.status === 'failed') {
            const controls = document.createElement('div');
            controls.className = 'flex shrink-0 items-center gap-3';
            const selectLabel = document.createElement('label');
            selectLabel.className = 'flex min-h-10 items-center gap-2 text-sm text-slate-700';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'h-4 w-4 rounded border-slate-300 text-indigo-600';
            checkbox.dataset.failedSync = sync.sync_uuid;
            const checkboxText = document.createElement('span');
            checkboxText.textContent = 'Select';
            selectLabel.append(checkbox, checkboxText);

            const deleteButton = document.createElement('button');
            deleteButton.type = 'button';
            deleteButton.className = 'min-h-10 rounded-lg border border-red-200 px-3 py-2 font-medium text-red-700 hover:bg-red-50';
            deleteButton.dataset.deleteFailedSync = sync.sync_uuid;
            deleteButton.textContent = 'Delete';
            controls.append(selectLabel, deleteButton);
            header.appendChild(controls);
        }

        item.appendChild(header);
        if (sync.error) {
            const error = document.createElement('p');
            error.className = 'mt-2 break-words text-sm text-red-700';
            error.textContent = sync.error;
            item.appendChild(error);
        }
        queueList.appendChild(item);
    });
}

document.getElementById('queue-list')?.addEventListener('click', async event => {
    const button = event.target.closest('[data-delete-failed-sync]');
    if (!button) return;

    if (!window.confirm('Delete this failed sync record from this device? The submitted data will not be deleted.')) return;

    try {
        const deleted = await window.EduSyncOffline.deleteFailedQueueItem(button.dataset.deleteFailedSync);
        await refreshSyncMonitor();
        showQueueMessage(deleted
            ? 'Failed sync record deleted from this device. Submitted data was not deleted.'
            : 'This record is no longer failed, so it was not deleted.', !deleted);
    } catch (error) {
        showQueueMessage(`Could not delete the failed sync record: ${error.message}`, true);
    }
});

document.getElementById('delete-selected-btn')?.addEventListener('click', async () => {
    const syncUuids = [...document.querySelectorAll('[data-failed-sync]:checked')]
        .map(checkbox => checkbox.dataset.failedSync);
    if (!syncUuids.length) {
        showQueueMessage('Select one or more failed sync records to delete.', true);
        return;
    }
    if (!window.confirm(`Delete ${syncUuids.length} selected failed sync record(s) from this device? The submitted data will not be deleted.`)) return;

    try {
        const deleted = await window.EduSyncOffline.deleteFailedQueueItems(syncUuids);
        await refreshSyncMonitor();
        showQueueMessage(deleted
            ? `${deleted} failed sync record(s) deleted from this device. Submitted data was not deleted.`
            : 'None of the selected records were still failed, so nothing was deleted.', !deleted);
    } catch (error) {
        showQueueMessage(`Could not delete the selected failed sync records: ${error.message}`, true);
    }
});

document.getElementById('sync-now-btn')?.addEventListener('click', async () => {
    const result = await window.EduSyncOffline?.processQueue();
    await refreshSyncMonitor();
    if (result?.failed) {
        showQueueMessage(`${result.failed} submission(s) could not sync. Review the queue details and retry later.`, true);
    } else if (result?.processed) {
        showQueueMessage(`${result.processed} submission(s) synchronized successfully.`, false);
    } else {
        showQueueMessage('No submissions needed synchronization.', false);
    }
});
window.addEventListener('edusync:sync-complete', refreshSyncMonitor);
refreshSyncMonitor();
</script>
@endpush
