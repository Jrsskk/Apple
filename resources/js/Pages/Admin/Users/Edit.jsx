import { Head, router, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Edit({ user, roles, statuses }) {
    const { data, setData, put, processing } = useForm({
        employee_number: user.employee_number || '', first_name: user.first_name, middle_name: user.middle_name || '',
        last_name: user.last_name, email: user.email, username: user.username,
        password: '', role: user.role?.value ?? user.role, gender: user.gender || '', status: user.status?.value ?? user.status,
    });

    return (
        <AdminLayout title="Edit User">
            <Head title="Edit User" />
            <form onSubmit={(e) => { e.preventDefault(); put(`/admin/users/${user.id}`); }} className="bg-white rounded-xl border p-6 max-w-2xl space-y-4">
                <div className="grid sm:grid-cols-2 gap-4">
                    <input value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input value={data.username} onChange={(e) => setData('username', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input type="password" placeholder="New password (optional)" value={data.password} onChange={(e) => setData('password', e.target.value)} className="rounded-lg border-slate-300" />
                    <select value={data.role} onChange={(e) => setData('role', e.target.value)} className="rounded-lg border-slate-300">
                        {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                    </select>
                    <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="rounded-lg border-slate-300">
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                </div>
                <div className="flex gap-2">
                    <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
                    <button type="button" onClick={() => router.delete(`/admin/users/${user.id}`)} className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm">Archive</button>
                </div>
            </form>
        </AdminLayout>
    );
}
