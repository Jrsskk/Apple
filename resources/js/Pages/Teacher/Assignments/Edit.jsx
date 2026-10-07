import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime, statusBadge } from '@/utils/teacher';

function GradeForm({ submission }) {
    const { data, setData, post, processing, errors } = useForm({
        score: submission.score ?? '',
        feedback: submission.feedback ?? '',
        status: submission.status?.value === 'returned' ? 'returned' : 'graded',
    });

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                post(`/teacher/submissions/${submission.id}/grade`);
            }}
            className="mt-3 p-3 bg-slate-50 rounded-lg space-y-2"
        >
            <div className="flex gap-2">
                <input
                    type="number"
                    min="0"
                    max={submission.assignment?.max_score}
                    value={data.score}
                    onChange={(e) => setData('score', e.target.value)}
                    className="rounded-lg border-slate-300 text-sm w-24"
                    placeholder="Score"
                    required
                />
                <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="rounded-lg border-slate-300 text-sm">
                    <option value="graded">Graded</option>
                    <option value="returned">Returned</option>
                </select>
            </div>
            <textarea value={data.feedback} onChange={(e) => setData('feedback', e.target.value)} rows={2} className="w-full rounded-lg border-slate-300 text-sm" placeholder="Feedback" />
            <button type="submit" disabled={processing} className="px-3 py-1 bg-emerald-600 text-white rounded text-xs">Save Grade</button>
        </form>
    );
}

export default function Edit({ assignment }) {
    const { data, setData, post, processing, errors } = useForm({
        title: assignment.title,
        description: assignment.description || '',
        instructions: assignment.instructions || '',
        starts_at: assignment.starts_at ? assignment.starts_at.slice(0, 16) : '',
        deadline: assignment.deadline ? assignment.deadline.slice(0, 16) : '',
        max_score: assignment.max_score,
        allow_resubmit: !!assignment.allow_resubmit,
        attachment: null,
        _method: 'PUT',
    });

    const [expandedSubmission, setExpandedSubmission] = useState(null);

    const submit = (e) => {
        e.preventDefault();
        post(`/teacher/assignments/${assignment.id}`, { forceFormData: true });
    };

    const publish = () => router.post(`/teacher/assignments/${assignment.id}/publish`);

    return (
        <TeacherLayout title="Edit Assignment">
            <Head title={`Edit: ${assignment.title}`} />

            <div className="grid lg:grid-cols-2 gap-6">
                <form onSubmit={submit} className="bg-white rounded-xl border p-6 space-y-4">
                    <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className="w-full rounded-lg border-slate-300" />
                    <textarea value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} rows={3} className="w-full rounded-lg border-slate-300" />
                    <label className="block text-sm font-medium">
                        Start date
                        <input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className="mt-1 w-full rounded-lg border-slate-300" />
                        {errors.starts_at && <span className="mt-1 block text-xs text-red-600">{errors.starts_at}</span>}
                    </label>
                    <label className="block text-sm font-medium">
                        Deadline
                        <input type="datetime-local" value={data.deadline} onChange={(e) => setData('deadline', e.target.value)} className="mt-1 w-full rounded-lg border-slate-300" />
                        {errors.deadline && <span className="mt-1 block text-xs text-red-600">{errors.deadline}</span>}
                    </label>
                    <label className="block text-sm font-medium">
                        Max score
                        <input type="number" min="1" value={data.max_score} onChange={(e) => setData('max_score', parseFloat(e.target.value))} className="mt-1 w-full rounded-lg border-slate-300" />
                        {errors.max_score && <span className="mt-1 block text-xs text-red-600">{errors.max_score}</span>}
                    </label>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.allow_resubmit} onChange={(e) => setData('allow_resubmit', e.target.checked)} /> Allow resubmit</label>
                    <input type="file" onChange={(e) => setData('attachment', e.target.files[0])} className="text-sm" />
                    {assignment.attachment_path && (
                        <p className="text-xs text-slate-500">
                            Current attachment: {assignment.attachment_file_name || assignment.attachment_path.split('/').pop()}
                            {assignment.attachment_file_size ? ` · ${(assignment.attachment_file_size / 1048576).toFixed(2)} MB` : ''}
                        </p>
                    )}
                    {data.attachment && <p className="text-xs text-slate-500">{data.attachment.name} · {(data.attachment.size / 1048576).toFixed(2)} MB</p>}
                    {errors.attachment && <p role="alert" className="text-sm text-red-600">{errors.attachment}</p>}
                    {assignment.attachment_path && (
                        <div className="flex gap-3 text-sm">
                            <a href={`/teacher/assignments/${assignment.id}/attachment`} className="text-indigo-600">Download attachment</a>
                            <button type="button" onClick={() => router.delete(`/teacher/assignments/${assignment.id}/attachment`)} className="text-red-600">Delete attachment</button>
                        </div>
                    )}
                    <div className="flex gap-2">
                        <button type="submit" disabled={processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">{processing ? 'Saving…' : 'Save'}</button>
                        {assignment.status !== 'published' && (
                            <button type="button" onClick={publish} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm">Publish</button>
                        )}
                    </div>
                </form>

                <div className="bg-white rounded-xl border p-6">
                    <h2 className="font-semibold mb-4">Submissions ({assignment.submissions?.length || 0})</h2>
                    <div className="space-y-3 max-h-[600px] overflow-y-auto">
                        {assignment.submissions?.map((s) => (
                            <div key={s.id} className="border rounded-lg p-3 text-sm">
                                <div className="flex justify-between items-start">
                                    <div>
                                        <p className="font-medium">{s.student?.first_name} {s.student?.last_name}</p>
                                        <p className="text-xs text-slate-500">{formatDateTime(s.submitted_at)}</p>
                                    </div>
                                    <span className={`px-2 py-0.5 rounded text-xs ${statusBadge(s.status?.value ?? s.status)}`}>
                                        {s.status?.value ?? s.status}
                                    </span>
                                </div>
                                {s.text_response && <p className="mt-2 text-slate-600 whitespace-pre-wrap">{s.text_response}</p>}
                                {s.file_path && (
                                    <a href={`/teacher/submissions/assignment/${s.id}/download`} className="text-emerald-600 text-xs mt-1 inline-block">
                                        Download {s.file_name || 'file'}{s.file_size ? ` · ${(s.file_size / 1048576).toFixed(2)} MB` : ''}
                                    </a>
                                )}
                                <button type="button" onClick={() => setExpandedSubmission(expandedSubmission === s.id ? null : s.id)} className="text-xs text-slate-500 mt-2 block">
                                    {expandedSubmission === s.id ? 'Hide grading' : 'Grade'}
                                </button>
                                {expandedSubmission === s.id && <GradeForm submission={s} />}
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </TeacherLayout>
    );
}
