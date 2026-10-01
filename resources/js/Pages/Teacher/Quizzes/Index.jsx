import { Head, Link, router } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime, statusBadge } from '@/utils/teacher';

export default function Index({ quizzes }) {
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

            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b">
                        <tr>
                            <th className="text-left p-3">Title</th>
                            <th className="text-left p-3">Subject</th>
                            <th className="text-left p-3">Class</th>
                            <th className="text-left p-3">Questions</th>
                            <th className="text-left p-3">Deadline</th>
                            <th className="text-left p-3">Status</th>
                            <th className="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {quizzes.data.map((q) => (
                            <tr key={q.id} className="border-b last:border-0">
                                <td className="p-3 font-medium">{q.title}</td>
                                <td className="p-3">{q.subject?.name}</td>
                                <td className="p-3">{q.school_class?.name} - {q.school_class?.section}</td>
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
