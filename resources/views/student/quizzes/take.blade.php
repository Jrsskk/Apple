@extends('layouts.student')

@section('header', 'Take Quiz')

@section('content')
<div x-data="quizTaker({{ $attempt->id }}, {{ $questions->count() }}, {{ $quiz->duration_minutes * 60 }})"
     @sequence-changed="saveAnswer($event.detail.questionId, null, $event.detail.order)"
     class="space-y-4">
    <div class="bg-white rounded-xl p-4 shadow-sm border">
        <div class="flex justify-between items-center mb-2">
            <h2 class="font-bold text-lg">{{ $quiz->title }}</h2>
            <span class="text-sm font-mono text-red-600" x-text="timerDisplay"></span>
        </div>
        <div class="w-full bg-slate-200 rounded-full h-2">
            <div class="bg-indigo-600 h-2 rounded-full transition-all" :style="`width: ${progress}%`"></div>
        </div>
        <p class="text-sm text-slate-500 mt-2">Question <span x-text="current + 1"></span> of {{ $questions->count() }}</p>
    </div>

    @foreach($questions as $index => $question)
    @php
        $savedAnswer = $attempt->answers->firstWhere('quiz_question_id', $question->id);
        $savedOptions = (array) ($savedAnswer?->selected_options ?? []);
        $savedOptionIds = array_map('intval', array_values($savedOptions));
    @endphp
    <div x-show="current === {{ $index }}" class="bg-white rounded-xl p-4 shadow-sm border">
        <p class="font-medium text-slate-800 mb-4">{!! nl2br(e($question->question_text)) !!}</p>

        @if(in_array($question->type->value, ['multiple_choice', 'true_false', 'image_based']))
            <div class="space-y-2">
                @foreach($question->options as $option)
                <label class="flex items-center gap-3 p-4 border rounded-xl cursor-pointer hover:bg-indigo-50 active:bg-indigo-100 transition min-h-[52px]">
                    <input type="radio" name="q_{{ $question->id }}" value="{{ $option->id }}"
                           @checked(in_array((int) $option->id, $savedOptionIds, true))
                           @change="saveAnswer({{ $question->id }}, null, [{{ $option->id }}])"
                           class="w-5 h-5 text-indigo-600">
                    <span class="text-base">{{ $option->option_text }}</span>
                </label>
                @endforeach
            </div>
        @elseif($question->type->value === 'multiple_selection')
            <div class="space-y-2">
                @foreach($question->options as $option)
                <label class="flex items-center gap-3 p-4 border rounded-xl cursor-pointer hover:bg-indigo-50 min-h-[52px]">
                    <input type="checkbox" value="{{ $option->id }}"
                           @checked(in_array((int) $option->id, $savedOptionIds, true))
                           @change="saveMulti({{ $question->id }})"
                           class="option-check w-5 h-5" data-qid="{{ $question->id }}">
                    <span>{{ $option->option_text }}</span>
                </label>
                @endforeach
            </div>
        @elseif($question->type->value === 'identification')
            <input type="text" class="w-full p-4 border rounded-xl text-base"
                   value="{{ $savedAnswer?->answer_text }}"
                   placeholder="Type your answer..."
                   @blur="saveAnswer({{ $question->id }}, $event.target.value, null)">
        @elseif($question->type->value === 'matching')
            @php
                $leftOptions = $question->options->where('is_correct', false);
                $rightOptions = $question->options->where('is_correct', true);
            @endphp
            <div class="space-y-3">
                @foreach($leftOptions as $option)
                <label class="grid gap-2 sm:grid-cols-2 sm:items-center">
                    <span>{{ $option->option_text }}</span>
                    <select class="matching-option rounded-lg border-slate-300"
                            data-question-id="{{ $question->id }}"
                            data-left-id="{{ $option->id }}"
                            @change="saveMatching({{ $question->id }})">
                        <option value="">Choose a match</option>
                        @foreach($rightOptions as $match)
                        <option value="{{ $match->id }}"
                                @selected((int) ($savedOptions[$option->id] ?? 0) === $match->id)>
                            {{ $match->option_text }}
                        </option>
                        @endforeach
                    </select>
                </label>
                @endforeach
            </div>
        @elseif(in_array($question->type->value, ['sequencing', 'drag_drop'], true))
            @php
                $sequenceOptions = $question->options->shuffle()->values();
                if ($savedOptionIds !== []) {
                    $byId = $question->options->keyBy('id');
                    $savedSequence = collect($savedOptionIds)->map(fn ($id) => $byId->get($id))->filter();
                    $sequenceOptions = $savedSequence->concat(
                        $sequenceOptions->reject(fn ($option) => in_array((int) $option->id, $savedOptionIds, true))
                    )->values();
                }
            @endphp
            <div x-data="{
                order: @js($sequenceOptions->pluck('id')->values()),
                labels: @js($sequenceOptions->mapWithKeys(fn ($option) => [$option->id => $option->option_text])),
                move(index, direction, dispatch) {
                    const target = index + direction;
                    if (target < 0 || target >= this.order.length) return;
                    [this.order[index], this.order[target]] = [this.order[target], this.order[index]];
                    dispatch('sequence-changed', { questionId: {{ $question->id }}, order: [...this.order] });
                }
            }" class="space-y-2">
                <p class="text-sm text-slate-500">Arrange the items in the correct order.</p>
                <template x-for="(optionId, optionIndex) in order" :key="optionId">
                    <div class="flex items-center gap-3 rounded-lg border p-3">
                        <span class="w-6 text-sm font-semibold text-slate-500" x-text="optionIndex + 1"></span>
                        <span class="flex-1" x-text="labels[optionId]"></span>
                        <button type="button" class="rounded bg-slate-100 px-3 py-2 text-sm disabled:opacity-40"
                                :disabled="optionIndex === 0" @click="move(optionIndex, -1, $dispatch)">Move up</button>
                        <button type="button" class="rounded bg-slate-100 px-3 py-2 text-sm disabled:opacity-40"
                                :disabled="optionIndex === order.length - 1" @click="move(optionIndex, 1, $dispatch)">Move down</button>
                    </div>
                </template>
            </div>
        @endif
    </div>
    @endforeach

    <div class="flex gap-3 pt-2">
        <button @click="prev()" x-show="current > 0" class="flex-1 py-3 bg-slate-200 rounded-xl font-medium min-h-[48px]">Previous</button>
        <button @click="next()" x-show="current < {{ $questions->count() - 1 }}" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl font-medium min-h-[48px]">Next</button>
        <form id="quiz-submit-form" x-show="current === {{ $questions->count() - 1 }}" method="POST" action="{{ route('student.quizzes.submit', [$quiz, $attempt]) }}" class="flex-1" @submit="handleSubmit">
            @csrf
            <button type="submit" class="w-full py-3 bg-green-600 text-white rounded-xl font-medium min-h-[48px]">Submit Quiz</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
function quizTaker(attemptId, total, seconds) {
    const syncUuid = @json($attempt->sync_uuid);
    const quizId = {{ $quiz->id }};
    const attemptNumber = {{ $attempt->attempt_number }};
    const startedAt = @json($attempt->started_at?->toIso8601String());
    const questionIds = @json($questions->pluck('id'));

    return {
        current: 0,
        remaining: Math.max(0, seconds - Math.floor((Date.now() - new Date(startedAt).getTime()) / 1000)),
        timerDisplay: '',
        progress: 0,
        autoSubmitting: false,
        init() {
            this.tick();
            setInterval(() => this.tick(), 1000);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) this.logIntegrity('tab_hidden');
            });
        },
        tick() {
            if (this.remaining <= 0) {
                this.submitOnTimeout();
                return;
            }
            this.remaining--;
            const m = Math.floor(this.remaining / 60);
            const s = this.remaining % 60;
            this.timerDisplay = `${m}:${String(s).padStart(2,'0')}`;
            this.progress = ((this.current + 1) / total) * 100;
            if (this.remaining === 0) this.submitOnTimeout();
        },
        submitOnTimeout() {
            if (this.autoSubmitting) return;
            this.autoSubmitting = true;
            document.getElementById('quiz-submit-form')?.requestSubmit();
        },
        next() { if (this.current < total - 1) this.current++; this.progress = ((this.current + 1) / total) * 100; },
        prev() { if (this.current > 0) this.current--; this.progress = ((this.current + 1) / total) * 100; },
        async saveAnswer(qId, text, options) {
            const payload = { question_id: qId, answer_text: text, selected_options: options };
            if (navigator.onLine) {
                try {
                    await fetch(`{{ route('student.quizzes.save-answer', [$quiz, $attempt]) }}`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, Accept: 'application/json' },
                        body: JSON.stringify(payload)
                    });
                } catch (e) { /* fall through to local save */ }
            }
            if (window.EduSyncOffline) window.EduSyncOffline.saveQuizAnswer({{ $attempt->id }}, qId, { answer_text: text, selected_options: options });
        },
        saveMulti(qId) {
            const checked = [...document.querySelectorAll(`input.option-check[data-qid="${qId}"]:checked`)].map(el => parseInt(el.value));
            this.saveAnswer(qId, null, checked);
        },
        saveMatching(qId) {
            const matches = Object.fromEntries(
                [...document.querySelectorAll(`select.matching-option[data-question-id="${qId}"]`)]
                    .filter((select) => select.value !== '')
                    .map((select) => [select.dataset.leftId, select.value])
            );
            this.saveAnswer(qId, null, matches);
        },
        async handleSubmit(e) {
            if (navigator.onLine) return;
            e.preventDefault();
            if (!window.EduSyncOffline) return alert('Offline submit unavailable.');
            const saved = await window.EduSyncOffline.getQuizAnswers(attemptId);
            const answers = questionIds.map((qId) => {
                const match = saved.find((a) => a.questionId === qId);
                return {
                    questionId: qId,
                    answer_text: match?.answer_text ?? null,
                    selected_options: match?.selected_options ?? null,
                };
            });
            await window.EduSyncOffline.submitStartedQuizOffline(syncUuid, quizId, attemptNumber, startedAt, answers);
            alert('Quiz submitted offline. It will sync when you reconnect.');
            window.location.href = '{{ route('student.sync.index') }}';
        },
        logIntegrity(event) { /* logged client-side; server receives on submit */ }
    };
}
</script>
@endpush
