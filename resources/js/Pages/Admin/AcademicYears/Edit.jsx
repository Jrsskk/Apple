import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Edit({ year }) {
    const { data, setData, put, processing } = useForm({
        name: year.name, start_date: year.start_date?.slice(0, 10), end_date: year.end_date?.slice(0, 10), status: year.status,
    });
    return (
        <AdminLayout title="Edit Academic Year">
            <Head title="Edit Academic Year" />
            <form onSubmit={(e) => { e.preventDefault(); put(`/admin/academic-years/${year.id}`); }} className="bg-white rounded-xl border p-6 max-w-lg space-y-4">
                <input value={data.name} onChange={(e) => setData('name', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                <input type="date" value={data.start_date} onChange={(e) => setData('start_date', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                <input type="date" value={data.end_date} onChange={(e) => setData('end_date', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="w-full rounded-lg border-slate-300">
                    <option value="open">Open</option><option value="closed">Closed</option>
                </select>
                <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
            </form>
        </AdminLayout>
    );
}
