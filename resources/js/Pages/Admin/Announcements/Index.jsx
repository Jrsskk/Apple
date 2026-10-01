import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatDateTime } from '@/utils/admin';

export default function Index({ announcements, classes }) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '', message: '', target_audience: 'all', school_class_id: '', expires_at: '',
    });

    return (
        <AdminLayout title="Announcements">
            <Head title="Announcements" />
            <div className="flex justify-end mb-4">
                <button type="button" onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">{showForm ? 'Cancel' : 'Post Announcement'}</button>
            </div>
            {showForm && (
                <form onSubmit={(e) => { e.preventDefault(); post('/admin/announcements', { onSuccess: () => { reset(); setShowForm(false); } }); }} className="bg-white rounded-xl border p-5 mb-6 space-y-3">
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Title" className="w-full rounded-lg border-slate-300" required />
                    <textarea value={data.message} onChange={(e) => setData('message', e.target.value)} placeholder="Message" rows={3} className="w-full rounded-lg border-slate-300" required />
                    <div className="grid sm:grid-cols-2 gap-4">
                        <select value={data.target_audience} onChange={(e) => setData('target_audience', e.target.value)} className="w-full rounded-lg border-slate-300">
                            <option value="all">Everyone</option><option value="students">Students</option><option value="teachers">Teachers</option>
                        </select>
                        <select value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)} className="w-full rounded-lg border-slate-300">
                            <option value="">All classes</option>
                            {classes.map((c) => <option key={c.id} value={c.id}>{c.name} - {c.section}</option>)}
                        </select>
                    </div>
                    <input type="datetime-local" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} className="w-full rounded-lg border-slate-300" />
                    <button type="submit" disabled={processing} className="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Post</button>
                </form>
            )}
            <div className="space-y-4">
                {announcements.data.map((a) => (
                    <div key={a.id} className="bg-white rounded-xl border p-5">
                        <div className="flex justify-between"><div><h3 className="font-semibold">{a.title}</h3><p className="text-xs text-slate-500">{formatDateTime(a.published_at)}</p></div>
                            <button type="button" onClick={() => router.delete(`/admin/announcements/${a.id}`)} className="text-red-600 text-xs">Delete</button></div>
                        <p className="text-sm mt-2">{a.message}</p>
                    </div>
                ))}
            </div>
        </AdminLayout>
    );
}
