import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Create({ roles, statuses }) {
    const { data, setData, post, processing, errors } = useForm({
        employee_number: '', first_name: '', middle_name: '', last_name: '',
        email: '', username: '', password: '', role: 'student', gender: '', status: 'active',
    });

    return (
        <AdminLayout title="Create User">
            <Head title="Create User" />
            <form onSubmit={(e) => { e.preventDefault(); post('/admin/users'); }} className="bg-white rounded-xl border p-6 max-w-2xl space-y-4">
                <div className="grid sm:grid-cols-2 gap-4">
                    <input placeholder="First name" value={data.first_name} onChange={(e) => setData('first_name', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input placeholder="Last name" value={data.last_name} onChange={(e) => setData('last_name', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input placeholder="Email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input placeholder="Username" value={data.username} onChange={(e) => setData('username', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input placeholder="Password" type="password" value={data.password} onChange={(e) => setData('password', e.target.value)} className="rounded-lg border-slate-300" required />
                    <input placeholder="Employee #" value={data.employee_number} onChange={(e) => setData('employee_number', e.target.value)} className="rounded-lg border-slate-300" />
                    <select value={data.role} onChange={(e) => setData('role', e.target.value)} className="rounded-lg border-slate-300">
                        {roles.map((r) => <option key={r.value} value={r.value}>{r.label}</option>)}
                    </select>
                    <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="rounded-lg border-slate-300">
                        {statuses.map((s) => <option key={s.value} value={s.value}>{s.label}</option>)}
                    </select>
                </div>
                {errors.email && <p className="text-red-600 text-sm">{errors.email}</p>}
                <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create User</button>
            </form>
        </AdminLayout>
    );
}
