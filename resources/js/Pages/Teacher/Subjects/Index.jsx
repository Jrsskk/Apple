import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';

const gradeOptions = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

export default function Index({ subjects, academicYears }) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        name: '',
        description: '',
        grade_level: gradeOptions[0],
        academic_year_id: academicYears.find((y) => y.is_active)?.id || academicYears[0]?.id || '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/teacher/subjects', { onSuccess: () => { reset(); setShowForm(false); } });
    };

    return (
        <TeacherLayout title="Subjects">
            <Head title="Subjects" />

            <div className="flex justify-end mb-4">
                <button type="button" onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">
                    {showForm ? 'Cancel' : 'Create Subject'}
                </button>
            </div>

            {showForm && (
                <form onSubmit={submit} className="bg-white rounded-xl border p-5 mb-6 space-y-4">
                    <div className="grid sm:grid-cols-2 gap-4">
                        <div className="sm:col-span-2">
                            <label className="block text-sm font-medium text-slate-700 mb-1">Name</label>
                            <input placeholder="Subject name" value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Grade Level</label>
                            <select value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)} className="w-full rounded-lg border-slate-300" required>
                                {gradeOptions.map((grade) => <option key={grade} value={grade}>{grade}</option>)}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Academic Year</label>
                            <select value={data.academic_year_id} onChange={(e) => setData('academic_year_id', e.target.value)} className="w-full rounded-lg border-slate-300" required>
                                <option value="">Select academic year</option>
                                {academicYears.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}
                            </select>
                        </div>

                        <div className="sm:col-span-2">
                            <label className="block text-sm font-medium text-slate-700 mb-1">Description</label>
                            <textarea placeholder="Description" value={data.description} onChange={(e) => setData('description', e.target.value)} className="w-full rounded-lg border-slate-300" rows={2} />
                        </div>
                    </div>

                    <button type="submit" disabled={processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm w-fit">Create</button>
                </form>
            )}

            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {subjects.map((s) => (
                    <div key={s.id} className="bg-white rounded-xl border p-5">
                        <p className="text-xs text-emerald-600 font-medium">{s.code}</p>
                        <h3 className="font-semibold mt-1">{s.name}</h3>
                        <p className="text-sm text-slate-500 mt-1">{s.grade_level} · {s.academic_year?.name}</p>
                        <p className="text-xs text-slate-400 mt-2">{s.classes_count} classes</p>
                    </div>
                ))}
            </div>
        </TeacherLayout>
    );
}
