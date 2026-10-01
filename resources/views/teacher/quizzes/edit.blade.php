@extends('layouts.teacher')

@section('header', 'Edit Quiz')

@section('content')
<form id="edit-quiz-form" method="POST" action="{{ route('teacher.quizzes.update', $quiz) }}" class="bg-white rounded-xl shadow-sm border p-6 space-y-4">
    @csrf @method('PUT')
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-xl font-semibold text-slate-900">Quiz editor</h2>
        <button type="button" id="open-pdf-modal" class="px-4 py-2 bg-emerald-100 text-emerald-700 border border-emerald-200 rounded-lg text-sm font-medium">Build from PDF</button>
    </div>
    <div class="grid lg:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Title</label>
            <input type="text" name="title" value="{{ old('title', $quiz->title) }}" required class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Status</label>
            <select name="status" class="w-full rounded-lg border-slate-300">
                @foreach(['draft','published','archived'] as $s)
                <option value="{{ $s }}" @selected(old('status', $quiz->status->value) === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <input type="hidden" name="subject_id" value="{{ $quiz->subject_id }}">
    <input type="hidden" name="school_class_id" value="{{ $quiz->school_class_id }}">
    <input type="hidden" name="duration_minutes" value="{{ $quiz->duration_minutes }}">
    <input type="hidden" name="max_attempts" value="{{ $quiz->max_attempts }}">
    <input type="hidden" name="passing_score" value="{{ $quiz->passing_score }}">
    <div>
        <label class="block text-sm font-medium mb-1">Instructions</label>
        <textarea name="instructions" rows="3" class="w-full rounded-lg border-slate-300">{{ old('instructions', $quiz->instructions) }}</textarea>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Starts At</label>
            <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $quiz->starts_at?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Deadline</label>
            <input type="datetime-local" name="deadline" value="{{ old('deadline', $quiz->deadline?->format('Y-m-d\TH:i')) }}" class="w-full rounded-lg border-slate-300">
        </div>
    </div>

    <div class="flex flex-wrap gap-3 pt-2">
        @if($quiz->status->value !== 'published')
        <form method="POST" action="{{ route('teacher.quizzes.publish', $quiz) }}">@csrf<button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-sm">Publish</button></form>
        @endif
        <form method="POST" action="{{ route('teacher.quizzes.duplicate', $quiz) }}">@csrf<button type="submit" class="px-4 py-2 bg-slate-600 text-white rounded-lg text-sm">Duplicate</button></form>
    </div>

    <div id="pdf-generated-questions" class="space-y-3 hidden"></div>

    <hr class="my-4">
    <h3 class="font-semibold">Questions ({{ $quiz->questions->count() }})</h3>
    <div class="space-y-3">
        @foreach($quiz->questions as $q)
        <div class="border rounded-lg p-3 text-sm">
            <p class="font-medium">{{ $loop->iteration }}. {{ $q->question_text }}</p>
            <p class="text-slate-500 mt-1">{{ $q->type->value ?? $q->type }} · {{ $q->points }} pt(s) · {{ $q->options->count() }} options</p>
        </div>
        @endforeach
    </div>
    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg">Save Quiz</button>
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
const editQuizForm = document.getElementById('edit-quiz-form');

const openModal = () => {
    pdfModal.classList.remove('hidden');
    pdfError.classList.add('hidden');
    pdfSuccess.classList.add('hidden');
};

const closeModal = () => {
    pdfModal.classList.add('hidden');
    pdfForm.reset();
};

openPdfModal?.addEventListener('click', openModal);
closePdfModal?.addEventListener('click', closeModal);
cancelPdf?.addEventListener('click', closeModal);

const removeGeneratedQuestionFields = () => {
    editQuizForm.querySelectorAll('[data-generated-question]').forEach((el) => el.remove());
};

const addGeneratedQuestionFields = (questions) => {
    removeGeneratedQuestionFields();

    questions.forEach((question, qIndex) => {
        const pushField = (name, value, isBoolean = false) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value ?? '');
            input.dataset.generatedQuestion = 'true';
            if (isBoolean) input.value = value ? '1' : '0';
            editQuizForm.appendChild(input);
        };

        pushField(`questions[${qIndex}][type]`, question.type);
        pushField(`questions[${qIndex}][question_text]`, question.question_text);
        pushField(`questions[${qIndex}][points]`, question.points ?? 1);
        pushField(`questions[${qIndex}][explanation]`, question.explanation ?? '');

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
</script>
@endpush
@endsection
