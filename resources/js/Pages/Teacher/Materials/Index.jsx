import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function Index({ materials, classes }) {
    const [showForm, setShowForm] = useState(false);
    const [uploadStatus, setUploadStatus] = useState('');
    const [replaceStatus, setReplaceStatus] = useState('');
    const { data, setData, post, processing, reset, errors } = useForm({
        title: '',
        description: '',
        school_class_id: classes[0]?.id || '',
        subject_id: classes[0]?.subject_id || '',
        file: null,
    });

    const onClassChange = (classId) => {
        const cls = classes.find((c) => c.id === parseInt(classId, 10));
        setData((prev) => ({ ...prev, school_class_id: classId, subject_id: cls?.subject_id || prev.subject_id }));
    };

    const submit = (e) => {
        e.preventDefault();
        post('/teacher/materials', {
            forceFormData: true,
            onStart: () => setUploadStatus('Uploading to Supabase Storage…'),
            onSuccess: () => { reset(); setShowForm(false); setUploadStatus('Upload complete.'); },
            onError: () => setUploadStatus('Upload failed. Check the file and try again.'),
        });
    };

    const destroy = (id) => {
        if (confirm('Delete this material?')) router.delete(`/teacher/materials/${id}`);
    };

    return (
        <TeacherLayout title="Learning Materials">
            <Head title="Materials" />

            <div className="flex justify-end mb-4">
                <button type="button" onClick={() => setShowForm(!showForm)} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">
                    {showForm ? 'Cancel' : 'Upload Material'}
                </button>
            </div>

            {showForm && (
                <form onSubmit={submit} className="bg-white rounded-xl border p-5 mb-6 space-y-4">
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Title" className="w-full rounded-lg border-slate-300" required />
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Description" className="w-full rounded-lg border-slate-300" rows={2} />
                    <select value={data.school_class_id} onChange={(e) => onClassChange(e.target.value)} className="w-full rounded-lg border-slate-300">
                        {classes.map((c) => <option key={c.id} value={c.id}>{c.name} - {c.section}</option>)}
                    </select>
                    <input type="file" accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.ppt,.pptx,.pps,.ppsx,.xls,.xlsx,.csv,.mp4,.mov,.avi,.webm,.jpg,.jpeg,.png,.gif,.webp" onChange={(e) => setData('file', e.target.files[0])} required className="text-sm" />
                    {data.file && <p className="text-xs text-slate-500">{data.file.name} · {(data.file.size / 1048576).toFixed(2)} MB</p>}
                    {errors.file && <p role="alert" className="text-sm text-red-600">{errors.file}</p>}
                    {uploadStatus && <p role="status" className="text-sm text-slate-600">{uploadStatus}</p>}
                    <button type="submit" disabled={processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">{processing ? 'Uploading…' : 'Upload'}</button>
                </form>
            )}

            <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                {materials.data.map((m) => (
                    <div key={m.id} className="bg-white rounded-xl border p-5">
                        <h3 className="font-semibold">{m.title}</h3>
                        <p className="text-sm text-slate-500 mt-1">{m.school_class?.name} · {m.file_type} · {m.file_size ? `${(m.file_size / 1048576).toFixed(1)} MB` : ''}</p>
                        {m.original_file_name && <p className="text-xs text-slate-500 mt-1 break-all">{m.original_file_name}</p>}
                        {errors.file && <p role="alert" className="text-xs text-red-600 mt-2">{errors.file}</p>}
                        <div className="flex gap-3 mt-3">
                            <a href={`/teacher/materials/${m.id}/download?download=0`} target="_blank" rel="noreferrer" className="text-sm text-emerald-600">View</a>
                            <a href={`/teacher/materials/${m.id}/download`} className="text-sm text-emerald-600">Download</a>
                            <label className="text-sm text-indigo-600 cursor-pointer">
                                Replace
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.ppt,.pptx,.pps,.ppsx,.xls,.xlsx,.csv,.mp4,.mov,.avi,.webm,.jpg,.jpeg,.png,.gif,.webp"
                                    className="hidden"
                                    onChange={(e) => {
                                        const file = e.target.files[0];
                                        if (file) {
                                            setReplaceStatus(`Replacing ${m.original_file_name || m.title}…`);
                                            router.post(`/teacher/materials/${m.id}/replace`, {
                                                title: m.title,
                                                description: m.description || '',
                                                school_class_id: m.school_class_id,
                                                subject_id: m.subject_id,
                                                file,
                                            }, {
                                                forceFormData: true,
                                                onSuccess: () => setReplaceStatus('File replaced.'),
                                                onError: (replaceErrors) => setReplaceStatus(replaceErrors.file || 'Replacement failed.'),
                                            });
                                        }
                                    }}
                                />
                            </label>
                            <button type="button" onClick={() => destroy(m.id)} className="text-sm text-red-600">Delete</button>
                        </div>
                    </div>
                ))}
            </div>
            {replaceStatus && <p role="status" className="mt-3 text-sm text-slate-600">{replaceStatus}</p>}
        </TeacherLayout>
    );
}
