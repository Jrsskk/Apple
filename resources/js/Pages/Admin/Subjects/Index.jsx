import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Index({ subjects }) {
    const handleDelete = (subjectId) => {
        if (window.confirm('Delete this subject?')) {
            router.delete(`/admin/subjects/${subjectId}`);
        }
    };

    return (
        <AdminLayout title="Subjects">
            <Head title="Subjects" />
            <div className="flex justify-end mb-4"><Link href="/admin/subjects/create" className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create Subject</Link></div>
            <div className="bg-white rounded-xl border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b"><tr><th className="text-left p-3">Name</th><th className="text-left p-3">Grade</th><th className="text-left p-3">Teacher</th><th className="text-left p-3">Year</th><th className="p-3"></th></tr></thead>
                    <tbody>
                        {subjects.data.map((s) => (
                            <tr key={s.id} className="border-b">
                                <td className="p-3 font-medium">{s.name}</td>
                                <td className="p-3">{s.grade_level}</td>
                                <td className="p-3">{s.teacher ? `${s.teacher.first_name} ${s.teacher.last_name}` : '—'}</td>
                                <td className="p-3">{s.academic_year?.name}</td>
                                <td className="p-3 text-right">
                                    <div className="flex items-center justify-end gap-3">
                                        <Link href={`/admin/subjects/${s.id}/edit`} className="text-indigo-600">Edit</Link>
                                        <button type="button" onClick={() => handleDelete(s.id)} className="text-red-600 hover:text-red-700">Delete</button>
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
