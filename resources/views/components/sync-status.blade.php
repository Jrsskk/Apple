<div id="sync-status" class="flex items-center gap-1 text-xs" x-data="syncStatus()" x-init="init()">
    <span :class="statusClass" class="inline-block w-2 h-2 rounded-full"></span>
    <span x-text="statusText" class="hidden sm:inline"></span>
    <button @click="syncNow()" x-show="pending > 0" class="ml-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded text-xs">Sync Now</button>
</div>

@push('scripts')
<script>
function syncStatus() {
    return {
        pending: 0,
        online: navigator.onLine,
        statusText: 'Synced',
        statusClass: 'bg-green-500',
        init() {
            window.addEventListener('online', () => { this.online = true; this.check(); });
            window.addEventListener('offline', () => { this.online = false; this.updateDisplay(); });
            window.addEventListener('edusync:sync-complete', () => this.check());
            this.check();
        },
        async check() {
            if (window.EduSyncOffline) {
                this.pending = await window.EduSyncOffline.getPendingCount();
            }
            this.updateDisplay();
        },
        updateDisplay() {
            if (!this.online) {
                this.statusText = this.pending ? `${this.pending} waiting (offline)` : 'Offline';
                this.statusClass = 'bg-yellow-500';
            } else if (this.pending > 0) {
                this.statusText = `${this.pending} waiting to sync`;
                this.statusClass = 'bg-yellow-500';
            } else {
                this.statusText = 'Synced';
                this.statusClass = 'bg-green-500';
            }
        },
        async syncNow() {
            if (window.EduSyncOffline) {
                await window.EduSyncOffline.processQueue();
                await this.check();
            }
        }
    };
}
</script>
@endpush
