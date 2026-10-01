import { Head, router, useForm } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function Edit({ user }) {
    const profileForm = useForm({
        first_name: user.first_name || '',
        last_name: user.last_name || '',
        middle_name: user.middle_name || '',
        email: user.email || '',
        username: user.username || '',
    });

    const passwordForm = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const avatarForm = useForm({ avatar: null });

    return (
        <TeacherLayout title="Profile">
            <Head title="Profile" />

            <div className="max-w-2xl space-y-6">
                <form
                    onSubmit={(e) => { e.preventDefault(); profileForm.patch('/teacher/profile'); }}
                    className="bg-white rounded-xl border p-6 space-y-4"
                >
                    <h2 className="font-semibold">Profile Information</h2>
                    {user.employee_number && (
                        <p className="text-sm text-slate-500">Employee #: {user.employee_number}</p>
                    )}
                    <div className="grid sm:grid-cols-2 gap-4">
                        <input value={profileForm.data.first_name} onChange={(e) => profileForm.setData('first_name', e.target.value)} placeholder="First name" className="rounded-lg border-slate-300" required />
                        <input value={profileForm.data.last_name} onChange={(e) => profileForm.setData('last_name', e.target.value)} placeholder="Last name" className="rounded-lg border-slate-300" required />
                        <input value={profileForm.data.middle_name} onChange={(e) => profileForm.setData('middle_name', e.target.value)} placeholder="Middle name" className="rounded-lg border-slate-300" />
                        <input value={profileForm.data.username} onChange={(e) => profileForm.setData('username', e.target.value)} placeholder="Username" className="rounded-lg border-slate-300" />
                    </div>
                    <input type="email" value={profileForm.data.email} onChange={(e) => profileForm.setData('email', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    <button type="submit" disabled={profileForm.processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Save Profile</button>
                </form>

                <form
                    onSubmit={(e) => {
                        e.preventDefault();
                        avatarForm.post('/teacher/profile/avatar', { forceFormData: true });
                    }}
                    className="bg-white rounded-xl border p-6 space-y-4"
                >
                    <h2 className="font-semibold">Profile Photo</h2>
                    {user.profile_image && (
                        <img src={user.profile_image_url} alt="Profile" className="w-20 h-20 rounded-full object-cover" />
                    )}
                    {user.profile_image_file_size && <p className="text-xs text-slate-500">{(user.profile_image_file_size / 1048576).toFixed(2)} MB</p>}
                    <input type="file" accept="image/*" onChange={(e) => avatarForm.setData('avatar', e.target.files[0])} className="text-sm" />
                    {avatarForm.data.avatar && <p className="text-xs text-slate-500">{avatarForm.data.avatar.name} · {(avatarForm.data.avatar.size / 1048576).toFixed(2)} MB</p>}
                    {avatarForm.errors.avatar && <p role="alert" className="text-sm text-red-600">{avatarForm.errors.avatar}</p>}
                    <div className="flex gap-2">
                        <button type="submit" disabled={avatarForm.processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">{avatarForm.processing ? 'Uploading…' : 'Upload Photo'}</button>
                        {user.profile_image && <button type="button" onClick={() => router.delete('/teacher/profile/avatar')} className="px-4 py-2 text-red-600 text-sm">Delete Photo</button>}
                    </div>
                </form>

                <form
                    onSubmit={(e) => { e.preventDefault(); passwordForm.put('/teacher/profile/password'); }}
                    className="bg-white rounded-xl border p-6 space-y-4"
                >
                    <h2 className="font-semibold">Change Password</h2>
                    <input type="password" value={passwordForm.data.current_password} onChange={(e) => passwordForm.setData('current_password', e.target.value)} placeholder="Current password" className="w-full rounded-lg border-slate-300" required />
                    <input type="password" value={passwordForm.data.password} onChange={(e) => passwordForm.setData('password', e.target.value)} placeholder="New password" className="w-full rounded-lg border-slate-300" required />
                    <input type="password" value={passwordForm.data.password_confirmation} onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)} placeholder="Confirm password" className="w-full rounded-lg border-slate-300" required />
                    <button type="submit" disabled={passwordForm.processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Update Password</button>
                </form>
            </div>
        </TeacherLayout>
    );
}
