import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatBytes } from '@/utils/admin';

export default function Index({ materials, submissions, storageSize }) {
    return (
        <AdminLayout title="File Management">
            <Head title="Files" />
            <div className="bg-white rounded-xl border p-4 mb-6">
                <p className="text-sm text-slate-500">Total storage used</p>
                <p className="text-2xl font-bold text-indigo-600">{formatBytes(storageSize)}</p>
            </div>
            <div className="grid lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-3">Learning Materials</h2>
                    {materials.data.map((m) => (
                        <div key={m.id} className="flex justify-between items-center text-sm border-b py-2">
                            <div><p className="font-medium">{m.title}</p><p className="text-xs text-slate-500">{m.original_file_name || m.file_type}{m.file_size ? ` · ${formatBytes(m.file_size)}` : ''}</p></div>
                            <div className="space-x-2">
                                <a href={`/admin/files/materials/${m.id}/download`} className="text-indigo-600">Download</a>
                                <button type="button" onClick={() => router.delete(`/admin/files/materials/${m.id}`)} className="text-red-600">Delete</button>
                            </div>
                        </div>
                    ))}
                </div>
                <div className="bg-white rounded-xl border p-5">
                    <h2 className="font-semibold mb-3">Assignment Submissions</h2>
                    {submissions.data.map((s) => (
                        <div key={s.id} className="flex justify-between items-center text-sm border-b py-2">
                            <div><p className="font-medium">{s.student?.first_name} {s.student?.last_name}</p><p className="text-xs text-slate-500">{s.assignment?.title}</p><p className="text-xs text-slate-500">{s.file_name || ''}{s.file_size ? ` · ${formatBytes(s.file_size)}` : ''}</p></div>
                            <a href={`/admin/files/submissions/${s.id}/download`} className="text-indigo-600">Download</a>
                        </div>
                    ))}
                </div>
            </div>
        </AdminLayout>
    );
}
