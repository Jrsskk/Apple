import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Show({ class: schoolClass, availableStudents }) {
    const [selected, setSelected] = useState([]);
    const { data, setData, put, processing } = useForm({
        name: schoolClass.name, section: schoolClass.section, grade_level: schoolClass.grade_level,
        teacher_id: schoolClass.teacher_id, schedule: schoolClass.schedule || '', room: schoolClass.room || '',
    });

    const enroll = () => router.post(`/admin/classes/${schoolClass.id}/enroll`, { student_ids: selected }, { onSuccess: () => setSelected([]) });
    const unenroll = (id) => router.delete(`/admin/classes/${schoolClass.id}/unenroll`, { data: { student_id: id } });

    return (
        <AdminLayout title={`${schoolClass.name} - ${schoolClass.section}`}>
            <Head title={schoolClass.name} />
            <div className="grid lg:grid-cols-2 gap-6">
                <form onSubmit={(e) => { e.preventDefault(); put(`/admin/classes/${schoolClass.id}`); }} className="bg-white rounded-xl border p-5 space-y-3">
                    <h2 className="font-semibold">Class Details</h2>
                    <div className="p-3 bg-indigo-50 rounded-lg">
                        <p className="text-xs text-slate-500 mb-1">Class Code</p>
                        <p className="font-mono text-lg font-bold tracking-widest text-indigo-700">{schoolClass.class_code}</p>
                    </div>
                    <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-lg border-slate-300" />
                    <input value={data.section} onChange={(e) => setData('section', e.target.value)} className="w-full rounded-lg border-slate-300" />
                    <input value={data.grade_level} onChange={(e) => setData('grade_level', e.target.value)} className="w-full rounded-lg border-slate-300" />
                    <input value={data.schedule} onChange={(e) => setData('schedule', e.target.value)} placeholder="Schedule" className="w-full rounded-lg border-slate-300" />
                    <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
                </form>
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-3">Students ({schoolClass.students?.length || 0})</h2>
                    <select multiple value={selected} onChange={(e) => setSelected([...e.target.selectedOptions].map((o) => o.value))} className="w-full rounded-lg border-slate-300 text-sm h-24 mb-2">
                        {availableStudents.map((s) => <option key={s.id} value={s.id}>{s.last_name}, {s.first_name}</option>)}
                    </select>
                    <button type="button" onClick={enroll} className="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm mb-4">Enroll Selected</button>
                    <div className="space-y-2 max-h-48 overflow-y-auto">
                        {schoolClass.students?.map((s) => (
                            <div key={s.id} className="flex justify-between text-sm border rounded p-2">
                                <span>{s.last_name}, {s.first_name}</span>
                                <button type="button" onClick={() => unenroll(s.id)} className="text-red-600 text-xs">Remove</button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
