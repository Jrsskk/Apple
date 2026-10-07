@extends('layouts.teacher')

@section('header', 'Create Quiz')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <!-- Top Action Bar / Page Heading -->
    <div class="md:flex md:items-center md:justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight sm:text-3xl">Create New Quiz</h1>
            <p class="mt-1 text-sm text-slate-500">Set up instructions, timing, rules, and generate or manually add questions.</p>
        </div>
        <div class="mt-4 md:mt-0 flex items-center gap-3">
            <a href="{{ route('teacher.quizzes.index') }}" 
               class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-slate-500 transition-colors">
                Back to Quizzes
            </a>
        </div>
    </div>

    <!-- Main Quiz Setup Form -->
    <form id="create-quiz-form" method="POST" action="{{ route('teacher.quizzes.store') }}" class="space-y-6">
        @csrf

        <!-- Section 1: General Details -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-lg font-bold text-slate-900">1. Basic Information</h2>
                <p class="text-xs text-slate-500">Title, target class, and instructions for students.</p>
            </div>

            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Quiz Title <span class="text-rose-500">*</span></label>
                    <input type="text" 
                           name="title" 
                           value="{{ old('title') }}" 
                           placeholder="e.g. Midterm Examination - Biology 101"
                           required 
                           class="w-full rounded-xl border-slate-200 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2.5 px-3.5 shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Class Assignment <span class="text-rose-500">*</span></label>
                    <select name="school_class_id" required class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2.5 px-3.5 shadow-sm" id="class-select">
                        @foreach($classes as $class)
                            <option value="{{ $class->id }}" data-subject="{{ $class->subject_id }}" @selected(old('school_class_id') == $class->id)>
                                {{ $class->display_name }} — {{$class->subject?->name }}
                            </option>
                        @endforeach
                    </select>
                    <input type="hidden" name="subject_id" id="subject-id" value="{{ old('subject_id', $classes->first()?->subject_id) }}">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Instructions</label>
                    <textarea name="instructions" 
                              rows="3" 
                              placeholder="Add special instructions, allowed materials, or notes for students..."
                              class="w-full rounded-xl border-slate-200 text-slate-900 placeholder:text-slate-400 focus:border-emerald-500 focus:ring-emerald-500 text-sm p-3.5 shadow-sm">{{ old('instructions') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Section 2: Timing & Scoring Rules -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <h2 class="text-lg font-bold text-slate-900">2. Timing & Rules</h2>
                <p class="text-xs text-slate-500">Define test duration, schedule, passing thresholds, and retake attempts.</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Duration (Minutes) <span class="text-rose-500">*</span></label>
                    <div class="relative rounded-xl shadow-sm">
                        <input type="number" name="duration_minutes" value="{{ old('duration_minutes', 30) }}" min="1" max="480" required class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2.5 pl-3.5 pr-12">
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5">
                            <span class="text-xs font-medium text-slate-400">min</span>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Max Attempts Allowed <span class="text-rose-500">*</span></label>
                    <input type="number" name="max_attempts" value="{{ old('max_attempts', 1) }}" min="1" max="10" required class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2.5 px-3.5 shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Starts At</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2.5 px-3.5 shadow-sm">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Deadline</label>
                    <input type="datetime-local" name="deadline" value="{{ old('deadline') }}" class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2.5 px-3.5 shadow-sm">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Passing Score Threshold (%) <span class="text-rose-500">*</span></label>
                    <div class="relative rounded-xl shadow-sm max-w-xs">
                        <input type="number" name="passing_score" value="{{ old('passing_score', 60) }}" min="0" max="100" required class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2.5 pl-3.5 pr-12">
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3.5">
                            <span class="text-xs font-medium text-slate-400">%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Randomization Settings -->
        <div class="bg-white rounded-2xl border border-slate-200/80 shadow-sm p-6 sm:p-8 space-y-4">
            <div class="border-b border-slate-100 pb-3">
                <h2 class="text-lg font-bold text-slate-900">3. Security & Anti-Cheat Settings</h2>
            </div>
            
            <div class="grid sm:grid-cols-2 gap-4 pt-2">
                <label class="relative flex items-start p-4 rounded-xl border border-slate-200 hover:border-emerald-200 hover:bg-emerald-50/30 transition-all cursor-pointer">
                    <div class="flex items-center h-5">
                        <input type="checkbox" name="randomize_questions" value="1" @checked(old('randomize_questions')) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <span class="font-semibold text-slate-900">Randomize Question Order</span>
                        <p class="text-xs text-slate-500 mt-0.5">Shuffles question sequence for each attempt.</p>
                    </div>
                </label>

                <label class="relative flex items-start p-4 rounded-xl border border-slate-200 hover:border-emerald-200 hover:bg-emerald-50/30 transition-all cursor-pointer">
                    <div class="flex items-center h-5">
                        <input type="checkbox" name="randomize_choices" value="1" @checked(old('randomize_choices')) class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    </div>
                    <div class="ml-3 text-sm">
                        <span class="font-semibold text-slate-900">Randomize Multiple Choice Options</span>
                        <p class="text-xs text-slate-500 mt-0.5">Shuffles option ordering for multiple choice items.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- Section 4: PDF Preview Container -->
        <div id="pdf-generated-questions" class="space-y-4 hidden">
            <!-- Dynamically populated via renderGeneratedPreview() -->
        </div>

        <!-- Form Actions -->
        <div class="flex items-center justify-end gap-3 pt-4">
            <a href="{{ route('teacher.quizzes.index') }}" class="px-5 py-2.5 text-sm font-semibold text-slate-700 bg-white border border-slate-300 rounded-xl hover:bg-slate-50 transition-colors">
                Cancel
            </a>
            <button type="submit" class="inline-flex items-center gap-2 px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm rounded-xl shadow-md transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                </svg>
                Create & Add Questions
            </button>
        </div>
    </form>
</div>

<!-- Modal: Build from PDF -->
<div id="pdf-modal" class="fixed inset-0 z-50 hidden bg-slate-950/70 backdrop-blur-sm p-4 overflow-y-auto flex items-center justify-center">
    <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 sm:p-8 shadow-2xl border border-slate-100 transition-all">
        <!-- Modal Header -->
        <div class="mb-6 flex items-center justify-between border-b border-slate-100 pb-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Build Quiz from PDF</h3>
                    <p class="text-xs text-slate-500">Auto-extract questions directly from a document.</p>
                </div>
            </div>
            <button type="button" id="close-pdf-modal" class="p-1.5 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <form id="pdf-form" class="space-y-5">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Upload PDF Document</label>
                <div class="relative border-2 border-dashed border-slate-200 rounded-xl p-4 text-center hover:border-emerald-400 transition-colors bg-slate-50/50">
                    <input type="file" 
                           name="pdf_file" 
                           accept="application/pdf" 
                           required 
                           class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500 file:cursor-pointer cursor-pointer">
                    <p class="text-[11px] text-slate-400 mt-2">PDF files up to 10MB supported</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Questions</label>
                    <input type="number" name="question_count" value="5" min="1" max="12" class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2 px-3 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Difficulty</label>
                    <select name="difficulty" class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2 px-3 shadow-sm">
                        <option value="easy">Easy</option>
                        <option value="medium" selected>Medium</option>
                        <option value="hard">Hard</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">Type</label>
                    <select name="question_type" class="w-full rounded-xl border-slate-200 text-slate-900 focus:border-emerald-500 focus:ring-emerald-500 text-sm py-2 px-3 shadow-sm">
                        <option value="multiple_choice" selected>Multiple Choice</option>
                        <option value="true_false">True / False</option>
                        <option value="identification">Identification</option>
                    </select>
                </div>
            </div>

            <!-- Feedback Notifications -->
            <div id="pdf-error" class="hidden rounded-xl border border-rose-200 bg-rose-50/80 p-3.5 text-xs font-medium text-rose-700 flex items-center gap-2">
                <svg class="w-4 h-4 text-rose-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span id="pdf-error-text">Unable to generate questions from the PDF.</span>
            </div>

            <div id="pdf-success" class="hidden rounded-xl border border-emerald-200 bg-emerald-50/80 p-3.5 text-xs font-medium text-emerald-800 flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span id="pdf-success-text">Questions generated successfully.</span>
            </div>

            <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                <button type="button" id="cancel-pdf" class="px-4 py-2 rounded-xl border border-slate-300 text-slate-700 hover:bg-slate-50 text-xs font-semibold transition-colors">
                    Cancel
                </button>
                <button type="submit" id="submit-pdf-btn" class="inline-flex items-center gap-2 px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold shadow-sm transition-all">
                    <span id="pdf-btn-spinner" class="hidden">
                        <svg class="animate-spin -ml-1 mr-1 h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span id="pdf-btn-text">Generate Questions</span>
                </button>
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
const submitPdfBtn = document.getElementById('submit-pdf-btn');
const pdfBtnSpinner = document.getElementById('pdf-btn-spinner');
const pdfBtnText = document.getElementById('pdf-btn-text');

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
    if (pdfBtnSpinner) pdfBtnSpinner.classList.add('hidden');
    if (pdfBtnText) pdfBtnText.textContent = 'Generate Questions';
    if (submitPdfBtn) submitPdfBtn.disabled = false;
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
    generatedQuestionsWrap.innerHTML = `
        <div class="bg-emerald-50/60 border border-emerald-200/80 rounded-2xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">PDF Generated Questions Preview</h3>
                    <p class="text-xs text-slate-500">${questions.length} questions attached to this form</p>
                </div>
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Ready for submission
                </span>
            </div>
            
            <div class="space-y-3">
                ${questions.map((question, index) => `
                    <div class="rounded-xl border border-emerald-200/70 bg-white p-4 shadow-sm">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-emerald-100 text-emerald-800">
                                Q${index + 1} &bull; ${question.type ? question.type.replace('_', ' ').toUpperCase() : 'MULTIPLE CHOICE'}
                            </span>
                            <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md">${question.points ?? 1} pt(s)</span>
                        </div>
                        <p class="font-medium text-sm text-slate-800">${question.question_text \vert{}\vert{} 'Untitled question'}</p>${(question.options && question.options.length > 0) ? `
                            <ul class="mt-3 space-y-1.5 border-t border-slate-100 pt-2 text-xs text-slate-600">
                                ${question.options.map((option) => `
                                    <li class="flex items-center gap-2 ${option.is_correct ? 'text-emerald-700 font-medium' : ''}">
                                        <span class="h-1.5 w-1.5 rounded-full ${option.is_correct ? 'bg-emerald-500' : 'bg-slate-300'}"></span>
                                        ${option.option_text ?? ''}
                                        ${option.is_correct ? '<span class="ml-auto text-[10px] text-emerald-600 font-semibold uppercase">(Correct)</span>' : ''}
                                    </li>
                                `).join('')}
                            </ul>
                        ` : ''}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
    generatedQuestionsWrap.classList.remove('hidden');
};

pdfForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    const formData = new FormData(pdfForm);
    pdfError.classList.add('hidden');
    pdfSuccess.classList.add('hidden');

    if (submitPdfBtn) submitPdfBtn.disabled = true;
    if (pdfBtnSpinner) pdfBtnSpinner.classList.remove('hidden');
    if (pdfBtnText) pdfBtnText.textContent = 'Generating...';

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
        
        const successMsg = document.getElementById('pdf-success-text');
        if (successMsg) successMsg.textContent = payload.message || 'Questions generated successfully.';
        
        pdfSuccess.classList.remove('hidden');
        setTimeout(() => closeModal(), 800);
    } catch (error) {
        const errorMsg = document.getElementById('pdf-error-text');
        if (errorMsg) errorMsg.textContent = error.message || 'Something went wrong while generating the quiz.';
        
        pdfError.classList.remove('hidden');
        if (submitPdfBtn) submitPdfBtn.disabled = false;
        if (pdfBtnSpinner) pdfBtnSpinner.classList.add('hidden');
        if (pdfBtnText) pdfBtnText.textContent = 'Generate Questions';
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