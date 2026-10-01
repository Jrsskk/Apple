import { Head, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Index({ settings, definitions }) {
    const { data, setData, put, processing } = useForm({ settings: { ...settings } });

    const submit = (e) => {
        e.preventDefault();
        put('/admin/settings');
    };

    return (
        <AdminLayout title="System Settings">
            <Head title="Settings" />
            <form onSubmit={submit} className="bg-white rounded-xl border p-6 max-w-2xl space-y-4">
                {definitions.map((def) => (
                    <div key={def.key}>
                        <label className="block text-sm font-medium mb-1">{def.label}</label>
                        {def.type === 'boolean' ? (
                            <select value={data.settings[def.key]} onChange={(e) => setData('settings', { ...data.settings, [def.key]: e.target.value })} className="w-full rounded-lg border-slate-300">
                                <option value="true">Enabled</option><option value="false">Disabled</option>
                            </select>
                        ) : (
                            <input type={def.type === 'number' ? 'number' : 'text'} value={data.settings[def.key] || ''} onChange={(e) => setData('settings', { ...data.settings, [def.key]: e.target.value })} className="w-full rounded-lg border-slate-300" />
                        )}
                    </div>
                ))}
                <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save Settings</button>
            </form>
        </AdminLayout>
    );
}
