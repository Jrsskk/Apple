import { Head, router, usePage } from '@inertiajs/react';
import { Chart, registerables } from 'chart.js';
import { useEffect, useRef, useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';

Chart.register(...registerables);

const colors = {
    emerald: '#059669',
    blue: '#3b82f6',
    violet: '#8b5cf6',
    amber: '#f59e0b',
    red: '#ef4444',
    slate: '#cbd5e1',
};

function ChartCanvas({ type, data, options, label }) {
    const canvas = useRef(null);

    useEffect(() => {
        if (!canvas.current) return undefined;
        const chart = new Chart(canvas.current, { type, data, options });
        return () => chart.destroy();
    }, [type, data, options]);

    return <canvas ref={canvas} role="img" aria-label={label} />;
}

function EmptyState({ children }) {
    return (
        <div className="flex min-h-48 items-center justify-center rounded-lg bg-slate-50 px-5 text-center text-sm text-slate-500">
            {children}
        </div>
    );
}

function Panel({ title, description, children, className = '' }) {
    return (
        <section className={`rounded-xl border border-slate-200 bg-white p-5 shadow-sm ${className}`}>
            <div className="mb-4">
                <h2 className="font-semibold text-slate-900">{title}</h2>
                {description && <p className="mt-1 text-xs text-slate-500">{description}</p>}
            </div>
            {children}
        </section>
    );
}

function MetricCard({ title, value, caption, color = 'emerald' }) {
    const accent = {
        emerald: 'bg-emerald-500',
        blue: 'bg-blue-500',
        violet: 'bg-violet-500',
        amber: 'bg-amber-500',
        red: 'bg-rose-500',
    }[color];

    return (
        <article className="relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <span className={`absolute inset-y-0 left-0 w-1 ${accent}`} aria-hidden="true" />
            <p className="text-sm font-medium text-slate-500">{title}</p>
            <p className="mt-2 text-2xl font-bold tracking-tight text-slate-900">{value}</p>
            <p className="mt-1 text-xs text-slate-500">{caption}</p>
        </article>
    );
}

function formatPercent(value) {
    return value === null || value === undefined ? '—' : `${Number(value).toFixed(1)}%`;
}

function progressOptions(max = 100) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8, padding: 18 } } },
        scales: {
            y: { beginAtZero: true, max, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
            x: { grid: { display: false } },
        },
    };
}

export default function Index({ filters, options, stats, charts, students, empty }) {
    const { props: pageProps } = usePage();
    const errors = pageProps.errors || {};
    const [processing, setProcessing] = useState(false);
    const [values, setValues] = useState({
        subject_id: filters.subject_id || '',
        school_class_id: filters.school_class_id || '',
        quiz_id: filters.quiz_id || '',
        assignment_id: filters.assignment_id || '',
        student_id: filters.student_id || '',
        academic_year_id: filters.academic_year_id || '',
        from_date: filters.from_date || '',
        to_date: filters.to_date || '',
    });

    const update = (key) => (event) => setValues((current) => ({ ...current, [key]: event.target.value }));
    const submit = (event) => {
        event.preventDefault();
        const params = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== ''));
        router.get('/teacher/analytics', params, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };
    const reset = () => {
        const cleared = {
            subject_id: '', school_class_id: '', quiz_id: '', assignment_id: '',
            student_id: '', academic_year_id: '', from_date: '', to_date: '',
        };
        setValues(cleared);
        router.get('/teacher/analytics', {}, {
            preserveScroll: true,
            replace: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    const subjectData = {
        labels: charts.subjectPerformance.map((row) => row.subject),
        datasets: [
            {
                label: 'Quiz average',
                data: charts.subjectPerformance.map((row) => row.quiz),
                backgroundColor: colors.emerald,
                borderRadius: 5,
            },
            {
                label: 'Assignment average',
                data: charts.subjectPerformance.map((row) => row.assignment),
                backgroundColor: colors.blue,
                borderRadius: 5,
            },
        ],
    };
    const progressData = {
        labels: charts.progress.map((row) => row.label),
        datasets: [{
            label: 'Average score',
            data: charts.progress.map((row) => row.average),
            borderColor: colors.violet,
            backgroundColor: 'rgba(139, 92, 246, 0.12)',
            fill: true,
            tension: 0.35,
            spanGaps: true,
            pointRadius: 3,
        }],
    };
    const distributionData = {
        labels: charts.gradeDistribution.map((row) => row.label),
        datasets: [{
            data: charts.gradeDistribution.map((row) => row.count),
            backgroundColor: [colors.emerald, colors.blue, colors.violet, colors.amber, colors.red],
            borderWidth: 0,
        }],
    };
    const questionData = {
        labels: charts.questionPerformance.byQuestion.map((row) => row.label),
        datasets: [{
            label: 'Correct responses',
            data: charts.questionPerformance.byQuestion.map((row) => row.accuracy),
            backgroundColor: colors.blue,
            borderRadius: 4,
        }],
    };

    return (
        <TeacherLayout title="Teacher Analytics">
            <Head title="Teacher Analytics" />

            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-xl font-bold text-slate-900">Learning analytics</h1>
                    <p className="mt-1 text-sm text-slate-500">Track class progress and activity using your students’ recorded work.</p>
                </div>
                <span className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Live database data</span>
            </div>

            <form onSubmit={submit} aria-label="Filter analytics" className="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <label className="text-xs font-semibold text-slate-600">
                        Academic year
                        <select value={values.academic_year_id} onChange={(event) => setValues((current) => ({ ...current, academic_year_id: event.target.value, subject_id: '', school_class_id: '', quiz_id: '', assignment_id: '', student_id: '' }))} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal">
                            <option value="">All academic years</option>
                            {options.academicYears.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        Subject
                        <select value={values.subject_id} onChange={(event) => setValues((current) => ({ ...current, subject_id: event.target.value, school_class_id: '', quiz_id: '', assignment_id: '', student_id: '' }))} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal">
                            <option value="">All subjects</option>
                            {options.subjects.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        Class / section
                        <select value={values.school_class_id} onChange={(event) => setValues((current) => ({ ...current, school_class_id: event.target.value, quiz_id: '', assignment_id: '', student_id: '' }))} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal">
                            <option value="">All classes</option>
                            {options.classes.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        Student
                        <select value={values.student_id} onChange={update('student_id')} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal">
                            <option value="">All students</option>
                            {options.students.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        Quiz
                        <select value={values.quiz_id} onChange={update('quiz_id')} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal">
                            <option value="">All quizzes</option>
                            {options.quizzes.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        Assignment
                        <select value={values.assignment_id} onChange={update('assignment_id')} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal">
                            <option value="">All assignments</option>
                            {options.assignments.map((row) => <option key={row.id} value={row.id}>{row.name}</option>)}
                        </select>
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        From
                        <input type="date" value={values.from_date} onChange={update('from_date')} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal" />
                    </label>
                    <label className="text-xs font-semibold text-slate-600">
                        To
                        <input type="date" value={values.to_date} onChange={update('to_date')} className="mt-1 block w-full rounded-lg border-slate-300 text-sm font-normal" />
                    </label>
                </div>
                {Object.keys(errors).length > 0 && (
                    <div role="alert" className="mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-800">
                        {Object.values(errors).flat().join(' ')}
                    </div>
                )}
                <div className="mt-4 flex flex-wrap items-center gap-3">
                    <button type="submit" disabled={processing} className="inline-flex min-w-32 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-70">
                        {processing && <span className="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true" />}
                        {processing ? 'Updating…' : 'Apply filters'}
                    </button>
                    <button type="button" onClick={reset} disabled={processing} className="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 disabled:opacity-50">Reset</button>
                    <span className="text-xs text-slate-500" aria-live="polite">{processing ? 'Refreshing analytics…' : 'Filters apply to published activities and recorded submissions.'}</span>
                </div>
            </form>

            {!empty.hasRoster ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <h2 className="font-semibold text-slate-800">No enrolled students found</h2>
                    <p className="mt-2 text-sm text-slate-500">Choose another class or enroll students to see analytics.</p>
                </div>
            ) : (
                <>
                    <div className="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <MetricCard title="Enrolled students" value={stats.students} caption="In the selected classes" />
                        <MetricCard title="Average quiz score" value={formatPercent(stats.average_quiz_score)} caption="Graded quiz attempts" color="blue" />
                        <MetricCard title="Average assignment score" value={formatPercent(stats.average_assignment_score)} caption="Latest graded submissions" color="violet" />
                        <MetricCard title="Overall average" value={formatPercent(stats.average_score)} caption="Across graded student work" color="blue" />
                        <MetricCard title="Quiz completion" value={formatPercent(stats.quiz_completion_rate)} caption="Completed student quiz opportunities" color="violet" />
                        <MetricCard title="Assignment completion" value={formatPercent(stats.assignment_completion_rate)} caption="Submitted student assignment opportunities" color="amber" />
                        <MetricCard title="Submission rate" value={formatPercent(stats.submission_rate)} caption={`${stats.completed_activities} recorded completions`} color="blue" />
                        <MetricCard title="Participation" value={formatPercent(stats.participation_rate)} caption={`${stats.participants} students participated`} color="violet" />
                        <MetricCard title="Pending activities" value={stats.pending_activities} caption="Not yet submitted and not overdue" color="amber" />
                        <MetricCard title="Overdue activities" value={stats.overdue_activities} caption="Past due with no recorded submission" color="red" />
                    </div>

                    <div className="mb-6 grid gap-5 xl:grid-cols-2">
                        <Panel title="Performance by subject" description="Average percentage across graded student work">
                            {charts.subjectPerformance.length === 0 || !empty.hasScores ? <EmptyState>No graded student scores match these filters yet.</EmptyState> : (
                                <div className="h-72">
                                    <ChartCanvas type="bar" data={subjectData} options={{ ...progressOptions(), scales: { ...progressOptions().scales, y: { ...progressOptions().scales.y, max: 100 } } }} label="Bar chart comparing quiz and assignment scores by subject" />
                                </div>
                            )}
                        </Panel>
                        <Panel title="Student progress over time" description="Monthly average score from graded quiz and assignment submissions">
                            {!empty.hasScores ? <EmptyState>Progress will appear after students receive graded results.</EmptyState> : (
                                <div className="h-72">
                                    <ChartCanvas type="line" data={progressData} options={{ ...progressOptions(100), scales: { ...progressOptions(100).scales, y: { ...progressOptions(100).scales.y, max: 100 } } }} label="Line chart of student average scores over time" />
                                </div>
                            )}
                        </Panel>
                        <Panel title="Grade distribution" description="Number of graded results in each score band">
                            {!empty.hasScores ? <EmptyState>No graded results are available for this selection.</EmptyState> : (
                                <div className="mx-auto h-72 max-w-sm">
                                    <ChartCanvas type="doughnut" data={distributionData} options={{ responsive: true, maintainAspectRatio: false, cutout: '62%', plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } } } }} label="Doughnut chart showing grade distribution" />
                                </div>
                            )}
                        </Panel>
                        <Panel title="Quiz performance by question" description="Correct-answer rate per question from graded attempts">
                            {charts.questionPerformance.byQuestion.length === 0 ? <EmptyState>No question-level responses are available for the selected quizzes.</EmptyState> : (
                                <div className="h-72">
                                    <ChartCanvas type="bar" data={questionData} options={{ ...progressOptions(), indexAxis: 'y', scales: { x: { beginAtZero: true, max: 100, grid: { color: '#f1f5f9' } }, y: { grid: { display: false } } } }} label="Horizontal bar chart showing quiz accuracy by question" />
                                </div>
                            )}
                            {charts.questionPerformance.byType.length > 0 && (
                                <div className="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div className="rounded-lg bg-emerald-50 p-3">
                                        <p className="text-xs font-medium text-emerald-800">Highest performing type</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-900">{charts.questionPerformance.highest.type} · {formatPercent(charts.questionPerformance.highest.accuracy)}</p>
                                    </div>
                                    <div className="rounded-lg bg-rose-50 p-3">
                                        <p className="text-xs font-medium text-rose-800">Lowest performing type</p>
                                        <p className="mt-1 text-sm font-semibold text-slate-900">{charts.questionPerformance.lowest.type} · {formatPercent(charts.questionPerformance.lowest.accuracy)}</p>
                                    </div>
                                </div>
                            )}
                            {charts.questionPerformance.byQuestion.length > 0 && (
                                <div className="mt-4 overflow-x-auto rounded-lg border border-slate-100">
                                    <table className="min-w-[560px] w-full text-left text-xs">
                                        <thead className="bg-slate-50 text-slate-500">
                                            <tr>
                                                <th scope="col" className="px-3 py-2">Quiz question</th>
                                                <th scope="col" className="px-3 py-2">Question type</th>
                                                <th scope="col" className="px-3 py-2">Responses</th>
                                                <th scope="col" className="px-3 py-2">Accuracy</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {charts.questionPerformance.byQuestion.map((row) => (
                                                <tr key={row.id}>
                                                    <td className="max-w-sm px-3 py-2 text-slate-700">{row.label}: {row.question}</td>
                                                    <td className="px-3 py-2 text-slate-600">{row.type}</td>
                                                    <td className="px-3 py-2 tabular-nums text-slate-600">{row.responses}</td>
                                                    <td className="px-3 py-2 font-medium tabular-nums text-slate-800">{formatPercent(row.accuracy)}</td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                        </Panel>
                    </div>

                    <Panel title="Student performance" description="Scores are averages of graded results; ungraded students are shown without a percentage.">
                        {students.length === 0 ? <EmptyState>No students match the selected filters.</EmptyState> : (
                            <div className="overflow-x-auto">
                                <table className="min-w-[720px] w-full text-left text-sm">
                                    <thead className="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                        <tr>
                                            <th scope="col" className="px-3 py-3">Student</th>
                                            <th scope="col" className="px-3 py-3">Quiz average</th>
                                            <th scope="col" className="px-3 py-3">Assignment average</th>
                                            <th scope="col" className="px-3 py-3">Overall average</th>
                                            <th scope="col" className="px-3 py-3">Completed</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {students.map((student) => (
                                            <tr key={student.id} className="hover:bg-slate-50">
                                                <th scope="row" className="px-3 py-3 font-medium text-slate-800">
                                                    {student.name}
                                                    <span className="block text-xs font-normal text-slate-500">{student.email}</span>
                                                </th>
                                                <td className="px-3 py-3">{formatPercent(student.quiz_score)}</td>
                                                <td className="px-3 py-3">{formatPercent(student.assignment_score)}</td>
                                                <td className="min-w-48 px-3 py-3">
                                                    <div className="flex items-center gap-3">
                                                        <div className="h-2 flex-1 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label={`${student.name} overall average`} aria-valuenow={student.average_score ?? undefined} aria-valuetext={formatPercent(student.average_score)} aria-valuemin="0" aria-valuemax="100">
                                                            <div className="h-full rounded-full bg-emerald-500" style={{ width: `${Math.max(0, Math.min(100, student.average_score ?? 0))}%` }} />
                                                        </div>
                                                        <span className="w-12 text-right tabular-nums">{formatPercent(student.average_score)}</span>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3 tabular-nums">{student.completed}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </Panel>
                </>
            )}
        </TeacherLayout>
    );
}
