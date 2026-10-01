import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import TeacherLayout from '@/Layouts/TeacherLayout';
import QuestionBuilder from '@/Components/QuestionBuilder';
import PdfQuizBuilder from '@/Components/PdfQuizBuilder';

function serializeQuestions(questions) {
    return questions.map((q) => ({
        type: q.type,
        question_text: q.question_text,
        points: q.points,
        explanation: q.explanation || null,
        image_path: q.image_path || null,
        options: (q.options || []).map((o, i) => ({
            option_text: o.option_text,
            is_correct: !!o.is_correct,
            match_key: o.match_key || null,
            image_path: o.image_path || null,
            order: i,
        })),
    }));
}

function deserializeQuestions(questions) {
    return (questions || []).map((q) => ({
        type: q.type?.value ?? q.type,
        question_text: q.question_text,
        points: parseFloat(q.points),
        explanation: q.explanation || '',
        image_path: q.image_path,
        options: (q.options || []).map((o) => ({
            option_text: o.option_text,
            is_correct: !!o.is_correct,
            match_key: o.match_key,
            image_path: o.image_path,
        })),
    }));
}

export default function Edit({ quiz }) {
    const [previewOpen, setPreviewOpen] = useState(false);
    const [pdfBuilderOpen, setPdfBuilderOpen] = useState(false);
    const { props } = usePage();
    const pageErrors = props.errors || {};
    const { data, setData, put, processing, transform, errors: formErrors } = useForm({
        title: quiz.title,
        instructions: quiz.instructions || '',
        subject_id: quiz.subject_id,
        school_class_id: quiz.school_class_id,
        starts_at: quiz.starts_at ? quiz.starts_at.slice(0, 16) : '',
        deadline: quiz.deadline ? quiz.deadline.slice(0, 16) : '',
        duration_minutes: quiz.duration_minutes,
        max_attempts: quiz.max_attempts,
        passing_score: quiz.passing_score,
        randomize_questions: !!quiz.randomize_questions,
        randomize_choices: !!quiz.randomize_choices,
        show_results: !!quiz.show_results,
        allow_review: !!quiz.allow_review,
        status: quiz.status?.value ?? quiz.status,
        questions: deserializeQuestions(quiz.questions),
    });

    transform((formData) => ({
        ...formData,
        questions: serializeQuestions(formData.questions),
    }));

    const submit = (e) => {
        e.preventDefault();
        put(`/teacher/quizzes/${quiz.id}`);
    };

    const handlePdfGenerated = (questions) => {
        setData('questions', questions || []);
    };

    const publish = () => router.post(`/teacher/quizzes/${quiz.id}/publish`);
    const duplicate = () => router.post(`/teacher/quizzes/${quiz.id}/duplicate`);
    const remove = () => {
        if (window.confirm(`Archive "${data.title}"? This removes it from student access.`)) {
            router.delete(`/teacher/quizzes/${quiz.id}`);
        }
    };

    return (
        <TeacherLayout title="Edit Quiz">
            <Head title={`Edit: ${quiz.title}`} />

            <form onSubmit={submit} className="space-y-6">
                {(Object.keys(pageErrors).length > 0 || Object.keys(formErrors).length > 0) && (
                    <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                        <p className="font-semibold">Please fix the quiz before publishing or saving.</p>
                        <ul className="mt-2 list-disc pl-5 space-y-1">
                            {Object.values({ ...pageErrors, ...formErrors }).map((message, index) => (
                                <li key={index}>{Array.isArray(message) ? message[0] : message}</li>
                            ))}
                        </ul>
                    </div>
                )}
                <div className="bg-white rounded-xl border p-6 space-y-4">
                    <div className="grid lg:grid-cols-2 gap-4">
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)} className="rounded-lg border-slate-300" placeholder="Title" required />
                        <select value={data.status} onChange={(e) => setData('status', e.target.value)} className="rounded-lg border-slate-300">
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="archived">Archived</option>
                        </select>
                    </div>

                    <textarea value={data.instructions} onChange={(e) => setData('instructions', e.target.value)} rows={3} className="w-full rounded-lg border-slate-300" placeholder="Instructions" />

                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <input type="datetime-local" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className="rounded-lg border-slate-300" />
                        <input type="datetime-local" value={data.deadline} onChange={(e) => setData('deadline', e.target.value)} className="rounded-lg border-slate-300" />
                        <input type="number" value={data.duration_minutes} onChange={(e) => setData('duration_minutes', parseInt(e.target.value, 10))} className="rounded-lg border-slate-300" placeholder="Duration" />
                        <input type="number" value={data.max_attempts} onChange={(e) => setData('max_attempts', parseInt(e.target.value, 10))} className="rounded-lg border-slate-300" placeholder="Max attempts" />
                    </div>

                    <div className="flex flex-wrap gap-4 text-sm">
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.randomize_questions} onChange={(e) => setData('randomize_questions', e.target.checked)} /> Randomize questions</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.randomize_choices} onChange={(e) => setData('randomize_choices', e.target.checked)} /> Randomize choices</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.show_results} onChange={(e) => setData('show_results', e.target.checked)} /> Instant feedback</label>
                        <label className="flex items-center gap-2"><input type="checkbox" checked={data.allow_review} onChange={(e) => setData('allow_review', e.target.checked)} /> Allow review</label>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <button type="button" onClick={() => setPdfBuilderOpen(true)} className="px-4 py-2 border border-emerald-200 bg-emerald-50 text-emerald-700 rounded-lg text-sm">Build from PDF</button>
                        <button type="button" onClick={() => setPreviewOpen(true)} className="px-4 py-2 border border-slate-300 text-slate-700 rounded-lg text-sm">Preview</button>
                        {(data.status !== 'published') && (
                            <button type="button" onClick={publish} className="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Publish</button>
                        )}
                        <button type="button" onClick={duplicate} className="px-4 py-2 bg-slate-600 text-white rounded-lg text-sm">Duplicate</button>
                        <button type="button" onClick={remove} className="px-4 py-2 border border-red-200 text-red-600 rounded-lg text-sm">Delete</button>
                    </div>
                </div>

                <div className="bg-white rounded-xl border p-6">
                    <h2 className="font-semibold mb-4">Questions ({data.questions.length})</h2>
                    <QuestionBuilder
                        questions={data.questions}
                        onChange={(questions) => setData('questions', questions)}
                    />
                </div>

                <button type="submit" disabled={processing} className="px-6 py-2 bg-emerald-600 text-white rounded-lg">Save Quiz</button>
            </form>

            <PdfQuizBuilder open={pdfBuilderOpen} onClose={() => setPdfBuilderOpen(false)} onGenerated={handlePdfGenerated} />

            {previewOpen && (
                <div className="fixed inset-0 z-50 bg-slate-950/50 p-4 overflow-y-auto" onClick={() => setPreviewOpen(false)}>
                    <div className="max-w-3xl mx-auto mt-8 bg-white rounded-xl p-6 space-y-5" onClick={(e) => e.stopPropagation()}>
                        <div className="flex items-center justify-between">
                            <h2 className="text-xl font-semibold">{data.title || 'Quiz preview'}</h2>
                            <button type="button" onClick={() => setPreviewOpen(false)} className="text-sm text-slate-500">Close</button>
                        </div>
                        {data.questions.map((question, index) => (
                            <div key={index} className="border-b pb-4">
                                <div className="flex justify-between gap-3">
                                    <p className="font-medium">{index + 1}. {question.question_text || 'Untitled question'}</p>
                                    <span className="text-xs text-slate-500">{question.points} pts</span>
                                </div>
                                {question.image_path && <img src={question.image_path} alt="Question reference" className="max-h-48 mt-3 rounded-lg object-contain" />}
                                <div className="mt-3 grid gap-2">
                                    {question.options.map((option, optionIndex) => (
                                        <div key={optionIndex} className="rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                            {option.option_text || `Option ${optionIndex + 1}`}
                                            {option.image_path && <img src={option.image_path} alt="Answer option" className="max-h-20 mt-2 rounded object-contain" />}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </TeacherLayout>
    );
}
