import { Head, useForm } from '@inertiajs/react';
import TeacherLayout from '@/Layouts/TeacherLayout';

export default function Create({ classes, subjects }) {
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        description: '',
        instructions: '',
        subject_id: '',
        school_class_id: '',
        starts_at: '',
        deadline: '',
        max_score: 100,
        allow_resubmit: false,
        attachment: null,
    });
    const subjectClasses = classes.filter((cls) => String(cls.subject_id) === String(data.subject_id));

    const onSubjectChange = (subjectId) => {
        setData((prev) => ({ ...prev, subject_id: subjectId, school_class_id: '' }));
    };

    const submit = (e) => {
        e.preventDefault();
        post('/teacher/assignments', { forceFormData: true });
    };

    return (
        <TeacherLayout title="Create Assignment">
            <Head title="Create Assignment" />

            <form onSubmit={submit} className="bg-white rounded-xl border p-6 space-y-4 max-w-3xl">
                <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="w-full rounded-lg border-slate-300" placeholder="Title" required />

                <label className="block text-sm font-medium">
                    Subject
                    <select value={data.subject_id} onChange={(e) => onSubjectChange(e.target.value)} className="mt-1 w-full rounded-lg border-slate-300" required>
                        <option value="">Select subject</option>
                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                    </select>
                    {errors.subject_id && <span className="mt-1 block text-xs text-red-600">{errors.subject_id}</span>}
                </label>

                <label className="block text-sm font-medium">
                    Class
                    <select value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)} className="mt-1 w-full rounded-lg border-slate-300" required disabled={subjectClasses.length === 0}>
                        <option value="">Select class</option>
                        {classes.filter((cls) => String(cls.subject_id) === String(data.subject_id)).map((c) => <option key={c.id} value={c.id}>{c.name} - {c.section}</option>)}
                    </select>
                    {errors.school_class_id && <span className="mt-1 block text-xs text-red-600">{errors.school_class_id}</span>}
                </label>

                <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={2} className="w-full rounded-lg border-slate-300" placeholder="Description" />
                <textarea value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} rows={3} className="w-full rounded-lg border-slate-300" placeholder="Instructions" />

                <div className="grid sm:grid-cols-2 gap-4">
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
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input type="checkbox" checked={data.allow_resubmit} onChange={(e) => setData('allow_resubmit', e.target.checked)} /> Allow resubmit
                </label>

                <input type="file" onChange={(e) => setData('attachment', e.target.files[0])} className="text-sm" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png" />
                {data.attachment && <p className="text-xs text-slate-500">{data.attachment.name} · {(data.attachment.size / 1048576).toFixed(2)} MB</p>}

                {Object.entries(errors).filter(([key]) => !['subject_id', 'school_class_id'].includes(key)).map(([key, error]) => <p key={key} className="text-sm text-red-600">{error}</p>)}

                <button type="submit" disabled={processing || !data.school_class_id} className="px-6 py-2 bg-emerald-600 text-white rounded-lg disabled:opacity-60">{processing ? 'Uploading…' : 'Create Assignment'}</button>
            </form>
        </TeacherLayout>
    );
}
