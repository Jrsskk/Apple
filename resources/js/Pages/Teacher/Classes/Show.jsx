import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function Show({ class: schoolClass, availableStudents }) {
    const [showEnroll, setShowEnroll] = useState(false);
    const [selectedStudents, setSelectedStudents] = useState([]);
    const [copied, setCopied] = useState(false);

    const copyClassCode = async () => {
        await navigator.clipboard.writeText(schoolClass.class_code);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    const { data, setData, put, processing } = useForm({
        name: schoolClass.name,
        section: schoolClass.section,
        grade_level: schoolClass.grade_level,
        schedule: schoolClass.schedule || '',
        room: schoolClass.room || '',
    });

    const enroll = () => {
        router.post(`/teacher/classes/${schoolClass.id}/enroll`, { student_ids: selectedStudents }, {
            onSuccess: () => {
                setSelectedStudents([]);
                setShowEnroll(false);
            },
        });
    };

    const unenroll = (studentId) => {
        if (confirm('Remove this student from the class?')) {
            router.delete(`/teacher/classes/${schoolClass.id}/unenroll`, { data: { student_id: studentId } });
        }
    };

    return (
        <TeacherLayout title={schoolClass.display_name || `${schoolClass.name} - ${schoolClass.section}`}>
            <Head title={schoolClass.name} />

            <div className="grid lg:grid-cols-3 gap-6">
                <div className="lg:col-span-2 space-y-6">
                    <form
                        onSubmit={(e) => { e.preventDefault(); put(`/teacher/classes/${schoolClass.id}`); }}
                        className="bg-white rounded-xl border p-5 space-y-4"
                    >
                        <h2 className="font-semibold">Class Details</h2>
                        <div className="grid sm:grid-cols-2 gap-3">
                            <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="rounded-lg border-slate-300" />
                            <input value={data.section} onChange={(e) => setData('section', e.target.value)} className="rounded-lg border-slate-300" />
                            <input value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)} className="rounded-lg border-slate-300" />
                            <input value={data.schedule} onChange={(e) => setData('schedule', e.target.value)} placeholder="Schedule" className="rounded-lg border-slate-300" />
                            <input value={data.room} onChange={(e) => setData('room', e.target.value)} placeholder="Room" className="rounded-lg border-slate-300" />
                        </div>
                        <button type="submit" disabled={processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Save</button>
                    </form>

                    <div className="bg-white rounded-xl border p-5">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="font-semibold">Students ({schoolClass.students?.length || 0})</h2>
                            <button type="button" onClick={() => setShowEnroll(!showEnroll)} className="text-sm text-emerald-600">Enroll Students</button>
                        </div>

                        {showEnroll && (
                            <div className="mb-4 p-4 bg-slate-50 rounded-lg">
                                <select
                                    multiple
                                    value={selectedStudents}
                                    onChange={(e) => setSelectedStudents([...e.target.selectedOptions].map((o) => o.value))}
                                    className="w-full rounded-lg border-slate-300 text-sm h-32"
                                >
                                    {availableStudents.map((s) => (
                                        <option key={s.id} value={s.id}>{s.last_name}, {s.first_name} ({s.email})</option>
                                    ))}
                                </select>
                                <button type="button" onClick={enroll} className="mt-2 px-3 py-1.5 bg-emerald-600 text-white rounded text-sm">Enroll Selected</button>
                            </div>
                        )}

                        <div className="space-y-2">
                            {schoolClass.students?.map((s) => (
                                <div key={s.id} className="flex justify-between items-center p-2 border rounded-lg text-sm">
                                    <span>{s.last_name}, {s.first_name}</span>
                                    <button type="button" onClick={() => unenroll(s.id)} className="text-red-600 text-xs">Remove</button>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                <div className="space-y-4">
                    <div className="bg-white rounded-xl border p-5">
                        <h3 className="font-semibold mb-2">Class Code</h3>
                        <p className="text-xs text-slate-500 mb-3">Share this code so students can join the class on their own.</p>
                        <div className="flex items-center gap-2">
                            <span className="flex-1 font-mono text-lg font-bold tracking-widest text-emerald-700 bg-emerald-50 px-3 py-2 rounded-lg">
                                {schoolClass.class_code || 'Generating…'}
                            </span>
                            <button
                                type="button"
                                onClick={copyClassCode}
                                className="px-3 py-2 text-sm text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-50"
                            >
                                {copied ? 'Copied!' : 'Copy'}
                            </button>
                        </div>
                    </div>
                    <div className="bg-white rounded-xl border p-5">
                        <h3 className="font-semibold mb-2">Subject</h3>
                        <p className="text-sm">{schoolClass.subject?.name}</p>
                    </div>
                    <div className="bg-white rounded-xl border p-5">
                        <h3 className="font-semibold mb-2">Recent Quizzes</h3>
                        {schoolClass.quizzes?.map((q) => (
                            <Link key={q.id} href={`/teacher/quizzes/${q.id}/edit`} className="block text-sm py-1 text-emerald-600">{q.title}</Link>
                        ))}
                    </div>
                    <div className="bg-white rounded-xl border p-5">
                        <h3 className="font-semibold mb-2">Recent Assignments</h3>
                        {schoolClass.assignments?.map((a) => (
                            <Link key={a.id} href={`/teacher/assignments/${a.id}/edit`} className="block text-sm py-1 text-emerald-600">{a.title}</Link>
                        ))}
                    </div>
                </div>
            </div>
        </TeacherLayout>
    );
}
