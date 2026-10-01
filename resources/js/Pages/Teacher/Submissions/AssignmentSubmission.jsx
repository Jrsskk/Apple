import { Head, Link, useForm } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import { formatDateTime } from '@/utils/teacher';

export default function AssignmentSubmission({ submission }) {
    const { data, setData, post, processing } = useForm({
        score: submission.score ?? '',
        feedback: submission.feedback ?? '',
        status: 'graded',
    });

    const submit = (e) => {
        e.preventDefault();
        post(`/teacher/submissions/assignment/${submission.id}/grade`);
    };

    return (
        <TeacherLayout title="Review Submission">
            <Head title="Assignment Submission" />

            <Link href="/teacher/submissions" className="text-sm text-emerald-600 mb-4 inline-block">← Back</Link>

            <div className="grid lg:grid-cols-2 gap-6">
                <div className="bg-white rounded-xl border p-6">
                    <h2 className="font-semibold">{submission.assignment?.title}</h2>
                    <p className="text-sm text-slate-500 mt-1">
                        {submission.student?.first_name} {submission.student?.last_name} · {formatDateTime(submission.submitted_at)}
                    </p>
                    {submission.text_response && (
                        <div className="mt-4 p-4 bg-slate-50 rounded-lg">
                            <p className="text-sm whitespace-pre-wrap">{submission.text_response}</p>
                        </div>
                    )}
                    {submission.file_path && (
                        <a href={`/teacher/submissions/assignment/${submission.id}/download`} className="inline-block mt-3 text-emerald-600 text-sm">
                            Download {submission.file_name || 'submitted file'}
                            {submission.file_size ? ` · ${(submission.file_size / 1048576).toFixed(2)} MB` : ''}
                        </a>
                    )}
                </div>

                <form onSubmit={submit} className="bg-white rounded-xl border p-6 space-y-4">
                    <h3 className="font-semibold">Grade & Feedback</h3>
                    <input type="number" min="0" max={submission.assignment?.max_score} value={data.score} onChange={(e) => setData('score', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                    <textarea value={data.feedback} onChange={(e) => setData('feedback', e.target.value)} rows={4} className="w-full rounded-lg border-slate-300" placeholder="Feedback for student" />
                    <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="w-full rounded-lg border-slate-300">
                        <option value="graded">Graded</option>
                        <option value="returned">Returned for revision</option>
                    </select>
                    <button type="submit" disabled={processing} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Submit Grade</button>
                </form>
            </div>
        </TeacherLayout>
    );
}
