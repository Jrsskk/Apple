import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatBytes, formatDateTime, statusBadge } from '@/utils/admin';

export default function Index({ backups }) {
    const create = () => { if (confirm('Create a new database backup?')) router.post('/admin/backups'); };
    const restore = (id) => { if (confirm('Restore will overwrite current data. Continue?')) router.post(`/admin/backups/${id}/restore`); };

    return (
        <AdminLayout title="Backups">
            <Head title="Backups" />
            <div className="flex justify-end mb-4">
                <button type="button" onClick={create} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create Backup</button>
            </div>
            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b"><tr><th className="text-left p-3">Filename</th><th className="text-left p-3">Size</th><th className="text-left p-3">Status</th><th className="text-left p-3">Created</th><th className="p-3"></th></tr></thead>
                    <tbody>
                        {backups.data.map((b) => (
                            <tr key={b.id} className="border-b">
                                <td className="p-3 font-medium">{b.filename}</td>
                                <td className="p-3">{formatBytes(b.size)}</td>
                                <td className="p-3"><span className={`px-2 py-0.5 rounded text-xs ${statusBadge(b.status)}`}>{b.status}</span></td>
                                <td className="p-3">{formatDateTime(b.created_at)}</td>
                                <td className="p-3 text-right space-x-2">
                                    <a href={`/admin/backups/${b.id}/download`} className="text-indigo-600">Download</a>
                                    <button type="button" onClick={() => restore(b.id)} className="text-amber-600">Restore</button>
                                    <button type="button" onClick={() => router.delete(`/admin/backups/${b.id}`)} className="text-red-600">Delete</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
