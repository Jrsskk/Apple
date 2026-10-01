import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

const gradeOptions = ['Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', 'Grade 12'];

export default function Edit({ subject, teachers, years }) {
    const { data, setData, put, processing } = useForm({
        name: subject.name, description: subject.description || '', grade_level: subject.grade_level || gradeOptions[0],
        teacher_id: subject.teacher_id || '', academic_year_id: subject.academic_year_id, status: subject.status,
    });

    return (
        <AdminLayout title="Edit Subject">
            <Head title="Edit Subject" />
            <form onSubmit={(e) => { e.preventDefault(); put(`/admin/subjects/${subject.id}`); }} className="bg-white rounded-xl border p-6 max-w-2xl space-y-4">
                <div className="grid sm:grid-cols-2 gap-4">
                    <div className="sm:col-span-2">
                        <label className="block text-sm font-medium text-slate-700 mb-1">Name</label>
                        <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Grade Level</label>
                        <select value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)} className="w-full rounded-lg border-slate-300" required>
                            {gradeOptions.map((grade) => <option key={grade} value={grade}>{grade}</option>)}
                        </select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="w-full rounded-lg border-slate-300"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Academic Year</label>
                        <select value={data.academic_year_id} onChange={(e) => setData('academic_year_id', e.target.value)} className="w-full rounded-lg border-slate-300">{years.map((y) => <option key={y.id} value={y.id}>{y.name}</option>)}</select>
                    </div>
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">Teacher</label>
                        <select value={data.teacher_id} onChange={(e) => setData('teacher_id', e.target.value)} className="w-full rounded-lg border-slate-300"><option value="">No teacher</option>{teachers.map((t) => <option key={t.id} value={t.id}>{t.last_name}, {t.first_name}</option>)}</select>
                    </div>
                </div>
                <div>
                    <label className="block text-sm font-medium text-slate-700 mb-1">Description</label>
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} className="w-full rounded-lg border-slate-300" rows={2} />
                </div>
                <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
            </form>
        </AdminLayout>
    );
}
