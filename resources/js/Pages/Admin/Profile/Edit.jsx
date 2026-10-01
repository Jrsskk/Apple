import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Edit({ user }) {
    const profile = useForm({ first_name: user.first_name, last_name: user.last_name, middle_name: user.middle_name || '', email: user.email, username: user.username || '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });

    return (
        <AdminLayout title="Profile">
            <Head title="Profile" />
            <div className="max-w-xl space-y-6">
                <form onSubmit={(e) => { e.preventDefault(); profile.patch('/admin/profile'); }} className="bg-white rounded-xl border p-6 space-y-4">
                    <h2 className="font-semibold">Profile</h2>
                    <div className="grid sm:grid-cols-2 gap-3">
                        <input value={profile.data.first_name} onChange={(e) => profile.setData('first_name', e.target.value)} className="rounded-lg border-slate-300" required />
                        <input value={profile.data.last_name} onChange={(e) => profile.setData('last_name', e.target.value)} className="rounded-lg border-slate-300" required />
                    </div>
                    <input type="email" value={profile.data.email} onChange={(e) => profile.setData('email', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    <button type="submit" disabled={profile.processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
                </form>
                <form onSubmit={(e) => { e.preventDefault(); password.put('/admin/profile/password'); }} className="bg-white rounded-xl border p-6 space-y-4">
                    <h2 className="font-semibold">Change Password</h2>
                    <input type="password" placeholder="Current password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    <input type="password" placeholder="New password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    <input type="password" placeholder="Confirm" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    <button type="submit" disabled={password.processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Update Password</button>
                </form>
            </div>
        </AdminLayout>
    );
}
