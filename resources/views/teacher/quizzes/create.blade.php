@extends('layouts.teacher')

@section('header', 'Create Quiz')

@section('content')
<form id="create-quiz-form" method="POST" action="{{ route('teacher.quizzes.store') }}" class="bg-white rounded-xl shadow-sm border p-6 max-w-3xl space-y-4">
    @csrf
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-slate-900">Quiz setup</h2>
        <button type="button" id="open-pdf-modal" class="px-4 py-2 bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-sm font-medium">Build from PDF</button>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Title</label>
        <input type="text" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Class</label>
        <select name="school_class_id" required class="w-full rounded-lg border-slate-300" id="class-select">
            @foreach($classes as $class)
            <option value="{{ $class->id }}" data-subject="{{ $class->subject_id }}" @selected(old('school_class_id') == $class->id)>{{ $class->display_name }} — {{ $class->subject?->name }}</option>
            @endforeach
        </select>
        <input type="hidden" name="subject_id" id="subject-id" value="{{ old('subject_id', $classes->first()?->subject_id) }}">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Instructions</label>
        <textarea name="instructions" rows="3" class="w-full rounded-lg border-slate-300">{{ old('instructions') }}</textarea>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Duration (minutes)</label>
            <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 30) }}" min="1" max="480" required class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Max Attempts</label>
            <input type="number" name="max_attempts" value="{{ old('max_attempts', 1) }}" min="1" max="10" required class="w-full rounded-lg border-slate-300">
        </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Starts At</label>
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Deadline</label>
            <input type="datetime-local" name="deadline" value="{{ old('deadline') }}" class="w-full rounded-lg border-slate-300">
        </div>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Passing Score (%)</label>
        <input type="number" name="passing_score" value="{{ old('passing_score', 60) }}" min="0" max="100" required class="w-full rounded-lg border-slate-300">
    </div>
    <div class="flex flex-wrap gap-4 text-sm">
        <label class="flex items-center gap-2"><input type="checkbox" name="randomize_questions" value="1" @checked(old('randomize_questions'))> Randomize questions</label>
        <label class="flex items-center gap-2"><input type="checkbox" name="randomize_choices" value="1" @checked(old('randomize_choices'))> Randomize choices</label>
    </div>
    <div id="pdf-generated-questions" class="space-y-3 hidden"></div>
    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg">Create & Add Questions</button>
</form>

<div id="pdf-modal" class="fixed inset-0 z-50 hidden bg-slate-950/60 p-4">
    <div class="mx-auto max-w-xl rounded-2xl bg-white p-6 shadow-xl">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="text-xl font-semibold text-slate-900">Build from PDF</h3>
            <button type="button" id="close-pdf-modal" class="text-sm text-slate-500">Close</button>
        </div>
        <form id="pdf-form" class="space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium mb-1">Upload PDF</label>
                <input type="file" name="pdf_file" accept="application/pdf" required class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white">
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Questions</label>
                    <input type="number" name="question_count" value="5" min="1" max="12" class="w-full rounded-lg border-slate-300">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Difficulty</label>
                    <select name="difficulty" class="w-full rounded-lg border-slate-300">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Type</label>
                    <select name="question_type" class="w-full rounded-lg border-slate-300">
                        <option value="multiple_choice" selected>Multiple Choice</option>
                        <option value="true_false">True / False</option>
                        <option value="identification">Identification</option>
                    </select>
                </div>
            </div>
            <div id="pdf-error" class="hidden rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700"></div>
            <div id="pdf-success" class="hidden rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700"></div>
            <div class="flex justify-end gap-3">
                <button type="button" id="cancel-pdf" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white">Generate</button>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
const pdfModal = document.getElementById('pdf-modal');
const openPdfModal = document.getElementById('open-pdf-modal');
const closePdfModal = document.getElementById('close-pdf-modal');
const cancelPdf = document.getElementById('cancel-pdf');
const pdfForm = document.getElementById('pdf-form');
const pdfError = document.getElementById('pdf-error');
const pdfSuccess = document.getElementById('pdf-success');
const generatedQuestionsWrap = document.getElementById('pdf-generated-questions');
const createQuizForm = document.getElementById('create-quiz-form');

const syncSubjectField = () => {
    const select = document.getElementById('class-select');
    const hiddenSubject = document.getElementById('subject-id');
    if (!select || !hiddenSubject) {
        return;
    }

    hiddenSubject.value = select.selectedOptions[0]?.dataset.subject || '';
};

const openModal = () => {
    pdfModal.classList.remove('hidden');
    pdfError.classList.add('hidden');
    pdfSuccess.classList.add('hidden');
};

const closeModal = () => {
    pdfModal.classList.add('hidden');
    pdfForm.reset();
};

syncSubjectField();

openPdfModal?.addEventListener('click', openModal);
closePdfModal?.addEventListener('click', closeModal);
cancelPdf?.addEventListener('click', closeModal);

const removeGeneratedQuestionFields = () => {
    createQuizForm.querySelectorAll('[data-generated-question]').forEach((el) => el.remove());
};

const addGeneratedQuestionFields = (questions) => {
    removeGeneratedQuestionFields();

    questions.forEach((question, qIndex) => {
        const fieldNames = {
            type: `questions[${qIndex}][type]`,
            question_text: `questions[${qIndex}][question_text]`,
            points: `questions[${qIndex}][points]`,
            explanation: `questions[${qIndex}][explanation]`,
        };

        const pushField = (name, value, isBoolean = false) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value ?? '');
            input.dataset.generatedQuestion = 'true';
            if (isBoolean) input.value = value ? '1' : '0';
            createQuizForm.appendChild(input);
        };

        pushField(fieldNames.type, question.type);
        pushField(fieldNames.question_text, question.question_text);
        pushField(fieldNames.points, question.points ?? 1);
        pushField(fieldNames.explanation, question.explanation ?? '');

        (question.options || []).forEach((option, optionIndex) => {
            pushField(`questions[${qIndex}][options][${optionIndex}][option_text]`, option.option_text ?? '');
            pushField(`questions[${qIndex}][options][${optionIndex}][is_correct]`, option.is_correct ?? false, true);
            pushField(`questions[${qIndex}][options][${optionIndex}][match_key]`, option.match_key ?? '');
        });
    });
};

const renderGeneratedPreview = (questions) => {
    generatedQuestionsWrap.innerHTML = questions.map((question, index) => `
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-3">
            <div class="flex items-center justify-between gap-3">
                <p class="font-medium text-slate-800">Question ${index + 1}</p>
                <span class="text-xs text-slate-600">${question.points ?? 1} pt(s)</span>
            </div>
            <p class="mt-2 text-sm text-slate-700">${question.question_text || 'Untitled question'}</p>
            <ul class="mt-2 space-y-1 text-xs text-slate-600">
                ${(question.options || []).map((option) => `<li>• ${option.option_text ?? ''}</li>`).join('')}
            </ul>
        </div>
    `).join('');
    generatedQuestionsWrap.classList.remove('hidden');
};

pdfForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(pdfForm);
    pdfError.classList.add('hidden');
    pdfSuccess.classList.add('hidden');

    try {
        const response = await fetch('{{ route('teacher.quizzes.generate-from-pdf') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
            body: formData,
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || 'Unable to generate questions from the PDF.');
        }

        if (!Array.isArray(payload.questions) || payload.questions.length === 0) {
            throw new Error('No quiz questions were generated from the uploaded PDF.');
        }

        addGeneratedQuestionFields(payload.questions);
        renderGeneratedPreview(payload.questions);
        pdfSuccess.textContent = payload.message || 'Questions generated successfully.';
        pdfSuccess.classList.remove('hidden');
        setTimeout(() => closeModal(), 800);
    } catch (error) {
        pdfError.textContent = error.message || 'Something went wrong while generating the quiz.';
        pdfError.classList.remove('hidden');
    }
});

document.getElementById('class-select')?.addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    document.getElementById('subject-id').value = opt?.dataset.subject || '';
});

createQuizForm?.addEventListener('submit', () => {
    syncSubjectField();
});
</script>
@endpush
@endsection
