import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDateTime, statusBadge } from '@/utils/admin';

export default function Index({ queue, logs, stats }) {
    return (
        <AdminLayout title="Sync Logs">
            <Head title="Sync Logs" />
            <div className="grid sm:grid-cols-4 gap-4 mb-6">
                {[['Pending', stats.pending], ['Syncing', stats.syncing], ['Synced', stats.synced], ['Failed', stats.failed]].map(([l, v]) => (
                    <div key={l} className="bg-white rounded-xl border p-4"><p className="text-sm text-slate-500">{l}</p><p className="text-2xl font-bold">{v}</p></div>
                ))}
            </div>
            <div className="grid lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-3">Sync Queue</h2>
                    {queue.data.map((item) => (
                        <div key={item.id} className="text-sm border-b py-2">
                            <div className="flex justify-between"><span>{item.user?.first_name} {item.user?.last_name}</span><span className={`px-2 py-0.5 rounded text-xs ${statusBadge(item.status?.value ?? item.status)}`}>{item.status?.value ?? item.status}</span></div>
                            <p className="text-xs text-slate-500">{item.entity_type} · {item.action}</p>
                        </div>
                    ))}
                </div>
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-3">Sync Logs</h2>
                    {logs.data.map((log) => (
                        <div key={log.id} className="text-sm border-b py-2">
                            <p>{log.message}</p>
                            <p className="text-xs text-slate-500">{log.user?.first_name} · {formatDateTime(log.created_at)} · {log.status}</p>
                        </div>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
