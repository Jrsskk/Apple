import { Head, useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const sectionOptions = ['A', 'B', 'C', 'D'];
const gradeOptions = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];
const roomOptions = ['Room 1', 'Room 2', 'Room 3'];

export default function Create({ subjects, teachers, years }) {
    const { data, setData, post, processing } = useForm({
        name: '',
        section: '',
        grade_level: '',
        subject_id: '',
        teacher_id: '',
        academic_year_id: years[0]?.id || '',
        schedule: '',
        room: '',
    });

    const teacherSubjects = subjects.filter((subject) => {
        if (!data.teacher_id) {
            return true;
        }

        return !subject.teacher_id || Number(subject.teacher_id) === Number(data.teacher_id);
    });

    useEffect(() => {
        const selectedSubjectExists = teacherSubjects.some((subject) => Number(subject.id) === Number(data.subject_id));

        if (data.subject_id && !selectedSubjectExists) {
            setData('subject_id', '');
        }
    }, [data.teacher_id, data.subject_id, teacherSubjects, setData]);

    return (
        <AdminLayout title="Create Class">
            <Head title="Create Class" />
            <form onSubmit={(e) => { e.preventDefault(); post('/admin/classes'); }} className="bg-white rounded-xl border p-6 max-w-2xl grid sm:grid-cols-2 gap-4">
                <input placeholder="Class name" value={data.name} onChange={(e) => setData('name', e.target.value)} className="rounded-lg border-slate-300" required />

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
                    {teacherSubjects.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>

                <select value={data.teacher_id} onChange={(e) => setData('teacher_id', e.target.value)} className="rounded-lg border-slate-300" required>
                    <option value="">Select teacher</option>
                    {teachers.map((t) => <option key={t.id} value={t.id}>{t.last_name}, {t.first_name}</option>)}
                </select>

                <select value={data.academic_year_id} onChange={(e) => setData('academic_year_id', e.target.value)} className="rounded-lg border-slate-300" required>
                    {years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                </select>

                <input placeholder="Schedule" value={data.schedule} onChange={(e) => setData('schedule', e.target.value)} className="rounded-lg border-slate-300" />

                <select value={data.room} onChange={(e) => setData('room', e.target.value)} className="rounded-lg border-slate-300">
                    <option value="">Select room</option>
                    {roomOptions.map((room) => <option key={room} value={room}>{room}</option>)}
                </select>

                <button type="submit" disabled={processing} className="sm:col-span-2 px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm w-fit">Create</button>
            </form>
        </AdminLayout>
    );
}
