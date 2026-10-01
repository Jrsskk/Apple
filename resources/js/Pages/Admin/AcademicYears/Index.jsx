import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { statusBadge } from '@/utils/admin';

export default function Index({ years }) {
    return (
        <AdminLayout title="Academic Years">
            <Head title="Academic Years" />
            <div className="flex justify-end mb-4">
                <Link href="/admin/academic-years/create" className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create Year</Link>
            </div>
            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b"><tr><th className="text-left p-3">Name</th><th className="text-left p-3">Period</th><th className="text-left p-3">Status</th><th className="p-3"></th></tr></thead>
                    <tbody>
                        {years.data.map((y) => (
                            <tr key={y.id} className="border-b">
                                <td className="p-3 font-medium">{y.name} {y.is_active && <span className="text-xs text-emerald-600">(Active)</span>}</td>
                                <td className="p-3">{y.start_date?.slice(0, 10)} — {y.end_date?.slice(0, 10)}</td>
                                <td className="p-3"><span className={`px-2 py-0.5 rounded text-xs ${statusBadge(y.status)}`}>{y.status}</span></td>
                                <td className="p-3 text-right space-x-2">
                                    <Link href={`/admin/academic-years/${y.id}/edit`} className="text-indigo-600">Edit</Link>
                                    {!y.is_active && <button type="button" onClick={() => router.post(`/admin/academic-years/${y.id}/activate`)} className="text-emerald-600">Activate</button>}
                                    {y.status !== 'closed' && <button type="button" onClick={() => router.post(`/admin/academic-years/${y.id}/close`)} className="text-amber-600">Close</button>}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
