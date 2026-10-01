import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Index({ systemUsage, academicSummary }) {
    return (
        <AdminLayout title="Reports">
            <Head title="Reports" />
            <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <a href="/admin/reports/system/export" className="bg-white rounded-xl border p-4 hover:border-indigo-300 transition"><p className="font-medium">System Report</p><p className="text-xs text-slate-500 mt-1">Download CSV</p></a>
                <a href="/admin/reports/academic/export" className="bg-white rounded-xl border p-4 hover:border-indigo-300 transition"><p className="font-medium">Academic Report</p><p className="text-xs text-slate-500 mt-1">Download CSV</p></a>
                <a href="/admin/reports/users/export" className="bg-white rounded-xl border p-4 hover:border-indigo-300 transition"><p className="font-medium">All Users</p><p className="text-xs text-slate-500 mt-1">Download CSV</p></a>
                <a href="/admin/reports/sync/export" className="bg-white rounded-xl border p-4 hover:border-indigo-300 transition"><p className="font-medium">Sync Logs</p><p className="text-xs text-slate-500 mt-1">Download CSV</p></a>
            </div>
            <div className="grid lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-4">System Usage</h2>
                    <div className="grid grid-cols-2 gap-3 text-sm">
                        {Object.entries(systemUsage).map(([k, v]) => (
                            <div key={k} className="p-2 bg-slate-50 rounded"><span className="capitalize">{k.replace(/_/g, ' ')}</span>: <strong>{v}</strong></div>
                        ))}
                    </div>
                </div>
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-4">Academic Summary</h2>
                    <div className="space-y-2 max-h-80 overflow-y-auto text-sm">
                        {academicSummary.map((row, i) => (
                            <div key={i} className="border-b pb-2">
                                <p className="font-medium">{row.class}</p>
                                <p className="text-xs text-slate-500">{row.subject} · {row.teacher} · {row.students} students</p>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}
