import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Create() {
    const { data, setData, post, processing } = useForm({ name: '', start_date: '', end_date: '' });
    return (
        <AdminLayout title="Create Academic Year">
            <Head title="Create Academic Year" />
            <form onSubmit={(e) => { e.preventDefault(); post('/admin/academic-years'); }} className="bg-white rounded-xl border p-6 max-w-lg space-y-4">
                <input placeholder="Name (e.g. SY 2025-2026)" value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                <input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                <input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create</button>
            </form>
        </AdminLayout>
    );
}
