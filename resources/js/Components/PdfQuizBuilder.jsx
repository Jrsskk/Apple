import { useState } from 'react';

export default function PdfQuizBuilder({ open, onClose, onGenerated }) {
    const [pdfFile, setPdfFile] = useState(null);
    const [settings, setSettings] = useState({
        question_count: 5,
        difficulty: 'medium',
        question_type: 'multiple_choice',
    });
    const [error, setError] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);

    if (!open) {
        return null;
    }

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!pdfFile) {
            setError('Please select a PDF file before generating questions.');
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const formData = new FormData();
        formData.append('pdf_file', pdfFile);
        formData.append('question_count', String(settings.question_count));
        formData.append('difficulty', settings.difficulty);
        formData.append('question_type', settings.question_type);

        setError('');
        setIsGenerating(true);

        try {
            const response = await fetch('/teacher/quizzes/generate-from-pdf', {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: formData,
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                throw new Error(payload.message || payload.errors?.pdf_file?.[0] || 'The PDF could not be processed.');
            }

            if (typeof onGenerated === 'function') {
                onGenerated(payload.questions || [], payload.message || 'Questions generated successfully.');
            }

            setPdfFile(null);
            onClose();
        } catch (requestError) {
            setError(requestError.message || 'Something went wrong while generating the quiz.');
        } finally {
            setIsGenerating(false);
        }
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" onClick={onClose}>
            <div className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl" onClick={(event) => event.stopPropagation()}>
                <div className="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium uppercase tracking-[0.2em] text-emerald-600">PDF-to-Quiz</p>
                        <h3 className="mt-1 text-2xl font-semibold text-slate-900">Build a quiz from a lesson PDF</h3>
                    </div>
                    <button type="button" onClick={onClose} className="text-sm text-slate-500 hover:text-slate-700">Close</button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div>
                        <label className="mb-2 block text-sm font-medium text-slate-700">Upload PDF</label>
                        <input
                            type="file"
                            accept="application/pdf"
                            onChange={(event) => setPdfFile(event.target.files?.[0] || null)}
                            className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white"
                        />
                        <p className="mt-2 text-xs text-slate-500">Only searchable PDF files with readable lesson text are supported.</p>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">Questions</label>
                            <input
                                type="number"
                                min="1"
                                max="12"
                                value={settings.question_count}
                                onChange={(event) => setSettings((prev) => ({ ...prev, question_count: Number(event.target.value) || 1 }))}
                                className="w-full rounded-lg border-slate-300"
                            />
                        </div>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">Difficulty</label>
                            <select
                                value={settings.difficulty}
                                onChange={(event) => setSettings((prev) => ({ ...prev, difficulty: event.target.value }))}
                                className="w-full rounded-lg border-slate-300"
                            >
                                <option value="easy">Easy</option>
                                <option value="medium">Medium</option>
                                <option value="hard">Hard</option>
                            </select>
                        </div>
                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">Type</label>
                            <select
                                value={settings.question_type}
                                onChange={(event) => setSettings((prev) => ({ ...prev, question_type: event.target.value }))}
                                className="w-full rounded-lg border-slate-300"
                            >
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True / False</option>
                                <option value="identification">Identification</option>
                            </select>
                        </div>
                    </div>

                    {error && (
                        <div className="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                            {error}
                        </div>
                    )}

                    <div className="flex items-center justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700">Cancel</button>
                        <button type="submit" disabled={isGenerating} className="rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-70">
                            {isGenerating ? 'Generating...' : 'Generate Questions'}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
