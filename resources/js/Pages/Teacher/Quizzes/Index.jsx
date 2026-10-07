import { Head, Link, router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime, statusBadge } from '@/utils/teacher';

export default function Index({ quizzes, subjects, filters }) {
    const deleteQuiz = (quiz) => {
        if (window.confirm(`Archive "${quiz.title}"? This removes it from student access.`)) {
            router.delete(`/teacher/quizzes/${quiz.id}`);
        }
    };

    return (
        <TeacherLayout title="Quizzes">
            <Head title="Quizzes" />

            <div className="flex justify-end mb-4">
                <Link href="/teacher/quizzes/create" className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Create Quiz</Link>
            </div>

            <label className="mb-4 block max-w-sm text-sm font-medium text-slate-700">
                Subject
                <select
                    value={filters?.subject_id ? String(filters.subject_id) : ''}
                    onChange={(event) => router.get('/teacher/quizzes', { subject_id: event.target.value }, { preserveState: true, preserveScroll: true, replace: true })}
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
                            <th className="text-left p-3">Subject → Class → Quiz</th>
                            <th className="text-left p-3">Questions</th>
                            <th className="text-left p-3">Deadline</th>
                            <th className="text-left p-3">Status</th>
                            <th className="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {quizzes.data.length === 0 ? (
                            <tr><td colSpan="5" className="p-5 text-center text-slate-500">No quizzes match this subject.</td></tr>
                        ) : quizzes.data.map((q) => (
                            <tr key={q.id} className="border-b last:border-0">
                                <td className="p-3 font-medium">{q.subject?.name} <span className="text-slate-400">→</span> {q.school_class?.name} - {q.school_class?.section} <span className="text-slate-400">→</span> {q.title}</td>
                                <td className="p-3">{q.questions_count}</td>
                                <td className="p-3">{formatDateTime(q.deadline)}</td>
                                <td className="p-3">
                                    <span className={`px-2 py-0.5 rounded text-xs ${statusBadge(q.status?.value ?? q.status)}`}>
                                        {q.status?.value ?? q.status}
                                    </span>
                                </td>
                                <td className="p-3 text-right">
                                    <div className="flex justify-end gap-3">
                                        <Link href={`/teacher/quizzes/${q.id}/edit`} className="text-emerald-600 hover:underline">Edit</Link>
                                        <button type="button" onClick={() => deleteQuiz(q)} className="text-red-600 hover:underline">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </TeacherLayout>
    );
}
