import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime } from '@/utils/teacher';

export default function Index({ announcements, classes }) {
    const [showForm, setShowForm] = useState(false);
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        message: '',
        target_audience: 'students',
        school_class_id: '',
        expires_at: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post('/teacher/announcements', { onSuccess: () => { reset(); setShowForm(false); } });
    };

    const destroy = (id) => {
        if (confirm('Delete this announcement?')) router.delete(`/teacher/announcements/${id}`);
    };

    return (
        <TeacherLayout title="Announcements">
            <Head title="Announcements" />

            <div className="flex justify-end mb-4">
                <button type="button" onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">
                    {showForm ? 'Cancel' : 'Post Announcement'}
                </button>
            </div>

            {showForm && (
                <form onSubmit={submit} className="bg-white rounded-xl border p-5 mb-6 space-y-4">
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Title" className="w-full rounded-lg border-slate-300" required />
                    <textarea value={data.message} onChange={(e) => setData('message', e.target.value)} placeholder="Message" rows={4} className="w-full rounded-lg border-slate-300" required />
                    <div className="grid sm:grid-cols-2 gap-4">
                        <select value={data.target_audience} onChange={(e) => setData('target_audience', e.target.value)} className="rounded-lg border-slate-300">
                            <option value="students">Students</option>
                            <option value="all">Everyone</option>
                        </select>
                        <select value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)} className="rounded-lg border-slate-300">
                            <option value="">All my classes</option>
                            {classes.map((c) => <option key={c.id} value={c.id}>{c.name} - {c.section}</option>)}
                        </select>
                    </div>
                    <input type="datetime-local" value={data.expires_at} onChange={(e) => setData('expires_at', e.target.value)} className="rounded-lg border-slate-300" />
                    <button type="submit" disabled={processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Post</button>
                </form>
            )}

            <div className="space-y-4">
                {announcements.data.map((a) => (
                    <div key={a.id} className="bg-white rounded-xl border p-5">
                        <div className="flex justify-between items-start">
                            <div>
                                <h3 className="font-semibold">{a.title}</h3>
                                <p className="text-sm text-slate-500 mt-1">{formatDateTime(a.published_at)} · {a.school_class ? `${a.school_class.name}` : 'All classes'}</p>
                            </div>
                            <button type="button" onClick={() => destroy(a.id)} className="text-red-600 text-xs">Delete</button>
                        </div>
                        <p className="text-sm mt-3 whitespace-pre-wrap">{a.message}</p>
                    </div>
                ))}
            </div>
        </TeacherLayout>
    );
}
