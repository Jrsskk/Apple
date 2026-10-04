import { openDB } from 'idb';

const DB_NAME = 'edusync-offline';
const DB_VERSION = 6;
const CACHE_NAME = 'edusync-pages-v3';
const FILE_CACHE_NAME = 'edusync-files-v3';

async function getDb() {
    return openDB(DB_NAME, DB_VERSION, {
        upgrade(db, oldVersion, _newVersion, transaction) {
            if (oldVersion < 1) {
                db.createObjectStore('quizzes', { keyPath: 'id' });
                db.createObjectStore('assignments', { keyPath: 'id' });
                db.createObjectStore('quiz_answers', { keyPath: 'key' });
                const queue = db.createObjectStore('sync_queue', { keyPath: 'sync_uuid' });
                queue.createIndex('status', 'status');
            }
            if (oldVersion < 2) {
                if (!db.objectStoreNames.contains('materials')) {
                    db.createObjectStore('materials', { keyPath: 'id' });
                }
                if (!db.objectStoreNames.contains('assignment_drafts')) {
                    db.createObjectStore('assignment_drafts', { keyPath: 'assignment_id' });
                }
                if (!db.objectStoreNames.contains('offline_attempts')) {
                    db.createObjectStore('offline_attempts', { keyPath: 'sync_uuid' });
                }
            }
            if (oldVersion < 3) {
                const attempts = transaction.objectStore('offline_attempts');
                if (!attempts.indexNames.contains('quiz_id')) {
                    attempts.createIndex('quiz_id', 'quiz_id');
                }
            }
            if (oldVersion < 4) {
                db.createObjectStore('classes', { keyPath: 'id' });
                db.createObjectStore('subjects', { keyPath: 'id' });
                db.createObjectStore('settings', { keyPath: 'key' });
            }
            if (oldVersion < 5) {
                db.createObjectStore('quiz_catalog', { keyPath: 'id' });
            }
            if (oldVersion < 6) {
                db.createObjectStore('assignment_catalog', { keyPath: 'id' });
                db.createObjectStore('material_catalog', { keyPath: 'id' });
            }
        },
    });
}

function getDeviceId() {
    let id = localStorage.getItem('edusync_device_id');
    if (!id) {
        id = crypto.randomUUID();
        localStorage.setItem('edusync_device_id', id);
    }
    return id;
}

function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

let queueProcessing;
let retryTimer;

const EduSyncOffline = {
    async downloadQuiz(quizId) {
        const res = await fetch(`/student/quizzes/${quizId}/download`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (json.success) {
            const db = await getDb();
            const data = {
                ...json.data,
                id: json.data.quiz?.id ?? json.data.id ?? quizId,
                downloaded_at: new Date().toISOString(),
            };
            await db.put('quizzes', data);

            const offlinePageUrl = `/student/quizzes/${quizId}/offline`;
            const offlinePage = await fetch(offlinePageUrl, { credentials: 'same-origin' });
            if (!offlinePage.ok || offlinePage.redirected) {
                throw new Error('Could not prepare the offline quiz page.');
            }
            const cache = await caches.open(CACHE_NAME);
            await cache.put(offlinePageUrl, offlinePage);
        }
        return json;
    },

    async cacheStudentData() {
        const responses = await Promise.all([
            fetch('/api/v1/classes', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
            fetch('/api/v1/assignments', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
            fetch('/api/v1/quizzes', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
            fetch('/api/v1/materials', { headers: { Accept: 'application/json' }, credentials: 'same-origin' }),
        ]);
        const payloads = await Promise.all(responses.map(async (response) => {
            if (!response.ok) throw new Error(`Student data cache request failed (${response.status}).`);
            const json = await response.json();
            if (!json.success || !Array.isArray(json.data)) {
                throw new Error('Student data cache returned an unexpected response.');
            }
            return json.data;
        }));

        const [classes, assignments, quizzes, materials] = payloads;
        const db = await getDb();
        const transaction = db.transaction(
            ['classes', 'subjects', 'assignment_catalog', 'quiz_catalog', 'material_catalog'],
            'readwrite',
        );
        for (const schoolClass of classes) {
            transaction.objectStore('classes').put(schoolClass);
            if (schoolClass.subject) transaction.objectStore('subjects').put(schoolClass.subject);
        }
        for (const assignment of assignments) transaction.objectStore('assignment_catalog').put(assignment);
        for (const quiz of quizzes) transaction.objectStore('quiz_catalog').put(quiz);
        for (const material of materials) transaction.objectStore('material_catalog').put(material);
        await transaction.done;
        return { classes, assignments, quizzes, materials };
    },

    async downloadAssignment(assignmentId) {
        const res = await fetch(`/student/assignments/${assignmentId}/download`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (json.success) {
            const db = await getDb();
            const assignment = json.data.assignment ?? json.data;
            await db.put('assignments', {
                ...assignment,
                id: assignment.id,
                downloaded_at: json.data.downloaded_at ?? new Date().toISOString(),
            });
            const pageUrl = `/student/assignments/${assignmentId}`;
            const page = await fetch(pageUrl, { credentials: 'same-origin' });
            if (!page.ok || page.redirected) throw new Error('Could not prepare the offline assignment page.');
            await (await caches.open(CACHE_NAME)).put(pageUrl, page);
        }
        return json;
    },

    async downloadMaterial(materialId) {
        const res = await fetch(`/student/materials/${materialId}/download`, {
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': getCsrfToken() },
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (json.success) {
            const db = await getDb();
            const material = json.data.material ?? json.data;
            await db.put('materials', {
                ...material,
                id: material.id ?? materialId,
                view_url: json.data.view_url,
                download_url: json.data.download_url,
                downloaded_at: new Date().toISOString(),
            });
            const fileResponse = await fetch(`/student/materials/${materialId}/file`, {
                credentials: 'same-origin',
            });
            if (!fileResponse.ok || fileResponse.redirected) {
                throw new Error(`Could not download learning material (${fileResponse.status}).`);
            }
            await (await caches.open(FILE_CACHE_NAME)).put(fileResponse.url, fileResponse.clone());
        }
        return json;
    },

    async getQuiz(quizId) {
        const db = await getDb();
        return db.get('quizzes', Number(quizId));
    },

    async getAssignment(assignmentId) {
        const db = await getDb();
        return db.get('assignments', Number(assignmentId));
    },

    async getMaterial(materialId) {
        const db = await getDb();
        return db.get('materials', Number(materialId));
    },

    async getClass(classId) {
        const db = await getDb();
        return db.get('classes', Number(classId));
    },

    async getSubjects() {
        const db = await getDb();
        return db.getAll('subjects');
    },

    async listDownloads() {
        const db = await getDb();
        const [quizzes, assignments, materials] = await Promise.all([
            db.getAll('quizzes'),
            db.getAll('assignments'),
            db.getAll('materials'),
        ]);
        return { quizzes, assignments, materials };
    },

    async isQuizDownloaded(quizId) {
        const quiz = await this.getQuiz(quizId);
        return Array.isArray(quiz?.questions) && quiz.questions.length > 0;
    },

    async isAssignmentDownloaded(assignmentId) {
        const item = await this.getAssignment(assignmentId);
        return !!item?.downloaded_at;
    },

    async saveQuizAnswer(attemptKey, questionId, data) {
        const db = await getDb();
        await db.put('quiz_answers', {
            key: `${attemptKey}_${questionId}`,
            attemptKey,
            questionId,
            ...data,
            savedAt: new Date().toISOString(),
        });
    },

    async getQuizAnswers(attemptKey) {
        const db = await getDb();
        const all = await db.getAll('quiz_answers');
        return all.filter((a) => a.attemptKey === attemptKey);
    },

    async createOfflineQuizAttempt(quizId) {
        const quiz = await this.getQuiz(quizId);
        if (!quiz) throw new Error('Quiz not downloaded');

        const db = await getDb();
        const existingAttempts = await db.getAllFromIndex(
            'offline_attempts',
            'quiz_id',
            Number(quizId),
        );
        const activeAttempt = existingAttempts
            .filter((attempt) => attempt.status === 'in_progress')
            .sort((a, b) => b.started_at.localeCompare(a.started_at))[0];
        if (activeAttempt) return activeAttempt;

        const queuedAttempt = existingAttempts
            .filter((attempt) => attempt.status === 'submitted')
            .sort((a, b) => b.started_at.localeCompare(a.started_at))
            .find((attempt) => attempt.queue_status !== 'synced');
        if (queuedAttempt) return queuedAttempt;

        const attemptNumber = Number(quiz.attempt_count || 0) + 1;
        if (Number(quiz.max_attempts) > 0 && attemptNumber > Number(quiz.max_attempts)) {
            throw new Error('Maximum attempts reached');
        }

        const syncUuid = crypto.randomUUID();
        const attempt = {
            sync_uuid: syncUuid,
            quiz_id: Number(quizId),
            attempt_number: attemptNumber,
            started_at: new Date().toISOString(),
            status: 'in_progress',
            current_question: 0,
        };
        await db.put('offline_attempts', attempt);
        await db.put('quizzes', { ...quiz, attempt_count: attemptNumber });
        return attempt;
    },

    async saveOfflineAttempt(attempt) {
        const db = await getDb();
        await db.put('offline_attempts', attempt);
    },

    async getOfflineAttempt(syncUuid) {
        const db = await getDb();
        return db.get('offline_attempts', syncUuid);
    },

    async submitStartedQuizOffline(syncUuid, quizId, attemptNumber, startedAt, answers) {
        const now = new Date().toISOString();
        const payload = {
            quiz_id: Number(quizId),
            attempt_number: attemptNumber,
            started_at: startedAt,
            completed_at: now,
            submitted_at: now,
            integrity_log: [],
            answers: answers.map((a) => ({
                question_id: a.questionId ?? a.question_id,
                answer_text: a.answer_text ?? null,
                selected_options: a.selected_options ?? null,
            })),
        };

        return this.queueSubmission({
            sync_uuid: syncUuid,
            entity_type: 'quiz_attempt',
            action: 'update',
            payload,
        });
    },

    async submitQuizOffline(syncUuid, quizId, answers) {
        const attempt = await this.getOfflineAttempt(syncUuid);
        const now = new Date().toISOString();
        const payload = {
            quiz_id: Number(quizId),
            attempt_number: attempt?.attempt_number ?? 1,
            started_at: attempt?.started_at ?? now,
            completed_at: now,
            submitted_at: now,
            integrity_log: [],
            answers: answers.map((a) => ({
                question_id: a.questionId,
                answer_text: a.answer_text ?? null,
                selected_options: a.selected_options ?? null,
            })),
        };

        const db = await getDb();
        if (attempt) {
            attempt.status = 'submitted';
            attempt.submitted_at = now;
            attempt.queue_status = 'pending';
            await db.put('offline_attempts', attempt);
        }

        const entry = await this.queueSubmission({
            sync_uuid: syncUuid,
            entity_type: 'quiz_attempt',
            action: 'create',
            payload,
        });

        return entry;
    },

    async saveAssignmentDraft(assignmentId, data) {
        const db = await getDb();
        await db.put('assignment_drafts', {
            assignment_id: Number(assignmentId),
            text_response: data.text_response ?? '',
            file: data.file ?? null,
            file_name: data.file?.name ?? data.file_name ?? null,
            saved_at: new Date().toISOString(),
        });
    },

    async getAssignmentDraft(assignmentId) {
        const db = await getDb();
        return db.get('assignment_drafts', Number(assignmentId));
    },

    async submitAssignmentOffline(assignmentId, data) {
        const syncUuid = data.sync_uuid || crypto.randomUUID();
        const payload = {
            assignment_id: Number(assignmentId),
            text_response: data.text_response ?? null,
            file_path: data.file_path ?? null,
            status: data.status ?? 'submitted',
            submitted_at: new Date().toISOString(),
            version: data.version ?? 1,
        };

        return this.queueSubmission({
            sync_uuid: syncUuid,
            entity_type: 'assignment_submission',
            action: 'create',
            payload,
            attachment: data.file ?? null,
        });
    },

    async queueSubmission(item) {
        const db = await getDb();
        const entry = {
            sync_uuid: item.sync_uuid || crypto.randomUUID(),
            entity_type: item.entity_type,
            action: item.action || 'create',
            payload: item.payload,
            checksum: item.checksum || null,
            device_id: getDeviceId(),
            status: 'pending',
            created_at: new Date().toISOString(),
            label: item.label || null,
            attachment: item.attachment || null,
        };
        await db.put('sync_queue', entry);
        await this._registerBackgroundSync();
        if (navigator.onLine) {
            await this.processQueue();
        }
        return entry;
    },

    async _registerBackgroundSync() {
        if (!('serviceWorker' in navigator)) return;
        try {
            const db = await getDb();
            await db.put('settings', { key: 'csrf_token', value: getCsrfToken() });
            const registration = await navigator.serviceWorker.ready;
            if ('sync' in registration) await registration.sync.register('edusync-sync');
        } catch (error) {
            console.error('EduSync could not schedule background synchronization.', error);
            window.dispatchEvent(new CustomEvent('edusync:offline-error', { detail: { error } }));
        }
    },

    async getPendingCount() {
        const db = await getDb();
        const all = await db.getAll('sync_queue');
        return all.filter((i) => ['pending', 'syncing', 'failed'].includes(i.status)).length;
    },

    async getSyncQueue() {
        const db = await getDb();
        return db.getAll('sync_queue');
    },

    async deleteFailedQueueItem(syncUuid) {
        return this.deleteFailedQueueItems([syncUuid]);
    },

    async deleteFailedQueueItems(syncUuids) {
        const db = await getDb();
        const transaction = db.transaction('sync_queue', 'readwrite');
        const store = transaction.objectStore('sync_queue');
        let deleted = 0;

        for (const syncUuid of new Set(syncUuids)) {
            const item = await store.get(syncUuid);
            if (item?.status !== 'failed') continue;
            await store.delete(syncUuid);
            deleted++;
        }

        await transaction.done;
        return deleted;
    },

    async processQueue() {
        if (queueProcessing) return queueProcessing;
        queueProcessing = this._processQueue().finally(() => {
            queueProcessing = null;
        });
        return queueProcessing;
    },

    async _processQueue() {
        if (!navigator.onLine) return { processed: 0, failed: 0 };
        const db = await getDb();
        const items = (await db.getAll('sync_queue'))
            .sort((a, b) => a.created_at.localeCompare(b.created_at));
        let processed = 0;
        let failed = 0;

        for (const candidate of items) {
            const item = await this._claimQueueItem(db, candidate.sync_uuid);
            if (!item) continue;

            item.attempts = (item.attempts || 0) + 1;
            item.sync_started_at = new Date().toISOString();
            await db.put('sync_queue', item);
            try {
                const controller = new AbortController();
                const timeout = setTimeout(() => controller.abort(), 30000);
                let res;
                try {
                    const hasAttachment = item.entity_type === 'assignment_submission'
                        && item.attachment instanceof Blob;
                    let endpoint = '/api/v1/sync';
                    let headers = {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    };
                    let body;
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
                        headers = {
                            ...headers,
                            'Content-Type': 'application/json',
                        };
                        body = JSON.stringify({
                            sync_uuid: item.sync_uuid,
                            entity_type: item.entity_type,
                            action: item.action,
                            payload: item.payload,
                            checksum: item.checksum,
                            device_id: item.device_id,
                        });
                    }
                    res = await fetch(endpoint, {
                        method: 'POST',
                        headers,
                        credentials: 'same-origin',
                        signal: controller.signal,
                        body,
                    });
                } finally {
                    clearTimeout(timeout);
                }
                let json = {};
                try {
                    json = await res.json();
                } catch {
                    json = {};
                }
                item.status = res.ok && json.success ? 'synced' : 'failed';
                item.synced_at = item.status === 'synced' ? new Date().toISOString() : null;
                item.error = item.status === 'synced' ? null : [
                    json.message || `Sync failed (${res.status})`,
                    ...Object.values(json.errors || {}).flat(),
                ].join(' ');
                if (item.status === 'synced') delete item.attachment;
                item.retryable = item.status !== 'synced'
                    && (res.status === 0 || res.status === 202 || res.status === 401 || res.status === 408 || res.status === 429 || res.status >= 500);
                item.next_retry_at = item.retryable
                    ? this._nextRetryAt(item.attempts)
                    : null;
                await db.put('sync_queue', item);
                if (item.entity_type === 'quiz_attempt') {
                    const attempt = await db.get('offline_attempts', item.sync_uuid);
                    if (attempt) {
                        attempt.queue_status = item.status;
                        attempt.sync_error = item.error;
                        await db.put('offline_attempts', attempt);
                    }
                }
                if (item.status === 'synced') processed++;
                else failed++;
            } catch (err) {
                item.status = 'failed';
                item.error = err instanceof Error ? err.message : String(err);
                item.retryable = true;
                item.next_retry_at = this._nextRetryAt(item.attempts);
                await db.put('sync_queue', item);
                if (item.entity_type === 'quiz_attempt') {
                    const attempt = await db.get('offline_attempts', item.sync_uuid);
                    if (attempt) {
                        attempt.queue_status = 'failed';
                        attempt.sync_error = item.error;
                        await db.put('offline_attempts', attempt);
                    }
                }
                failed++;
            }
        }

        this._scheduleRetry(await db.getAll('sync_queue'));
        window.dispatchEvent(new CustomEvent('edusync:sync-complete', { detail: { processed, failed } }));
        return { processed, failed };
    },

    async _claimQueueItem(db, syncUuid) {
        const transaction = db.transaction('sync_queue', 'readwrite');
        const store = transaction.objectStore('sync_queue');
        const item = await store.get(syncUuid);
        const now = Date.now();
        if (!item || item.status === 'synced') {
            await transaction.done;
            return null;
        }
        const authenticationFailure = /unauthenticated/i.test(item.error || '');
        const outdatedAvailabilityFailure = /started before its availability window/i.test(item.error || '')
            && !item.availability_retry_attempted;
        if (
            item.status === 'failed'
            && item.retryable === false
            && !authenticationFailure
            && !outdatedAvailabilityFailure
        ) {
            await transaction.done;
            return null;
        }
        if (item.status === 'failed' && item.next_retry_at
            && Date.parse(item.next_retry_at) > now) {
            await transaction.done;
            return null;
        }
        if (item.status === 'syncing' && item.sync_started_at
            && now - Date.parse(item.sync_started_at) < 120000) {
            await transaction.done;
            return null;
        }
        if (!['pending', 'failed', 'syncing'].includes(item.status)) {
            await transaction.done;
            return null;
        }

        if (outdatedAvailabilityFailure) {
            item.availability_retry_attempted = true;
        }
        item.status = 'syncing';
        item.sync_started_at = new Date(now).toISOString();
        await store.put(item);
        await transaction.done;
        return item;
    },

    _nextRetryAt(attempts) {
        const seconds = Math.min(300, 2 ** Math.min(attempts, 8));
        return new Date(Date.now() + seconds * 1000).toISOString();
    },

    _scheduleRetry(items) {
        clearTimeout(retryTimer);
        const next = items
            .filter((item) => item.status === 'failed' && item.retryable && item.next_retry_at)
            .map((item) => Date.parse(item.next_retry_at))
            .filter(Number.isFinite)
            .sort((a, b) => a - b)[0];
        if (next === undefined) return;
        retryTimer = setTimeout(() => {
            if (navigator.onLine) this.processQueue();
        }, Math.max(0, next - Date.now()));
    },
};

window.EduSyncOffline = EduSyncOffline;
window.dispatchEvent(new Event('edusync:offline-ready'));

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(async (registration) => {
                if (navigator.onLine) {
                    try {
                        await registration.update();
                    } catch (error) {
                        console.error('EduSync could not check for a Service Worker update.', error);
                    }
                }
            })
            .catch((error) => {
                console.error('EduSync Service Worker registration failed.', error);
                window.dispatchEvent(new CustomEvent('edusync:offline-error', { detail: { error } }));
            });
    });
}

window.addEventListener('online', () => {
    EduSyncOffline.processQueue();
    EduSyncOffline.cacheStudentData().catch((error) => {
        console.error('EduSync could not refresh offline student data.', error);
        window.dispatchEvent(new CustomEvent('edusync:offline-error', { detail: { error } }));
    });
});
window.addEventListener('load', () => EduSyncOffline.processQueue());
window.addEventListener('load', () => {
    if (!navigator.onLine) return;
    EduSyncOffline.cacheStudentData().catch((error) => {
        console.error('EduSync could not cache student data.', error);
        window.dispatchEvent(new CustomEvent('edusync:offline-error', { detail: { error } }));
    });
});

export default EduSyncOffline;
