import { Head, Link, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';
import { statusBadge } from '@/utils/admin';

export default function Index({ users, filters, roles, statuses }) {
    const search = (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        router.get('/admin/users', Object.fromEntries(fd), { preserveState: true });
    };

    return (
        <AdminLayout title="Users">
            <Head title="Users" />

            <div className="mb-5 flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
                <form onSubmit={search} className="grid flex-1 gap-2 sm:grid-cols-[minmax(180px,1fr)_auto_auto_auto]">
                    <input name="search" defaultValue={filters.search} placeholder="Search name, email, or ID" className="w-full rounded-xl border-slate-300 text-sm" aria-label="Search users" />
                    <select name="role" defaultValue={filters.role || ''} className="w-full rounded-xl border-slate-300 text-sm" aria-label="Filter by role">
                        <option value="">All roles</option>
                        {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                    </select>
                    <select name="status" defaultValue={filters.status || ''} className="w-full rounded-xl border-slate-300 text-sm" aria-label="Filter by status">
                        <option value="">All statuses</option>
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                    <button type="submit" className="portal-btn-secondary">Apply filters</button>
                </form>
                <Link href="/admin/users/create" className="portal-btn-primary">Add new user</Link>
            </div>

            <div className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead className="bg-slate-50 border-b">
                        <tr>
                            <th className="text-left p-3">Name</th>
                            <th className="text-left p-3">Email</th>
                            <th className="text-left p-3">Role</th>
                            <th className="text-left p-3">Status</th>
                            <th className="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {users.data.map((u) => (
                            <tr key={u.id} className="border-b">
                                <td className="p-3 font-medium">{u.first_name} {u.last_name}</td>
                                <td className="p-3">{u.email}</td>
                                <td className="p-3"><span className={`px-2 py-0.5 rounded text-xs ${statusBadge(u.role?.value ?? u.role)}`}>{u.role?.value ?? u.role}</span></td>
                                <td className="p-3"><span className={`px-2 py-0.5 rounded text-xs ${statusBadge(u.status?.value ?? u.status)}`}>{u.status?.value ?? u.status}</span></td>
                                <td className="p-3 text-right space-x-2">
                                    <Link href={`/admin/users/${u.id}/edit`} className="font-semibold text-blue-700">Edit</Link>
                                    <Link href={`/admin/users/${u.id}/reset-password`} method="post" as="button" className="text-amber-600">Reset PW</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    );
}
