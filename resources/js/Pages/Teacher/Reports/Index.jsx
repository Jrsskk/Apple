import { Head } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function Index({ classes, quizzes, assignments }) {
    return (
        <TeacherLayout title="Reports">
            <Head title="Reports" />

            <div className="space-y-6">
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-4">Class Summary Reports</h2>
                    <div className="space-y-2">
                        {classes.map((c) => (
                            <div key={c.id} className="flex justify-between items-center p-3 border rounded-lg">
                                <div>
                                    <p className="font-medium text-sm">{c.display_name || `${c.name} - ${c.section}`}</p>
                                    <p className="text-xs text-slate-500">{c.students_count} students · {c.quizzes_count} quizzes · {c.assignments_count} assignments</p>
                                </div>
                                <a href={`/teacher/reports/class/${c.id}/export`} className="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs">Download CSV</a>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-4">Quiz Reports</h2>
                    <div className="space-y-2">
                        {quizzes.map((q) => (
                            <div key={q.id} className="flex justify-between items-center p-3 border rounded-lg">
                                <div>
                                    <p className="font-medium text-sm">{q.title}</p>
                                    <p className="text-xs text-slate-500">{q.school_class?.name}</p>
                                </div>
                                <a href={`/teacher/reports/quiz/${q.id}/export`} className="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs">Download CSV</a>
                            </div>
                        ))}
                    </div>
                </div>

                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-4">Assignment Reports</h2>
                    <div className="space-y-2">
                        {assignments.map((a) => (
                            <div key={a.id} className="flex justify-between items-center p-3 border rounded-lg">
                                <div>
                                    <p className="font-medium text-sm">{a.title}</p>
                                    <p className="text-xs text-slate-500">{a.school_class?.name}</p>
                                </div>
                                <a href={`/teacher/reports/assignment/${a.id}/export`} className="px-3 py-1.5 bg-emerald-600 text-white rounded text-xs">Download CSV</a>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </TeacherLayout>
    );
}
