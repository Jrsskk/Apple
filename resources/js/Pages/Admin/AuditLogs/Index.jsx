import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDateTime } from '@/utils/admin';

export default function Index({ logs, filters, modules, actions }) {
    const filter = (e) => {
        e.preventDefault();
        router.get('/admin/audit-logs', Object.fromEntries(new FormData(e.target)), { preserveState: true });
    };

    return (
        <AdminLayout title="Audit Logs">
            <Head title="Audit Logs" />
            <form onSubmit={filter} className="flex flex-wrap gap-2 mb-4">
                <input name="search" defaultValue={filters.search} placeholder="Search description..." className="rounded-lg border-slate-300 text-sm" />
                <select name="module" defaultValue={filters.module || ''} className="rounded-lg border-slate-300 text-sm"><option value="">All modules</option>{modules.map((m) => <option key={m} value={m}>{m}</option>)}</select>
                <select name="action" defaultValue={filters.action || ''} className="rounded-lg border-slate-300 text-sm"><option value="">All actions</option>{actions.map((a) => <option key={a} value={a}>{a}</option>)}</select>
                <button type="submit" className="px-3 py-1.5 bg-indigo-600 text-white rounded-lg text-sm">Filter</button>
            </form>
            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b"><tr><th className="text-left p-3">User</th><th className="text-left p-3">Action</th><th className="text-left p-3">Module</th><th className="text-left p-3">Description</th><th className="text-left p-3">Date</th></tr></thead>
                    <tbody>
                        {logs.data.map((log) => (
                            <tr key={log.id} className="border-b">
                                <td className="p-3">{log.user ? `${log.user.first_name} ${log.user.last_name}` : 'System'}</td>
                                <td className="p-3">{log.action}</td>
                                <td className="p-3">{log.module}</td>
                                <td className="p-3 max-w-xs truncate">{log.description}</td>
                                <td className="p-3 whitespace-nowrap">{formatDateTime(log.created_at)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
