import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime } from '@/utils/teacher';

function Pagination({ paginator }) {
    if (paginator.links.length < 4) return null;

    return (
        <nav className="flex flex-wrap gap-1 border-t p-4" aria-label="Pagination">
            {paginator.links.map((link, index) => {
                const label = link.label.replace('&laquo;', '‹').replace('&raquo;', '›');
                return link.url ? (
                    <Link key={`${link.label}-${index}`} href={link.url} className={`rounded border px-3 py-1 text-sm ${link.active ? 'border-emerald-600 bg-emerald-50 text-emerald-700' : 'border-slate-200 text-slate-600'}`}>
                        {label}
                    </Link>
                ) : (
                    <span key={`${link.label}-${index}`} className="rounded border border-slate-100 px-3 py-1 text-sm text-slate-300">{label}</span>
                );
            })}
        </nav>
    );
}

function GradeTable({ title, rows, activityKey, totalScore, detailPath, paginator }) {
    return (
        <section className="overflow-hidden rounded-xl border bg-white">
            <h2 className="border-b p-4 font-semibold">{title}</h2>
            {rows.length === 0 ? (
                <p className="p-5 text-sm text-slate-500">No grades match the selected filters.</p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="min-w-[780px] w-full text-left text-sm">
                        <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th className="p-3">Subject</th>
                                <th className="p-3">Class</th>
                                <th className="p-3">Student</th>
                                <th className="p-3">Activity</th>
                                <th className="p-3">Score</th>
                                <th className="p-3">Total Score</th>
                                <th className="p-3">Date</th>
                                <th className="p-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {rows.map((row) => {
                                const activity = row[activityKey];
                                return (
                                    <tr key={row.id}>
                                        <td className="p-3">{activity?.subject?.name || '—'}</td>
                                        <td className="p-3">{activity?.school_class?.name}{activity?.school_class?.section ? ` - ${activity.school_class.section}` : ''}</td>
                                        <td className="p-3 font-medium">{row.student?.first_name} {row.student?.last_name}</td>
                                        <td className="p-3">{activity?.title}</td>
                                        <td className="p-3">{row.score ?? '—'}</td>
                                        <td className="p-3">{totalScore(row) ?? '—'}</td>
                                        <td className="p-3 whitespace-nowrap">{formatDateTime(row.submitted_at)}</td>
                                        <td className="p-3"><Link href={`${detailPath}/${row.id}`} className="text-emerald-700 hover:underline">View</Link></td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
            <Pagination paginator={paginator} />
        </section>
    );
}

export default function Index({ quizGrades, assignmentGrades, gradeSummary, subjects, classes, filters }) {
    const [subjectId, setSubjectId] = useState(filters.subject_id ? String(filters.subject_id) : '');
    const [schoolClassId, setSchoolClassId] = useState(filters.school_class_id ? String(filters.school_class_id) : '');
    const filteredClasses = classes.filter((schoolClass) => !subjectId || String(schoolClass.subject_id) === subjectId);

    const submitFilters = (event) => {
        event.preventDefault();
        router.get('/teacher/grades', { subject_id: subjectId, school_class_id: schoolClassId }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <TeacherLayout title="Grades">
            <Head title="Grades" />

            <form onSubmit={submitFilters} className="mb-6 flex flex-col gap-3 rounded-xl border bg-white p-4 sm:flex-row sm:items-end">
                <label className="flex-1 text-sm font-medium text-slate-700">
                    Subject
                    <select value={subjectId} onChange={(event) => { setSubjectId(event.target.value); setSchoolClassId(''); }} className="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">All subjects</option>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                    </select>
                </label>
                <label className="flex-1 text-sm font-medium text-slate-700">
                    Class
                    <select value={schoolClassId} onChange={(event) => setSchoolClassId(event.target.value)} className="mt-1 w-full rounded-lg border-slate-300">
                        <option value="">All classes</option>
                        {filteredClasses.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name} - {schoolClass.section}</option>)}
                    </select>
                </label>
                <button type="submit" className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white">Apply filters</button>
            </form>

            <section className="mb-6 overflow-hidden rounded-xl border bg-white">
                <div className="border-b p-4">
                    <h2 className="font-semibold">Subject Performance Summary</h2>
                    <p className="mt-1 text-sm text-slate-500">Combined graded assignment and quiz results for each student in each subject.</p>
                </div>
                {gradeSummary.length === 0 ? (
                    <p className="p-5 text-sm text-slate-500">No graded activities match the selected filters.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="min-w-[650px] w-full text-left text-sm">
                            <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="p-3">Subject</th>
                                    <th className="p-3">Student</th>
                                    <th className="p-3">Activities</th>
                                    <th className="p-3">Score / Total Score</th>
                                    <th className="p-3">Performance</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y">
                                {gradeSummary.map((row) => (
                                    <tr key={`${row.subject_id}-${row.student_id}`}>
                                        <td className="p-3 font-medium">{row.subject_name}</td>
                                        <td className="p-3">{row.student_name}</td>
                                        <td className="p-3">{row.activity_count}</td>
                                        <td className="p-3">{row.score} / {row.total_score}</td>
                                        <td className="p-3">{row.percentage === null ? '—' : `${row.percentage}%`}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>

            <div className="space-y-6">
                <GradeTable
                    title="Quiz Grades"
                    rows={quizGrades.data}
                    paginator={quizGrades}
                    activityKey="quiz"
                    detailPath="/teacher/submissions/quiz"
                    totalScore={(row) => row.total_points ?? row.quiz?.total_points}
                />
                <GradeTable
                    title="Assignment Grades"
                    rows={assignmentGrades.data}
                    paginator={assignmentGrades}
                    activityKey="assignment"
                    detailPath="/teacher/submissions/assignment"
                    totalScore={(row) => row.assignment?.max_score}
                />
            </div>
        </TeacherLayout>
    );
}
