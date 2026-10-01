import { openDB } from 'idb';

const DB_NAME = 'edusync-offline';
const DB_VERSION = 2;

async function getDb() {
    return openDB(DB_NAME, DB_VERSION, {
        upgrade(db, oldVersion) {
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
        }
        return json;
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
            await db.put('materials', {
                ...json.data,
                id: json.data.id ?? materialId,
                downloaded_at: new Date().toISOString(),
            });
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
        return !!quiz;
    },

    async isAssignmentDownloaded(assignmentId) {
        const item = await this.getAssignment(assignmentId);
        return !!item;
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

        const syncUuid = crypto.randomUUID();
        const attempt = {
            sync_uuid: syncUuid,
            quiz_id: Number(quizId),
            attempt_number: (quiz.attempt_count || 0) + 1,
            started_at: new Date().toISOString(),
            status: 'in_progress',
        };
        const db = await getDb();
        await db.put('offline_attempts', attempt);
        return attempt;
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

        const entry = await this.queueSubmission({
            sync_uuid: syncUuid,
            entity_type: 'quiz_attempt',
            action: 'create',
            payload,
        });

        const db = await getDb();
        if (attempt) {
            attempt.status = 'submitted';
            attempt.submitted_at = now;
            await db.put('offline_attempts', attempt);
        }

        return entry;
    },

    async saveAssignmentDraft(assignmentId, data) {
        const db = await getDb();
        await db.put('assignment_drafts', {
            assignment_id: Number(assignmentId),
            text_response: data.text_response ?? '',
            file_name: data.file_name ?? null,
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
        };
        await db.put('sync_queue', entry);
        if (navigator.onLine) {
            await this.processQueue();
        }
        return entry;
    },

    async getPendingCount() {
        const db = await getDb();
        const all = await db.getAll('sync_queue');
        return all.filter((i) => i.status === 'pending' || i.status === 'failed').length;
    },

    async getSyncQueue() {
        const db = await getDb();
        return db.getAll('sync_queue');
    },

    async processQueue() {
        if (!navigator.onLine) return { processed: 0, failed: 0 };
        const db = await getDb();
        const items = await db.getAll('sync_queue');
        const pending = items.filter((i) => i.status === 'pending' || i.status === 'failed');
        let processed = 0;
        let failed = 0;

        for (const item of pending) {
            try {
                const res = await fetch('/api/v1/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        sync_uuid: item.sync_uuid,
                        entity_type: item.entity_type,
                        action: item.action,
                        payload: item.payload,
                        checksum: item.checksum,
                        device_id: item.device_id,
                    }),
                });
                const json = await res.json();
                item.status = json.success ? 'synced' : 'failed';
                item.synced_at = json.success ? new Date().toISOString() : null;
                item.error = json.success ? null : (json.message || 'Sync failed');
                await db.put('sync_queue', item);
                if (json.success) processed++;
                else failed++;
            } catch (err) {
                item.status = 'failed';
                item.error = err.message;
                await db.put('sync_queue', item);
                failed++;
            }
        }

        window.dispatchEvent(new CustomEvent('edusync:sync-complete', { detail: { processed, failed } }));
        return { processed, failed };
    },
};

window.EduSyncOffline = EduSyncOffline;

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}

window.addEventListener('online', () => EduSyncOffline.processQueue());

export default EduSyncOffline;
