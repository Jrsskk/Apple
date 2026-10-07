import { Head, router, useForm } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';

const fileTypeStyles = {
    pdf: 'bg-rose-50 text-rose-700 ring-rose-600/20',
    doc: 'bg-blue-50 text-blue-700 ring-blue-700/10',
    docx: 'bg-blue-50 text-blue-700 ring-blue-700/10',
    xls: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    xlsx: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    csv: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    ppt: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    pptx: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    png: 'bg-purple-50 text-purple-700 ring-purple-700/10',
    jpg: 'bg-purple-50 text-purple-700 ring-purple-700/10',
    jpeg: 'bg-purple-50 text-purple-700 ring-purple-700/10',
    svg: 'bg-purple-50 text-purple-700 ring-purple-700/10',
    webp: 'bg-purple-50 text-purple-700 ring-purple-700/10',
};

function formatFileSize(bytes) {
    if (!bytes) return '—';
    if (bytes >= 1073741824) return `${(bytes / 1073741824).toFixed(2)} GB`;
    if (bytes >= 1048576) return `${(bytes / 1048576).toFixed(1)} MB`;
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${bytes} B`;
}

export default function Index({ materials, classes, subjects, filters }) {
    const [showForm, setShowForm] = useState(false);
    const [editingMaterialId, setEditingMaterialId] = useState(null);
    const [uploadStatus, setUploadStatus] = useState('');
    const [replaceStatus, setReplaceStatus] = useState('');
    const { data, setData, post, processing, reset, errors } = useForm({
        title: '',
        description: '',
        school_class_id: '',
        subject_id: '',
        file: null,
    });
    const {
        data: editData,
        setData: setEditData,
        post: saveMaterial,
        processing: editProcessing,
        errors: editErrors,
        clearErrors: clearEditErrors,
    } = useForm({
        title: '',
        description: '',
        school_class_id: '',
        subject_id: '',
        file: null,
    });

    const subjectClasses = classes.filter((schoolClass) => String(schoolClass.subject_id) === String(data.subject_id));

    const onClassChange = (classId) => {
        const cls = classes.find((c) => c.id === parseInt(classId, 10));
        setData((prev) => ({
            ...prev,
            school_class_id: classId,
            subject_id: String(cls?.subject_id || prev.subject_id),
        }));
    };

    const onSubjectChange = (subjectId) => {
        setData((prev) => ({
            ...prev,
            subject_id: subjectId,
            school_class_id: '',
        }));
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

    const editMaterial = (material) => {
        clearEditErrors();
        setReplaceStatus('');
        setEditData({
            title: material.title,
            description: material.description || '',
            school_class_id: material.school_class_id,
            subject_id: material.subject_id,
            file: null,
        });
        setEditingMaterialId(material.id);
    };

    const submitEdit = (e) => {
        e.preventDefault();
        saveMaterial(`/teacher/materials/${editingMaterialId}/replace`, {
            forceFormData: true,
            onStart: () => setReplaceStatus('Saving material…'),
            onSuccess: () => {
                setEditingMaterialId(null);
                setReplaceStatus('Material updated.');
            },
            onError: () => setReplaceStatus('Update failed. Check the material details and try again.'),
        });
    };

    return (
        <TeacherLayout title="Learning Materials">
            <Head title="Materials" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="mb-8 md:flex md:items-center md:justify-between">
                    <div className="min-w-0 flex-1">
                        <h2 className="text-2xl font-bold leading-7 text-slate-900 sm:text-3xl sm:tracking-tight">
                            Learning Materials
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            Manage and share course resources, documents, and files with your classes.
                        </p>
                    </div>
                    <div className="mt-4 md:ml-4 md:mt-0">
                        <button
                            type="button"
                            onClick={() => setShowForm(!showForm)}
                            className="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-all duration-150 hover:bg-emerald-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-600"
                        >
                            {showForm ? 'Cancel' : 'Upload Material'}
                        </button>
                    </div>
                </div>

                {showForm && (
                    <form onSubmit={submit} className="mb-6 space-y-4 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="Title" className="w-full rounded-lg border-slate-300" required />
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} placeholder="Description" className="w-full rounded-lg border-slate-300" rows={2} />
                        <label className="block text-sm font-medium text-slate-700">
                            Subject
                            <select
                                value={data.subject_id}
                                onChange={(e) => onSubjectChange(e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300"
                                required
                            >
                                <option value="">Select a subject</option>
                                {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                            </select>
                        </label>
                        <label className="block text-sm font-medium text-slate-700">
                            Class
                            <select
                                value={data.school_class_id}
                                onChange={(e) => onClassChange(e.target.value)}
                                className="mt-1 w-full rounded-lg border-slate-300"
                                required
                                disabled={subjectClasses.length === 0}
                            >
                                <option value="">Select a class</option>
                                {subjectClasses.map((schoolClass) => (
                                    <option key={schoolClass.id} value={schoolClass.id}>
                                        {schoolClass.name} - {schoolClass.section}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <input type="file" accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.ppt,.pptx,.pps,.ppsx,.xls,.xlsx,.csv,.mp4,.mov,.avi,.webm,.jpg,.jpeg,.png,.gif,.webp" onChange={(e) => setData('file', e.target.files[0])} required className="text-sm" />
                        {data.file && <p className="text-xs text-slate-500">{data.file.name} · {(data.file.size / 1048576).toFixed(2)} MB</p>}
                        {errors.file && <p role="alert" className="text-sm text-red-600">{errors.file}</p>}
                        {uploadStatus && <p role="status" className="text-sm text-slate-600">{uploadStatus}</p>}
                        <button type="submit" disabled={processing} className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-50">{processing ? 'Uploading…' : 'Upload'}</button>
                    </form>
                )}

                {uploadStatus === 'Upload complete.' && (
                    <div role="status" className="mb-6 rounded-xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm font-medium text-emerald-800 shadow-sm">
                        {uploadStatus}
                    </div>
                )}
                {uploadStatus.startsWith('Upload failed') && (
                    <div role="alert" className="mb-6 rounded-xl border border-rose-200/80 bg-rose-50 p-4 text-sm font-medium text-rose-800 shadow-sm">
                        {uploadStatus}
                    </div>
                )}

                {replaceStatus && (
                    <p role="status" className="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        {replaceStatus}
                    </p>
                )}

                <label className="mb-4 block max-w-sm text-sm font-medium text-slate-700">
                    Subject
                    <select
                        value={filters?.subject_id ? String(filters.subject_id) : ''}
                        onChange={(event) => router.get('/teacher/materials', { subject_id: event.target.value }, { preserveState: true, preserveScroll: true, replace: true })}
                        className="mt-1 w-full rounded-lg border-slate-300"
                    >
                        <option value="">All subjects</option>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                    </select>
                </label>

                <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left">
                            <thead>
                                <tr className="border-b border-slate-200 bg-slate-50/80 text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <th scope="col" className="px-6 py-4">Subject → Class → Material</th>
                                    <th scope="col" className="px-6 py-4">Type</th>
                                    <th scope="col" className="px-6 py-4">Size</th>
                                    <th scope="col" className="px-6 py-4 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white text-sm">
                                {materials.data.length === 0 ? (
                                    <tr><td colSpan="4" className="px-6 py-5 text-center text-slate-500">No learning materials match this subject.</td></tr>
                                ) : materials.data.map((m) => {
                                    const extension = m.original_file_name?.split('.').pop()?.toLowerCase() || 'file';
                                    const className = m.school_class?.display_name
                                        || [m.school_class?.name, m.school_class?.section].filter(Boolean).join(' - ')
                                        || 'Unassigned';

                                    return (
                                        <Fragment key={m.id}>
                                        <tr className="transition-colors duration-150 hover:bg-slate-50/60">
                                            <td className="whitespace-nowrap px-6 py-4 font-semibold text-slate-900">
                                                <span className="text-slate-700">{m.school_class?.subject?.name || m.subject?.name || '—'} <span className="text-slate-400">→</span> {className} <span className="text-slate-400">→</span></span>
                                                <span className="block max-w-xs truncate sm:max-w-md" title={m.title}>{m.title}</span>
                                                {m.original_file_name && <span className="mt-1 block max-w-xs truncate text-xs font-normal text-slate-500 sm:max-w-md">{m.original_file_name}</span>}
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4">
                                                <span className={`inline-flex rounded-md px-2.5 py-1 text-xs font-medium uppercase ring-1 ring-inset ${fileTypeStyles[extension] || 'bg-slate-50 text-slate-700 ring-slate-600/10'}`}>
                                                    {extension}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-6 py-4 font-mono text-xs text-slate-500">{formatFileSize(m.file_size)}</td>
                                            <td className="whitespace-nowrap px-6 py-4 text-right text-xs">
                                                <div className="flex items-center justify-end gap-1">
                                                    <a href={`/teacher/materials/${m.id}/download?download=0`} target="_blank" rel="noreferrer" className="rounded-lg p-2 text-slate-400 transition-colors duration-150 hover:bg-indigo-50 hover:text-indigo-600" title="View" aria-label={`View ${m.title}`}>View</a>
                                                    <a href={`/teacher/materials/${m.id}/download`} className="rounded-lg p-2 text-slate-400 transition-colors duration-150 hover:bg-indigo-50 hover:text-indigo-600" title="Download" aria-label={`Download ${m.title}`}>Download</a>
                                                    <button
                                                        type="button"
                                                        onClick={() => editMaterial(m)}
                                                        className="rounded-lg p-2 text-slate-400 transition-colors duration-150 hover:bg-amber-50 hover:text-amber-600"
                                                        aria-label={`Edit ${m.title}`}
                                                    >
                                                        Edit
                                                    </button>
                                                    <button type="button" onClick={() => destroy(m.id)} className="rounded-lg p-2 text-slate-400 transition-colors duration-150 hover:bg-rose-50 hover:text-rose-600" title="Delete material" aria-label={`Delete ${m.title}`}>Delete</button>
                                                </div>
                                            </td>
                                        </tr>
                                        {editingMaterialId === m.id && (
                                            <tr>
                                                <td colSpan="4" className="bg-slate-50 px-6 py-4">
                                                    <form onSubmit={submitEdit} className="grid gap-3 sm:grid-cols-2">
                                                        <input
                                                            value={editData.title}
                                                            onChange={(e) => setEditData('title', e.target.value)}
                                                            placeholder="Title"
                                                            aria-label="Material title"
                                                            required
                                                            className="rounded-lg border-slate-300"
                                                        />
                                                        <input
                                                            value={editData.description}
                                                            onChange={(e) => setEditData('description', e.target.value)}
                                                            placeholder="Description"
                                                            aria-label="Material description"
                                                            className="rounded-lg border-slate-300"
                                                        />
                                                        <label className="text-sm text-slate-600 sm:col-span-2">
                                                            Replace file (optional)
                                                            <input
                                                                type="file"
                                                                accept=".pdf,.doc,.docx,.odt,.rtf,.txt,.ppt,.pptx,.pps,.ppsx,.xls,.xlsx,.csv,.mp4,.mov,.avi,.webm,.jpg,.jpeg,.png,.gif,.webp"
                                                                onChange={(e) => setEditData('file', e.target.files[0] || null)}
                                                                className="mt-1 block text-sm"
                                                            />
                                                        </label>
                                                        {Object.entries(editErrors).map(([field, error]) => (
                                                            <p key={field} role="alert" className="text-sm text-red-600 sm:col-span-2">{error}</p>
                                                        ))}
                                                        <div className="flex gap-2 sm:col-span-2">
                                                            <button type="submit" disabled={editProcessing} className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                                                                {editProcessing ? 'Saving…' : 'Save'}
                                                            </button>
                                                            <button type="button" onClick={() => setEditingMaterialId(null)} className="rounded-lg border px-4 py-2 text-sm text-slate-600">
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </form>
                                                </td>
                                            </tr>
                                        )}
                                        </Fragment>
                                    );
                                })}
                                {materials.data.length === 0 && (
                                    <tr>
                                        <td colSpan="6" className="px-6 py-16 text-center">
                                            <div className="mx-auto flex max-w-sm flex-col items-center">
                                                <div className="mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400" aria-hidden="true">+</div>
                                                <h3 className="text-sm font-semibold text-slate-900">No materials uploaded</h3>
                                                <p className="mt-1 text-xs text-slate-500">Get started by uploading your first learning resource for your students.</p>
                                                <button type="button" onClick={() => setShowForm(true)} className="mt-5 text-xs font-semibold text-emerald-600 hover:text-emerald-500">
                                                    Upload First Material
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </TeacherLayout>
    );
}
