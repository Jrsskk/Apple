import { Head, Link, router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime, statusBadge } from '@/utils/teacher';

export default function Index({ assignments, subjects, filters }) {
    return (
        <TeacherLayout title="Assignments">
            <Head title="Assignments" />

            <div className="flex justify-end mb-4">
                <Link href="/teacher/assignments/create" className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Create Assignment</Link>
            </div>

            <label className="mb-4 block max-w-sm text-sm font-medium text-slate-700">
                Subject
                <select
                    value={filters?.subject_id ? String(filters.subject_id) : ''}
                    onChange={(event) => router.get('/teacher/assignments', { subject_id: event.target.value }, { preserveState: true, preserveScroll: true, replace: true })}
                    className="mt-1 w-full rounded-lg border-slate-300"
                >
                    <option value="">All subjects</option>
                    {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                </select>
            </label>

            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b">
                        <tr>
                            <th className="text-left p-3">Subject → Class → Assignment</th>
                            <th className="text-left p-3">Submissions</th>
                            <th className="text-left p-3">Deadline</th>
                            <th className="text-left p-3">Status</th>
                            <th className="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {assignments.data.length === 0 ? (
                            <tr><td colSpan="5" className="p-5 text-center text-slate-500">No assignments match this subject.</td></tr>
                        ) : assignments.data.map((a) => (
                            <tr key={a.id} className="border-b last:border-0">
                                <td className="p-3 font-medium">{a.subject?.name} <span className="text-slate-400">→</span> {a.school_class?.name} - {a.school_class?.section} <span className="text-slate-400">→</span> {a.title}</td>
                                <td className="p-3">{a.submissions_count}</td>
                                <td className="p-3">{formatDateTime(a.deadline)}</td>
                                <td className="p-3">
                                    <span className={`px-2 py-0.5 rounded text-xs ${statusBadge(a.status?.value ?? a.status)}`}>
                                        {a.status?.value ?? a.status}
                                    </span>
                                </td>
                                <td className="p-3 text-right">
                                    <Link href={`/teacher/assignments/${a.id}/edit`} className="text-emerald-600 hover:underline">Edit</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </TeacherLayout>
    );
}
