import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import QuestionBuilder from '@/Components/QuestionBuilder';
import PdfQuizBuilder from '@/Components/PdfQuizBuilder';

export default function Create({ classes, subjects }) {
    const [previewOpen, setPreviewOpen] = useState(false);
    const [pdfBuilderOpen, setPdfBuilderOpen] = useState(false);
    const [generatedMessage, setGeneratedMessage] = useState('');

    const initialSubjectId = subjects[0]?.id || '';
    const subjectClasses = classes.filter((cls) => String(cls.subject_id) === String(initialSubjectId));
    const { data, setData, post, processing, errors } = useForm({
        title: '',
        instructions: '',
        subject_id: initialSubjectId,
        school_class_id: subjectClasses[0]?.id || '',
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

    const onSubjectChange = (subjectId) => {
        const firstClass = classes.find((cls) => String(cls.subject_id) === subjectId);
        setData((prev) => ({
            ...prev,
            subject_id: subjectId,
            school_class_id: firstClass?.id || '',
        }));
    };

    const handlePdfGenerated = (questions, message) => {
        setGeneratedMessage(message || 'Questions generated successfully.');
        setData('questions', questions || []);
    };

    const submit = (e) => {
        e.preventDefault();
        post('/teacher/quizzes');
    };

    return (
        <TeacherLayout title="Create Quiz">
            <Head title="Create Quiz" />

            <div className="space-y-6">
                {generatedMessage && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                        {generatedMessage}
                    </div>
                )}

                <form onSubmit={submit} className="bg-white rounded-xl border p-6 space-y-4 max-w-5xl">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 className="text-xl font-semibold text-slate-900">Quiz setup</h2>
                            <p className="text-sm text-slate-500">Create your quiz from scratch or build questions directly from a PDF lesson.</p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <button type="button" onClick={() => setPdfBuilderOpen(true)} className="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-700">Build from PDF</button>
                            <button type="button" onClick={() => setPreviewOpen(true)} className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">Quiz Preview</button>
                        </div>
                    </div>

                    <div>
                        <label className="block text-sm font-medium mb-1">Title</label>
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="w-full rounded-lg border-slate-300" required />
                        {errors.title && <p className="text-red-600 text-xs mt-1">{errors.title}</p>}
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label className="block text-sm font-medium mb-1">Subject</label>
                            <select value={data.subject_id} onChange={(e) => onSubjectChange(e.target.value)} className="w-full rounded-lg border-slate-300" required>
                                <option value="">Select subject</option>
                                {subjects.map((subject) => <option key={subject.id} value={subject.id}>{subject.name}</option>)}
                            </select>
                            {errors.subject_id && <p className="text-red-600 text-xs mt-1">{errors.subject_id}</p>}
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Class</label>
                            <select value={data.school_class_id} onChange={(e) => setData('school_class_id', e.target.value)} className="w-full rounded-lg border-slate-300" required>
                                {classes.filter((cls) => String(cls.subject_id) === String(data.subject_id)).map((c) => (
                                    <option key={c.id} value={c.id}>{c.name} - {c.section}</option>
                                ))}
                            </select>
                            {errors.school_class_id && <p className="text-red-600 text-xs mt-1">{errors.school_class_id}</p>}
                        </div>
                    </div>

                    <textarea value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} rows={3} className="w-full rounded-lg border-slate-300" placeholder="Instructions" />

                    <div className="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Starts At</label>
                            <input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className="w-full rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Deadline</label>
                            <input type="datetime-local" value={data.deadline} onChange={(e) => setData('deadline', e.target.value)} className="w-full rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Duration (minutes)</label>
                            <input type="number" min="1" value={data.duration_minutes} onChange={(e) => setData('duration_minutes', parseInt(e.target.value, 10))} className="w-full rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Max Attempts</label>
                            <input type="number" min="1" max="10" value={data.max_attempts} onChange={(e) => setData('max_attempts', parseInt(e.target.value, 10))} className="w-full rounded-lg border-slate-300" />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Passing Score (%)</label>
                            <input type="number" min="0" max="100" value={data.passing_score} onChange={(e) => setData('passing_score', parseFloat(e.target.value))} className="w-full rounded-lg border-slate-300" />
                        </div>
                    </div>

                    <div className="flex flex-wrap gap-4 text-sm">
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.randomize_questions} onChange={(e) => setData('randomize_questions', e.target.checked)} /> Randomize questions</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.randomize_choices} onChange={(e) => setData('randomize_choices', e.target.checked)} /> Randomize choices</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.show_results} onChange={(e) => setData('show_results', e.target.checked)} /> Instant feedback</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.allow_review} onChange={(e) => setData('allow_review', e.target.checked)} /> Allow review</label>
                    </div>

                    <div className="bg-white rounded-xl border p-6">
                        <h2 className="font-semibold mb-4">Questions ({data.questions.length})</h2>
                        <QuestionBuilder
                            questions={data.questions}
                            onChange={(questions) => setData('questions', questions)}
                        />
                    </div>

                    <button type="submit" disabled={processing || !data.school_class_id} className="px-6 py-2 bg-emerald-600 text-white rounded-lg disabled:opacity-60">Create Quiz</button>
                </form>
            </div>

            <PdfQuizBuilder open={pdfBuilderOpen} onClose={() => setPdfBuilderOpen(false)} onGenerated={handlePdfGenerated} />

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
