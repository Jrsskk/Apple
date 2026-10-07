import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import QuestionBuilder from '@/Components/QuestionBuilder';

export default function Create({ classes, subjects }) {
    const [previewOpen, setPreviewOpen] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        title: '',
        instructions: '',
        subject_id: '',
        school_class_id: '',
        starts_at: '',
        deadline: '',
        duration_minutes: 30,
        max_attempts: 1,
        passing_score: 60,
        randomize_questions: false,
        randomize_choices: false,
        show_results: true,
        allow_review: true,
        status: 'draft',
        questions: [],
    });
    const subjectClasses = classes.filter((cls) => String(cls.subject_id) === String(data.subject_id));

    const onSubjectChange = (subjectId) => {
        setData((prev) => ({
            ...prev,
            subject_id: subjectId,
            school_class_id: '',
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        post('/teacher/quizzes');
    };

    return (
        <TeacherLayout title="Create Quiz">
            <Head title="Create Quiz" />

            <div className="mx-auto max-w-4xl space-y-8 px-4 py-8 sm:px-6 lg:px-8">
                <div className="md:flex md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">Create New Quiz</h1>
                        <p className="mt-1 text-sm text-slate-500">Set up instructions, timing, rules, and add or generate questions.</p>
                    </div>
                    <a
                        href="/teacher/quizzes"
                        className="mt-4 inline-flex items-center rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-50 md:mt-0"
                    >
                        Back to Quizzes
                    </a>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <section className="space-y-6 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                        <div className="border-b border-slate-100 pb-4">
                            <h2 className="text-lg font-bold text-slate-900">1. Basic Information</h2>
                            <p className="text-xs text-slate-500">Title, target class, and instructions for students.</p>
                        </div>
                        <div className="space-y-4">
                            <div>
                                <label htmlFor="quiz-title" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Quiz Title <span className="text-rose-500">*</span></label>
                                <input id="quiz-title" value={data.title} onChange={(e) => setData('title', e.target.value)} placeholder="e.g. Midterm Examination - Biology 101" className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500" required />
                                {errors.title && <p role="alert" className="mt-1 text-xs text-rose-600">{errors.title}</p>}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="quiz-subject" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Subject <span className="text-rose-500">*</span></label>
                                    <select id="quiz-subject" value={data.subject_id} onChange={(e) => onSubjectChange(e.target.value)} className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required>
                                        <option value="">Select subject</option>
                                        {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                                    </select>
                                    {errors.subject_id && <p role="alert" className="mt-1 text-xs text-rose-600">{errors.subject_id}</p>}
                                </div>
                                <div>
                                    <label htmlFor="quiz-class" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Class Assignment <span className="text-rose-500">*</span></label>
                                    <select id="quiz-class" value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)} className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm text-slate-900 shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required disabled={subjectClasses.length === 0}>
                                        <option value="">Select class</option>
                                        {subjectClasses.map((schoolClass) => <option key={schoolClass.id} value={schoolClass.id}>{schoolClass.name} - {schoolClass.section}</option>)}
                                    </select>
                                    {errors.school_class_id && <p role="alert" className="mt-1 text-xs text-rose-600">{errors.school_class_id}</p>}
                                </div>
                            </div>
                            <div>
                                <label htmlFor="quiz-instructions" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Instructions</label>
                                <textarea id="quiz-instructions" value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} rows={3} className="w-full rounded-xl border-slate-200 p-3.5 text-sm text-slate-900 shadow-sm placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500" placeholder="Add special instructions, allowed materials, or notes for students..." />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-6 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                        <div className="border-b border-slate-100 pb-4">
                            <h2 className="text-lg font-bold text-slate-900">2. Timing &amp; Rules</h2>
                            <p className="text-xs text-slate-500">Define duration, schedule, passing threshold, and retake attempts.</p>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2">
                            <div>
                                <label htmlFor="quiz-duration" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Duration (Minutes) <span className="text-rose-500">*</span></label>
                                <input id="quiz-duration" type="number" min="1" max="480" value={data.duration_minutes} onChange={(e) => setData('duration_minutes', parseInt(e.target.value, 10))} className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required />
                            </div>
                            <div>
                                <label htmlFor="quiz-attempts" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Max Attempts Allowed <span className="text-rose-500">*</span></label>
                                <input id="quiz-attempts" type="number" min="1" max="10" value={data.max_attempts} onChange={(e) => setData('max_attempts', parseInt(e.target.value, 10))} className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required />
                            </div>
                            <div>
                                <label htmlFor="quiz-starts" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Starts At</label>
                                <input id="quiz-starts" type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                            </div>
                            <div>
                                <label htmlFor="quiz-deadline" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Deadline</label>
                                <input id="quiz-deadline" type="datetime-local" value={data.deadline} onChange={(e) => setData('deadline', e.target.value)} className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" />
                            </div>
                            <div>
                                <label htmlFor="quiz-passing-score" className="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-700">Passing Score Threshold (%) <span className="text-rose-500">*</span></label>
                                <input id="quiz-passing-score" type="number" min="0" max="100" value={data.passing_score} onChange={(e) => setData('passing_score', parseFloat(e.target.value))} className="w-full rounded-xl border-slate-200 px-3.5 py-2.5 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500" required />
                            </div>
                        </div>
                    </section>

                    <section className="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                        <div className="border-b border-slate-100 pb-3">
                            <h2 className="text-lg font-bold text-slate-900">3. Security &amp; Anti-Cheat Settings</h2>
                        </div>
                        <div className="grid gap-4 pt-2 sm:grid-cols-2">
                            {[
                                ['randomize_questions', 'Randomize Question Order', 'Shuffles question sequence for each attempt.'],
                                ['randomize_choices', 'Randomize Multiple Choice Options', 'Shuffles answer options for each attempt.'],
                                ['show_results', 'Show Results Immediately', 'Students see their results after submitting.'],
                                ['allow_review', 'Allow Quiz Review', 'Students can review their answers after submission.'],
                            ].map(([key, label, description]) => (
                                <label key={key} className="flex cursor-pointer items-start rounded-xl border border-slate-200 p-4 transition-all hover:border-emerald-200 hover:bg-emerald-50/30">
                                    <input type="checkbox" checked={data[key]} onChange={(e) => setData(key, e.target.checked)} className="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500" />
                                    <span className="ml-3 text-sm">
                                        <span className="font-semibold text-slate-900">{label}</span>
                                        <span className="mt-0.5 block text-xs text-slate-500">{description}</span>
                                    </span>
                                </label>
                            ))}
                        </div>
                    </section>

                    <section className="space-y-4 rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm sm:p-8">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <h2 className="text-lg font-bold text-slate-900">4. Questions ({data.questions.length})</h2>
                                <p className="text-xs text-slate-500">Add questions manually.</p>
                            </div>
                            <button type="button" onClick={() => setPreviewOpen(true)} className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Quiz Preview
                            </button>
                        </div>
                        <QuestionBuilder
                            questions={data.questions}
                            onChange={(questions) => setData('questions', questions)}
                        />
                    </section>

                    <div className="flex justify-end">
                        <button type="submit" disabled={processing || !data.school_class_id} className="rounded-xl bg-emerald-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-60">
                            {processing ? 'Creating Quiz…' : 'Create Quiz'}
                        </button>
                    </div>
                </form>
            </div>

            {previewOpen && (
                <div className="fixed inset-0 z-40 bg-slate-950/50 p-4 overflow-y-auto" onClick={() => setPreviewOpen(false)}>
                    <div className="max-w-3xl mx-auto mt-8 bg-white rounded-xl p-6 space-y-5" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between">
                            <h2 className="text-xl font-semibold">{data.title || 'Quiz preview'}</h2>
                            <button type="button" onClick={() => setPreviewOpen(false)} className="text-sm text-slate-500">Close</button>
                        </div>
                        {data.questions.length === 0 ? (
                            <p className="text-sm text-slate-500">No questions yet. Add questions manually or generate from a PDF.</p>
                        ) : (
                            data.questions.map((question, index) => (
                                <div key={index} className="border-b pb-4">
                                    <div className="flex justify-between gap-3">
                                        <p className="font-medium">{index + 1}. {question.question_text || 'Untitled question'}</p>
                                        <span className="text-xs text-slate-500">{question.points} pts</span>
                                    </div>
                                    <div className="mt-3 grid gap-2">
                                        {question.options?.map((option, optionIndex) => (
                                            <div key={optionIndex} className="rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                                {option.option_text || `Option ${optionIndex + 1}`}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </TeacherLayout>
    );
}
