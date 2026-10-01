import { Head, Link, useForm } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

const sectionOptions = ['A', 'B', 'C', 'D'];
const gradeOptions = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
const roomOptions = ['Room 1', 'Room 2', 'Room 3'];

export default function Create({ subjects, academicYears }) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        section: '',
        grade_level: '',
        subject_id: subjects[0]?.id || '',
        academic_year_id: academicYears.find((y) => y.is_active)?.id || academicYears[0]?.id || '',
        schedule: '',
        room: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/teacher/classes');
    };

    return (
        <TeacherLayout title="Create Class">
            <Head title="Create Class" />

            <div className="mb-4">
                <Link href="/teacher/classes" className="text-sm text-slate-600 hover:text-slate-800">← Back to classes</Link>
            </div>

            <form onSubmit={submit} noValidate className="portal-card mb-6 space-y-4 p-4 sm:p-6 max-w-3xl">
                <div>
                    <h2 className="font-semibold text-slate-900">Create a class</h2>
                    <p className="text-sm text-slate-500">Set the class details and connect it to a subject.</p>
                </div>

                <div className="grid sm:grid-cols-2 gap-4">
                    <input type="text" placeholder="Class name" value={data.name} onChange={(e) => setData('name', e.target.value)} className="rounded-lg border-slate-300" required />

                    <select value={data.section} onChange={(e) => setData('section', e.target.value)} className="rounded-lg border-slate-300" required>
                        <option value="">Select section</option>
                        {sectionOptions.map((section) => <option key={section} value={section}>{section}</option>)}
                    </select>

                    <select value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)} className="rounded-lg border-slate-300" required>
                        <option value="">Select grade level</option>
                        {gradeOptions.map((grade) => <option key={grade} value={grade}>{grade}</option>)}
                    </select>

                    <select value={data.subject_id} onChange={(e) => setData('subject_id', e.target.value)} className="rounded-lg border-slate-300" required>
                        <option value="">Select subject</option>
                        {subjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                    </select>

                    <select value={data.academic_year_id} onChange={(e) => setData('academic_year_id', e.target.value)} className="rounded-lg border-slate-300" required>
                        <option value="">Select academic year</option>
                        {academicYears.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                    </select>

                    <input type="text" placeholder="Schedule" value={data.schedule} onChange={(e) => setData('schedule', e.target.value)} className="rounded-lg border-slate-300" />

                    <select value={data.room} onChange={(e) => setData('room', e.target.value)} className="rounded-lg border-slate-300">
                        <option value="">Select room</option>
                        {roomOptions.map((room) => <option key={room} value={room}>{room}</option>)}
                    </select>
                </div>

                {Object.values(errors).map((error, index) => (
                    <p key={`${error}-${index}`} className="text-red-600 text-sm">{error}</p>
                ))}

                <button type="submit" disabled={processing} className="portal-btn-primary disabled:opacity-60">
                    {processing ? 'Creating…' : 'Create class'}
                </button>
            </form>
        </TeacherLayout>
    );
}
