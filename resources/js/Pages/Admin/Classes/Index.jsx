import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Index({ classes }) {
    const handleDelete = (classId) => {
        if (window.confirm('Delete this class?')) {
            router.delete(`/admin/classes/${classId}`);
        }
    };

    return (
        <AdminLayout title="Classes">
            <Head title="Classes" />
            <div className="flex justify-end mb-4"><Link href="/admin/classes/create" className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create Class</Link></div>
            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b"><tr><th className="text-left p-3">Class</th><th className="text-left p-3">Subject</th><th className="text-left p-3">Teacher</th><th className="text-left p-3">Students</th><th className="p-3"></th></tr></thead>
                    <tbody>
                        {classes.data.map((c) => (
                            <tr key={c.id} className="border-b">
                                <td className="p-3 font-medium">{c.name} - {c.section}</td>
                                <td className="p-3">{c.subject?.name}</td>
                                <td className="p-3">{c.teacher ? `${c.teacher.first_name} ${c.teacher.last_name}` : '—'}</td>
                                <td className="p-3">{c.students_count}</td>
                                <td className="p-3 text-right">
                                    <div className="flex items-center justify-end gap-3">
                                        <Link href={`/admin/classes/${c.id}`} className="text-indigo-600">Manage</Link>
                                        <button type="button" onClick={() => handleDelete(c.id)} className="text-red-600 hover:text-red-700">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
