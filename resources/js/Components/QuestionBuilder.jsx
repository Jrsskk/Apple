import { QUESTION_TYPES, emptyQuestion } from '@/utils/teacher';

function OptionEditor({ question, qIndex, onChange }) {
    const { type, options } = question;

    const updateOption = (oIndex, field, value) => {
        const newOptions = options.map((opt, i) => {
            if (i !== oIndex) {
                if (field === 'is_correct' && value && ['multiple_choice', 'true_false', 'image_based'].includes(type)) {
                    return { ...opt, is_correct: false };
                }
                return opt;
            }
            return { ...opt, [field]: value };
        });
        onChange(qIndex, { ...question, options: newOptions });
    };

    const addOption = () => {
        onChange(qIndex, {
            ...question,
            options: [...options, { option_text: '', is_correct: false, match_key: null }],
        });
    };

    const removeOption = (oIndex) => {
        onChange(qIndex, {
            ...question,
            options: options.filter((_, i) => i !== oIndex),
        });
    };

    const addMatchingPair = () => {
        const pairNum = Math.floor(options.length / 2) + 1;
        const key = `pair_${pairNum}`;
        onChange(qIndex, {
            ...question,
            options: [
                ...options,
                { option_text: '', is_correct: false, match_key: key },
                { option_text: '', is_correct: true, match_key: key },
            ],
        });
    };

    if (type === 'matching') {
        const pairs = [];
        for (let i = 0; i < options.length; i += 2) {
            pairs.push([options[i], options[i + 1]]);
        }
        return (
            <div className="space-y-3 mt-3">
                {pairs.map(([left, right], pairIndex) => (
                    <div key={pairIndex} className="grid sm:grid-cols-2 gap-2">
                        <input
                            type="text"
                            placeholder="Left item"
                            value={left?.option_text || ''}
                            onChange={(e) => updateOption(pairIndex * 2, 'option_text', e.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        />
                        <input
                            type="text"
                            placeholder="Right item (match)"
                            value={right?.option_text || ''}
                            onChange={(e) => updateOption(pairIndex * 2 + 1, 'option_text', e.target.value)}
                            className="rounded-lg border-slate-300 text-sm"
                        />
                    </div>
                ))}
                <button type="button" onClick={addMatchingPair} className="text-sm text-emerald-600 hover:underline">
                    + Add matching pair
                </button>
            </div>
        );
    }

    if (type === 'sequencing' || type === 'drag_drop') {
        return (
            <div className="space-y-2 mt-3">
                <p className="text-xs text-slate-500">Enter items in the correct order (top to bottom).</p>
                {options.map((opt, oIndex) => (
                    <div key={oIndex} className="flex gap-2 items-center">
                        <span className="text-xs text-slate-400 w-6">{oIndex + 1}.</span>
                        <input
                            type="text"
                            value={opt.option_text || ''}
                            onChange={(e) => updateOption(oIndex, 'option_text', e.target.value)}
                            className="flex-1 rounded-lg border-slate-300 text-sm"
                        />
                        {options.length > 2 && (
                            <button type="button" onClick={() => removeOption(oIndex)} className="text-red-500 text-xs">
                                Remove
                            </button>
                        )}
                    </div>
                ))}
                <button type="button" onClick={addOption} className="text-sm text-emerald-600 hover:underline">
                    + Add item
                </button>
            </div>
        );
    }

    if (type === 'identification') {
        return (
            <div className="mt-3">
                <label className="text-xs text-slate-500">Correct answer(s) — separate alternatives with commas</label>
                <input
                    type="text"
                    value={options[0]?.option_text || ''}
                    onChange={(e) => updateOption(0, 'option_text', e.target.value)}
                    className="w-full mt-1 rounded-lg border-slate-300 text-sm"
                    placeholder="e.g. mitochondria, Mitochondria"
                />
            </div>
        );
    }

    return (
        <div className="space-y-2 mt-3">
            {options.map((opt, oIndex) => (
                <div key={oIndex} className="flex gap-2 items-center">
                    <input
                        type={['multiple_choice', 'multiple_selection', 'image_based'].includes(type) ? 'radio' : 'checkbox'}
                        name={`correct-${qIndex}`}
                        checked={!!opt.is_correct}
                        onChange={() => updateOption(oIndex, 'is_correct', true)}
                        className="text-emerald-600"
                    />
                    <input
                        type="text"
                        value={opt.option_text || ''}
                        onChange={(e) => updateOption(oIndex, 'option_text', e.target.value)}
                        className="flex-1 rounded-lg border-slate-300 text-sm"
                        placeholder={`Option ${oIndex + 1}`}
                    />
                    {type === 'image_based' && (
                        <input
                            type="url"
                            value={opt.image_path || ''}
                            onChange={(e) => updateOption(oIndex, 'image_path', e.target.value)}
                            className="flex-1 rounded-lg border-slate-300 text-sm"
                            placeholder="Option image URL"
                        />
                    )}
                    {options.length > 2 && type !== 'true_false' && (
                        <button type="button" onClick={() => removeOption(oIndex)} className="text-red-500 text-xs">
                            Remove
                        </button>
                    )}
                </div>
            ))}
            {!['true_false', 'identification'].includes(type) && (
                <button type="button" onClick={addOption} className="text-sm text-emerald-600 hover:underline">
                    + Add option
                </button>
            )}
        </div>
    );
}

export default function QuestionBuilder({ questions, onChange }) {
    const addQuestion = (type = 'multiple_choice') => {
        onChange([...questions, emptyQuestion(type)]);
    };

    const updateQuestion = (index, updated) => {
        onChange(questions.map((q, i) => (i === index ? updated : q)));
    };

    const changeType = (index, type) => {
        updateQuestion(index, emptyQuestion(type));
    };

    const removeQuestion = (index) => {
        onChange(questions.filter((_, i) => i !== index));
    };

    const moveQuestion = (index, direction) => {
        const newQuestions = [...questions];
        const target = index + direction;
        if (target < 0 || target >= questions.length) return;
        [newQuestions[index], newQuestions[target]] = [newQuestions[target], newQuestions[index]];
        onChange(newQuestions);
    };

    return (
        <div className="space-y-4">
            <div className="flex flex-wrap gap-2">
                {QUESTION_TYPES.map((qt) => (
                    <button
                        key={qt.value}
                        type="button"
                        onClick={() => addQuestion(qt.value)}
                        className="px-3 py-1.5 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-lg hover:bg-emerald-100"
                    >
                        + {qt.label}
                    </button>
                ))}
            </div>

            {questions.length === 0 && (
                <p className="text-sm text-slate-500 py-8 text-center border-2 border-dashed rounded-xl">
                    No questions yet. Click a question type above to add one.
                </p>
            )}

            {questions.map((question, qIndex) => (
                <div key={qIndex} className="border rounded-xl p-4 bg-slate-50">
                    <div className="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <span className="font-medium text-sm">Question {qIndex + 1}</span>
                        <div className="flex gap-2">
                            <button type="button" onClick={() => moveQuestion(qIndex, -1)} className="text-xs text-slate-500">↑</button>
                            <button type="button" onClick={() => moveQuestion(qIndex, 1)} className="text-xs text-slate-500">↓</button>
                            <button type="button" onClick={() => removeQuestion(qIndex)} className="text-xs text-red-600">Delete</button>
                        </div>
                    </div>

                    <div className="grid sm:grid-cols-3 gap-3 mb-3">
                        <div className="sm:col-span-2">
                            <select
                                value={question.type}
                                onChange={(e) => changeType(qIndex, e.target.value)}
                                className="w-full rounded-lg border-slate-300 text-sm"
                            >
                                {QUESTION_TYPES.map((qt) => (
                                    <option key={qt.value} value={qt.value}>{qt.label}</option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <input
                                type="number"
                                min="0"
                                step="0.5"
                                value={question.points}
                                onChange={(e) => updateQuestion(qIndex, { ...question, points: parseFloat(e.target.value) || 0 })}
                                className="w-full rounded-lg border-slate-300 text-sm"
                                placeholder="Points"
                            />
                        </div>
                    </div>

                    <textarea
                        value={question.question_text}
                        onChange={(e) => updateQuestion(qIndex, { ...question, question_text: e.target.value })}
                        rows={2}
                        className="w-full rounded-lg border-slate-300 text-sm"
                        placeholder="Question text"
                        required
                    />

                    {question.type === 'image_based' && (
                        <div className="mt-2 space-y-2">
                            <input
                                type="url"
                                value={question.image_path || ''}
                                onChange={(e) => updateQuestion(qIndex, { ...question, image_path: e.target.value })}
                                className="w-full rounded-lg border-slate-300 text-sm"
                                placeholder="Question image URL"
                            />
                            {question.image_path && (
                                <img src={question.image_path} alt="Question reference" className="max-h-40 rounded-lg object-contain border" />
                            )}
                        </div>
                    )}

                    <OptionEditor question={question} qIndex={qIndex} onChange={updateQuestion} />

                    <input
                        type="text"
                        value={question.explanation || ''}
                        onChange={(e) => updateQuestion(qIndex, { ...question, explanation: e.target.value })}
                        className="w-full mt-3 rounded-lg border-slate-300 text-sm"
                        placeholder="Explanation (shown with instant feedback)"
                    />
                </div>
            ))}
        </div>
    );
}
