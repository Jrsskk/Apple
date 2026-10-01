import { Head, Link } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime, statusBadge } from '@/utils/teacher';

export default function Index({ assignments }) {
    return (
        <TeacherLayout title="Assignments">
            <Head title="Assignments" />

            <div className="flex justify-end mb-4">
                <Link href="/teacher/assignments/create" className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Create Assignment</Link>
            </div>

            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b">
                        <tr>
                            <th className="text-left p-3">Title</th>
                            <th className="text-left p-3">Subject</th>
                            <th className="text-left p-3">Class</th>
                            <th className="text-left p-3">Submissions</th>
                            <th className="text-left p-3">Deadline</th>
                            <th className="text-left p-3">Status</th>
                            <th className="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {assignments.data.map((a) => (
                            <tr key={a.id} className="border-b last:border-0">
                                <td className="p-3 font-medium">{a.title}</td>
                                <td className="p-3">{a.subject?.name}</td>
                                <td className="p-3">{a.school_class?.name} - {a.school_class?.section}</td>
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
