@extends('layouts.student')

@section('header', $quiz->title)

@section('content')
<div id="offline-quiz" class="space-y-4">
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-3 text-sm text-yellow-800">
        Offline mode — answers will sync when you're back online.
    </div>
    <div id="quiz-timer" class="text-right font-mono text-red-600"></div>
    <div id="quiz-loading" class="text-center text-slate-500 py-8">Loading quiz...</div>
    <div id="quiz-content" class="hidden space-y-4"></div>
</div>
@endsection

@push('scripts')
<script>
(async function() {
    const quizId = {{ $quiz->id }};
    const container = document.getElementById('quiz-content');
    const loading = document.getElementById('quiz-loading');

    if (!window.EduSyncOffline) {
        loading.textContent = 'Offline module not loaded.';
        return;
    }

    const pack = await window.EduSyncOffline.getQuiz(quizId);
    if (!pack) {
        loading.textContent = 'Quiz not downloaded. Connect to internet and download first.';
        return;
    }

    const quiz = pack.quiz || pack;
    const attemptCount = Number(pack.attempt_count || 0);
    const maxAttempts = Number(pack.max_attempts ?? quiz.max_attempts ?? 0);
    if (maxAttempts && attemptCount >= maxAttempts) {
        loading.textContent = 'Maximum attempts reached.';
        return;
    }

    const attempt = await window.EduSyncOffline.createOfflineQuizAttempt(quizId);
    const questions = pack.questions || quiz.questions || [];
    const answers = {};
    const sequenceOrders = {};
    let current = 0;
    let remainingSeconds = Math.max(
        0,
        (Number(pack.duration_minutes ?? quiz.duration_minutes ?? 0) * 60)
            - Math.floor((Date.now() - new Date(attempt.started_at).getTime()) / 1000)
    );
    let isSubmitting = false;

    loading.classList.add('hidden');
    container.classList.remove('hidden');

    function render() {
        const question = questions[current];
        if (!question) return;

        container.innerHTML = `
            <div class="bg-white rounded-xl p-4 shadow-sm border">
                <p id="question-count" class="text-sm text-slate-500 mb-2"></p>
                <p id="question-text" class="font-medium mb-4"></p>
                <div id="options" class="space-y-2"></div>
            </div>
            <div class="flex gap-3">
                ${current > 0 ? '<button type="button" id="prev-btn" class="flex-1 py-3 bg-slate-200 rounded-xl">Previous</button>' : ''}
                ${current < questions.length - 1
                    ? '<button type="button" id="next-btn" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl">Next</button>'
                    : '<button type="button" id="submit-btn" class="flex-1 py-3 bg-green-600 text-white rounded-xl">Submit Quiz</button>'}
            </div>`;

        const optionsElement = document.getElementById('options');
        const type = question.type?.value ?? question.type;
        const saved = answers[question.id] || {};
        const options = question.options || [];

        document.getElementById('question-count').textContent = `Question ${current + 1} of ${questions.length}`;
        document.getElementById('question-text').textContent = question.question_text || '';

        if (['multiple_choice', 'true_false', 'image_based'].includes(type)) {
            options.forEach((option) => {
                const label = document.createElement('label');
                const input = document.createElement('input');
                const text = document.createElement('span');
                label.className = 'flex items-center gap-3 p-3 border rounded-xl';
                input.type = 'radio';
                input.name = `q-${question.id}`;
                input.value = option.id;
                input.className = 'w-5 h-5';
                input.checked = (saved.selected_options || []).map(Number).includes(Number(option.id));
                text.textContent = option.option_text || '';
                label.append(input, text);
                optionsElement.appendChild(label);
            });
        } else if (type === 'multiple_selection') {
            options.forEach((option) => {
                const label = document.createElement('label');
                const input = document.createElement('input');
                const text = document.createElement('span');
                label.className = 'flex items-center gap-3 p-3 border rounded-xl';
                input.type = 'checkbox';
                input.value = option.id;
                input.className = 'w-5 h-5';
                input.checked = (saved.selected_options || []).map(Number).includes(Number(option.id));
                text.textContent = option.option_text || '';
                label.append(input, text);
                optionsElement.appendChild(label);
            });
        } else if (type === 'matching') {
            const leftOptions = options.filter((option) => option.side === 'left');
            const rightOptions = options.filter((option) => option.side === 'right');
            leftOptions.forEach((option) => {
                const label = document.createElement('label');
                const text = document.createElement('span');
                const select = document.createElement('select');
                label.className = 'grid gap-2 sm:grid-cols-2 sm:items-center';
                text.textContent = option.option_text || '';
                select.className = 'matching-option rounded-lg border-slate-300';
                select.dataset.leftId = option.id;
                const empty = document.createElement('option');
                empty.value = '';
                empty.textContent = 'Choose a match';
                select.appendChild(empty);
                rightOptions.forEach((match) => {
                    const choice = document.createElement('option');
                    choice.value = match.id;
                    choice.textContent = match.option_text || '';
                    choice.selected = String(saved.selected_options?.[option.id] ?? '') === String(match.id);
                    select.appendChild(choice);
                });
                label.append(text, select);
                optionsElement.appendChild(label);
            });
        } else if (type === 'sequencing' || type === 'drag_drop') {
            const savedOrder = (saved.selected_options || []).map(Number);
            sequenceOrders[question.id] = savedOrder.length
                ? [...savedOrder, ...options.map((option) => Number(option.id)).filter((id) => !savedOrder.includes(id))]
                : options.map((option) => Number(option.id));
            const list = document.createElement('div');
            list.className = 'space-y-2';
            optionsElement.appendChild(list);
            renderSequence(question, options, list);
        } else if (type === 'identification') {
            const input = document.createElement('input');
            input.type = 'text';
            input.id = 'text-answer';
            input.className = 'w-full p-3 border rounded-xl';
            input.placeholder = 'Your answer';
            input.value = saved.answer_text || '';
            optionsElement.appendChild(input);
        }

        document.getElementById('prev-btn')?.addEventListener('click', () => {
            saveCurrent();
            current--;
            render();
        });
        document.getElementById('next-btn')?.addEventListener('click', () => {
            saveCurrent();
            current++;
            render();
        });
        document.getElementById('submit-btn')?.addEventListener('click', submitQuiz);
    }

    function renderSequence(question, options, list) {
        const labels = new Map(options.map((option) => [Number(option.id), option.option_text || '']));
        list.replaceChildren();
        sequenceOrders[question.id].forEach((optionId, index) => {
            const row = document.createElement('div');
            const number = document.createElement('span');
            const text = document.createElement('span');
            const up = document.createElement('button');
            const down = document.createElement('button');
            row.className = 'flex items-center gap-3 rounded-lg border p-3';
            number.className = 'w-6 text-sm font-semibold text-slate-500';
            number.textContent = `${index + 1}.`;
            text.className = 'flex-1';
            text.textContent = labels.get(optionId) || '';
            up.type = down.type = 'button';
            up.textContent = 'Move up';
            down.textContent = 'Move down';
            up.disabled = index === 0;
            down.disabled = index === sequenceOrders[question.id].length - 1;
            up.className = down.className = 'rounded bg-slate-100 px-3 py-2 text-sm disabled:opacity-40';
            up.addEventListener('click', () => moveSequence(question, options, list, index, -1));
            down.addEventListener('click', () => moveSequence(question, options, list, index, 1));
            row.append(number, text, up, down);
            list.appendChild(row);
        });
    }

    function moveSequence(question, options, list, index, offset) {
        const target = index + offset;
        const order = sequenceOrders[question.id];
        if (target < 0 || target >= order.length) return;
        [order[index], order[target]] = [order[target], order[index]];
        renderSequence(question, options, list);
    }

    function saveCurrent() {
        const question = questions[current];
        const type = question.type?.value ?? question.type;

        if (['multiple_choice', 'true_false', 'image_based'].includes(type)) {
            const selected = document.querySelector(`input[name="q-${question.id}"]:checked`);
            answers[question.id] = {
                questionId: question.id,
                selected_options: selected ? [Number(selected.value)] : null,
            };
        } else if (type === 'multiple_selection') {
            answers[question.id] = {
                questionId: question.id,
                selected_options: [...document.querySelectorAll('#options input[type="checkbox"]:checked')]
                    .map((input) => Number(input.value)),
            };
        } else if (type === 'matching') {
            answers[question.id] = {
                questionId: question.id,
                selected_options: Object.fromEntries(
                    [...document.querySelectorAll('#options select.matching-option')]
                        .filter((select) => select.value !== '')
                        .map((select) => [select.dataset.leftId, Number(select.value)])
                ),
            };
        } else if (type === 'sequencing' || type === 'drag_drop') {
            answers[question.id] = { questionId: question.id, selected_options: sequenceOrders[question.id] };
        } else {
            answers[question.id] = {
                questionId: question.id,
                answer_text: document.getElementById('text-answer')?.value || '',
            };
        }

        window.EduSyncOffline.saveQuizAnswer(attempt.sync_uuid, question.id, answers[question.id]);
    }

    async function submitQuiz() {
        if (isSubmitting) return;
        isSubmitting = true;
        saveCurrent();
        await window.EduSyncOffline.submitQuizOffline(attempt.sync_uuid, quizId, Object.values(answers));
        alert('Quiz submitted offline. It will sync when you reconnect.');
        window.location.href = '/student/sync';
    }

    function tick() {
        const timer = document.getElementById('quiz-timer');
        if (remainingSeconds <= 0) {
            if (!isSubmitting) submitQuiz();
            return;
        }
        timer.textContent = `${Math.floor(remainingSeconds / 60)}:${String(remainingSeconds % 60).padStart(2, '0')}`;
        remainingSeconds--;
    }

    render();
    tick();
    setInterval(tick, 1000);
})();
</script>
@endpush
