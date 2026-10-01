import { Head } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime, statusBadge } from '@/utils/teacher';

export default function Index({
    stats,
    pendingQueue,
    failedQueue,
    pendingQuizAttempts,
    pendingAssignmentSubmissions,
    syncedQuizAttempts,
}) {
    return (
        <TeacherLayout title="Sync Monitor">
            <Head title="Sync Monitor" />

            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {[
                    { label: 'Pending Queue', value: stats.pending_queue, color: 'text-yellow-600' },
                    { label: 'Failed Sync', value: stats.failed_queue, color: 'text-red-600' },
                    { label: 'Pending Quiz Submissions', value: stats.pending_quiz_submissions, color: 'text-orange-600' },
                    { label: 'Pending Assignment Submissions', value: stats.pending_assignment_submissions, color: 'text-orange-600' },
                ].map((s) => (
                    <div key={s.label} className="bg-white rounded-xl border p-4">
                        <p className="text-sm text-slate-500">{s.label}</p>
                        <p className={`text-2xl font-bold mt-1 ${s.color}`}>{s.value}</p>
                    </div>
                ))}
            </div>

            <div className="grid lg:grid-cols-2 gap-6">
                <Section title="Pending Sync Queue" items={pendingQueue} renderItem={(item) => (
                    <>
                        <p className="font-medium text-sm">{item.user?.first_name} {item.user?.last_name}</p>
                        <p className="text-xs text-slate-500">{item.entity_type} · {item.action}</p>
                    </>
                )} />

                <Section title="Failed Sync" items={failedQueue} renderItem={(item) => (
                    <>
                        <p className="font-medium text-sm">{item.user?.first_name} {item.user?.last_name}</p>
                        <p className="text-xs text-red-500">{item.error_message || 'Sync failed'}</p>
                    </>
                )} />

                <Section title="Pending Quiz Submissions (Not Synced)" items={pendingQuizAttempts} renderItem={(item) => (
                    <>
                        <p className="font-medium text-sm">{item.student?.first_name} {item.student?.last_name}</p>
                        <p className="text-xs text-slate-500">{item.quiz?.title} · {formatDateTime(item.submitted_at)}</p>
                    </>
                )} />

                <Section title="Recently Synced Quiz Attempts" items={syncedQuizAttempts} renderItem={(item) => (
                    <>
                        <p className="font-medium text-sm">{item.student?.first_name} {item.student?.last_name}</p>
                        <p className="text-xs text-slate-500">{item.quiz?.title} · Synced {formatDateTime(item.synced_at)}</p>
                    </>
                )} />
            </div>
        </TeacherLayout>
    );
}

function Section({ title, items, renderItem }) {
    return (
        <div className="bg-white rounded-xl border p-5">
            <h2 className="font-semibold mb-4">{title}</h2>
            {items.length === 0 ? (
                <p className="text-sm text-slate-500">None</p>
            ) : (
                <div className="space-y-2 max-h-64 overflow-y-auto">
                    {items.map((item) => (
                        <div key={item.id} className="p-3 border rounded-lg text-sm">{renderItem(item)}</div>
                    ))}
                </div>
            )}
        </div>
    );
}
