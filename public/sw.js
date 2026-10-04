const CACHE_PREFIX = 'edusync-';
const SHELL_CACHE = 'edusync-shell-v3';
const PAGE_CACHE = 'edusync-pages-v3';
const FILE_CACHE = 'edusync-files-v3';
const OFFLINE_URL = '/offline.html';
const DATABASE_NAME = 'edusync-offline';
const DATABASE_VERSION = 6;
const PRECACHE = [
    OFFLINE_URL,
    '/manifest.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(SHELL_CACHE)
            .then((cache) => cache.addAll(PRECACHE))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys
                .filter((key) => key.startsWith(CACHE_PREFIX)
                    && ![SHELL_CACHE, PAGE_CACHE, FILE_CACHE].includes(key))
                .map((key) => caches.delete(key))))
            .then(() => self.clients.claim())
    );
});

function isStudentPage(pathname) {
    return pathname === '/dashboard' || pathname === '/student' || pathname.startsWith('/student/');
}

function isOfflineFile(pathname) {
    return /^\/(?:student\/(?:materials|assignments)\/\d+\/(?:file|attachment)|api\/v1\/(?:materials|assignments)\/\d+\/(?:file|attachment))$/.test(pathname);
}

async function cacheSuccessfulPage(request, response) {
    if (
        response.status !== 200
        || response.redirected
        || !response.headers.get('content-type')?.includes('text/html')
    ) {
        return response;
    }

    const url = new URL(request.url);
    if (!isStudentPage(url.pathname)) return response;
    if (!/<meta\s+name=["']edusync-portal["']\s+content=["']student["']/i.test(await response.clone().text())) {
        return response;
    }

    await (await caches.open(PAGE_CACHE)).put(request, response.clone());
    return response;
}

async function serveStudentPage(request) {
    try {
        return await cacheSuccessfulPage(request, await fetch(request));
    } catch (error) {
        const cache = await caches.open(PAGE_CACHE);
        const cached = await cache.match(request)
            || await cache.match(new URL(request.url).pathname);
        if (cached) return cached;

        const offline = await caches.match(OFFLINE_URL);
        if (offline) return offline;
        throw error;
    }
}

async function serveOfflineFile(request) {
    const cache = await caches.open(FILE_CACHE);
    try {
        const response = await fetch(request);
        if (response.ok && !response.redirected) {
            await cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await cache.match(request);
        if (cached) return cached;
        throw error;
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) return;

    if (isOfflineFile(url.pathname)) {
        event.respondWith(serveOfflineFile(request));
        return;
    }

    if (request.mode === 'navigate' && isStudentPage(url.pathname)) {
        event.respondWith(serveStudentPage(request));
        return;
    }

    if (
        url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname === '/manifest.json'
    ) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then(async (response) => {
                if (response.ok) {
                    await (await caches.open(SHELL_CACHE)).put(request, response.clone());
                }
                return response;
            }))
        );
    }
});

function openDatabase() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DATABASE_NAME, DATABASE_VERSION);
        request.onupgradeneeded = () => {
            const db = request.result;
            if (!db.objectStoreNames.contains('quizzes')) db.createObjectStore('quizzes', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('assignments')) db.createObjectStore('assignments', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('quiz_answers')) db.createObjectStore('quiz_answers', { keyPath: 'key' });
            if (!db.objectStoreNames.contains('sync_queue')) {
                const queue = db.createObjectStore('sync_queue', { keyPath: 'sync_uuid' });
                queue.createIndex('status', 'status');
            }
            if (!db.objectStoreNames.contains('materials')) db.createObjectStore('materials', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('assignment_drafts')) {
                db.createObjectStore('assignment_drafts', { keyPath: 'assignment_id' });
            }
            if (!db.objectStoreNames.contains('offline_attempts')) {
                const attempts = db.createObjectStore('offline_attempts', { keyPath: 'sync_uuid' });
                attempts.createIndex('quiz_id', 'quiz_id');
            } else if (!request.transaction.objectStore('offline_attempts').indexNames.contains('quiz_id')) {
                request.transaction.objectStore('offline_attempts').createIndex('quiz_id', 'quiz_id');
            }
            if (!db.objectStoreNames.contains('classes')) db.createObjectStore('classes', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('subjects')) db.createObjectStore('subjects', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('settings')) db.createObjectStore('settings', { keyPath: 'key' });
            if (!db.objectStoreNames.contains('quiz_catalog')) db.createObjectStore('quiz_catalog', { keyPath: 'id' });
            if (!db.objectStoreNames.contains('assignment_catalog')) {
                db.createObjectStore('assignment_catalog', { keyPath: 'id' });
            }
            if (!db.objectStoreNames.contains('material_catalog')) {
                db.createObjectStore('material_catalog', { keyPath: 'id' });
            }
        };
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function transactionRequest(request) {
    return new Promise((resolve, reject) => {
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

async function claimQueueItem(db, syncUuid) {
    return new Promise((resolve, reject) => {
        const transaction = db.transaction('sync_queue', 'readwrite');
        const store = transaction.objectStore('sync_queue');
        let claimed = null;
        const request = store.get(syncUuid);
        request.onsuccess = () => {
            const item = request.result;
            if (!item || item.status === 'synced') return;
            const now = Date.now();
            if (item.status === 'failed' && item.retryable === false) return;
            if (item.next_retry_at && Date.parse(item.next_retry_at) > now) return;
            if (item.status === 'syncing' && item.sync_started_at
                && now - Date.parse(item.sync_started_at) < 120000) return;
            if (!['pending', 'failed', 'syncing'].includes(item.status)) return;

            claimed = {
                ...item,
                status: 'syncing',
                attempts: (item.attempts || 0) + 1,
                sync_started_at: new Date(now).toISOString(),
            };
            store.put(claimed);
        };
        transaction.oncomplete = () => resolve(claimed);
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });
}

async function updateQueueItem(db, item) {
    const transaction = db.transaction('sync_queue', 'readwrite');
    transaction.objectStore('sync_queue').put(item);
    await new Promise((resolve, reject) => {
        transaction.oncomplete = resolve;
        transaction.onerror = () => reject(transaction.error);
        transaction.onabort = () => reject(transaction.error);
    });

    if (item.entity_type === 'quiz_attempt') {
        const attemptsTransaction = db.transaction('offline_attempts', 'readwrite');
        const store = attemptsTransaction.objectStore('offline_attempts');
        const request = store.get(item.sync_uuid);
        request.onsuccess = () => {
            const attempt = request.result;
            if (attempt) {
                attempt.queue_status = item.status;
                attempt.sync_error = item.error;
                store.put(attempt);
            }
        };
        await new Promise((resolve, reject) => {
            attemptsTransaction.oncomplete = resolve;
            attemptsTransaction.onerror = () => reject(attemptsTransaction.error);
            attemptsTransaction.onabort = () => reject(attemptsTransaction.error);
        });
    }
}

async function postQueueItem(item, csrfToken) {
    const hasAttachment = item.entity_type === 'assignment_submission'
        && item.attachment instanceof Blob;
    const headers = { Accept: 'application/json' };
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;
    let body;
    let endpoint = '/api/v1/sync';

    if (hasAttachment) {
        endpoint = '/api/v1/submissions/assignment';
        body = new FormData();
        body.set('assignment_id', String(item.payload.assignment_id));
        body.set('sync_uuid', item.sync_uuid);
        body.set('device_id', item.device_id || '');
        body.set('text_response', item.payload.text_response || '');
        body.set('status', item.payload.status || 'submitted');
        body.set('version', String(item.payload.version || 1));
        body.set('submitted_at', item.payload.submitted_at);
        body.set('file', item.attachment, item.attachment.name || 'submission');
    } else {
        headers['Content-Type'] = 'application/json';
        body = JSON.stringify({
            sync_uuid: item.sync_uuid,
            entity_type: item.entity_type,
            action: item.action,
            payload: item.payload,
            checksum: item.checksum,
            device_id: item.device_id,
        });
    }

    const response = await fetch(endpoint, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body,
    });
    let json = {};
    try {
        json = await response.json();
    } catch (error) {
        if (response.ok) throw error;
    }

    item.status = response.ok && json.success ? 'synced' : 'failed';
    item.synced_at = item.status === 'synced' ? new Date().toISOString() : null;
    item.error = item.status === 'synced'
        ? null
        : json.message || `Sync failed (${response.status})`;
    item.retryable = item.status !== 'synced'
        && (response.status === 0 || response.status === 202 || response.status === 401
            || response.status === 408 || response.status === 429 || response.status >= 500);
    item.next_retry_at = item.retryable
        ? new Date(Date.now() + Math.min(300, 2 ** Math.min(item.attempts, 8)) * 1000).toISOString()
        : null;
    return item;
}

async function syncQueuedItems() {
    const db = await openDatabase();
    try {
        const context = await transactionRequest(
            db.transaction('settings').objectStore('settings').get('csrf_token')
        );
        const items = await transactionRequest(db.transaction('sync_queue').objectStore('sync_queue').getAll());
        let shouldRetry = false;

        for (const candidate of items.sort((a, b) => a.created_at.localeCompare(b.created_at))) {
            const item = await claimQueueItem(db, candidate.sync_uuid);
            if (!item) continue;
            try {
                const result = await postQueueItem(item, context?.value || '');
                await updateQueueItem(db, result);
                if (result.retryable) shouldRetry = true;
            } catch (error) {
                item.status = 'failed';
                item.error = error instanceof Error ? error.message : String(error);
                item.retryable = true;
                item.next_retry_at = new Date(Date.now() + Math.min(300, 2 ** Math.min(item.attempts, 8)) * 1000).toISOString();
                await updateQueueItem(db, item);
                shouldRetry = true;
            }
        }
        return shouldRetry;
    } finally {
        db.close();
    }
}

self.addEventListener('sync', (event) => {
    if (event.tag !== 'edusync-sync') return;
    event.waitUntil(syncQueuedItems().then((shouldRetry) => {
        if (shouldRetry) throw new Error('Some EduSync submissions need another synchronization attempt.');
    }));
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'EDUSYNC_SYNC_NOW') {
        event.waitUntil(syncQueuedItems());
    }
});
