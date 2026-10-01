@extends('layouts.student')

@section('header', $assignment->title)

@section('content')
@php
    $submissionStatus = $submission->status instanceof \BackedEnum ? $submission->status->value : (string) ($submission->status ?? '');
@endphp
<div class="bg-white rounded-xl p-4 shadow-sm border mb-4 text-sm">
    @if($assignment->description)<p class="text-slate-600">{{ $assignment->description }}</p>@endif
    @if($assignment->instructions)<p class="text-slate-600 mt-3 whitespace-pre-line">{{ $assignment->instructions }}</p>@endif
    <p class="text-xs text-slate-400 mt-3">Due {{ $assignment->deadline?->format('M d, Y g:i A') ?? 'No deadline' }} · Max score: {{ $assignment->max_score }}</p>
    <p class="text-xs mt-1">Status: <span class="font-medium">{{ $submissionStatus }}</span></p>
    <p id="download-status" class="text-xs text-emerald-600 mt-2 hidden">✓ Available offline</p>
    @if($assignment->attachment_path)
    <a href="{{ route('student.assignments.attachment', $assignment) }}" class="inline-block mt-2 text-sm text-indigo-600">
        Download {{ $assignment->attachment_file_name ?: 'assignment attachment' }}
        @if($assignment->attachment_file_size) · {{ number_format($assignment->attachment_file_size / 1048576, 2) }} MB @endif
    </a>
    @endif
</div>

@if(!in_array($submissionStatus, ['submitted', 'late', 'graded', 'returned'], true))
<form id="assignment-form" method="POST" action="{{ route('student.assignments.submit', $assignment) }}" enctype="multipart/form-data" class="space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium mb-1">Your Response</label>
        <textarea id="text-response" name="text_response" rows="6" class="w-full rounded-xl border-slate-300 text-base">{{ old('text_response', $submission->text_response) }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Attach File</label>
        <input type="file" name="file" id="file-input" class="w-full text-sm" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
        <p id="selected-file-info" class="mt-1 text-xs text-slate-500"></p>
        @if($submission->file_path)
        <div class="mt-2 flex items-center gap-3 text-sm">
            <a href="{{ route('student.assignments.submission-file', $assignment) }}" class="text-indigo-600">
                {{ $submission->file_name ?: 'Download current file' }}
                @if($submission->file_size) · {{ number_format($submission->file_size / 1048576, 2) }} MB @endif
            </a>
            <button type="button" id="delete-submission-file" class="text-red-600">Delete file</button>
        </div>
        @endif
        @error('file')<p role="alert" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        <p id="submission-upload-status" class="mt-1 text-xs text-slate-500" role="status" aria-live="polite"></p>
    </div>
    <div class="grid grid-cols-2 gap-3">
        <button type="button" id="save-draft-btn" class="py-3 bg-slate-100 text-slate-700 rounded-xl font-medium">Save Draft</button>
        <button type="submit" id="submit-btn" class="py-3 bg-indigo-600 text-white rounded-xl font-medium">Submit</button>
    </div>
</form>
<button type="button" id="download-assignment" class="w-full mt-3 py-3 bg-slate-100 text-slate-700 rounded-xl text-sm font-medium">Download for Offline</button>
@else
<div class="bg-green-50 border border-green-200 rounded-xl p-4 text-sm text-green-800">
    Submitted on {{ $submission->submitted_at?->format('M d, Y g:i A') }}
    @if($submission->score !== null)
    <p class="mt-2 font-semibold">Score: {{ $submission->score }}/{{ $assignment->max_score }}</p>
    @if($submission->feedback)<p class="mt-2 bg-white/60 rounded-lg p-3">{{ $submission->feedback }}</p>@endif
    @endif
</div>
@endif
@endsection

@push('scripts')
<script>
(async function() {
    const assignmentId = {{ $assignment->id }};
    const syncUuid = @json($submission->sync_uuid);
    const statusEl = document.getElementById('download-status');
    const textEl = document.getElementById('text-response');
    const fileEl = document.getElementById('file-input');
    const fileInfoEl = document.getElementById('selected-file-info');
    const uploadStatusEl = document.getElementById('submission-upload-status');

    fileEl?.addEventListener('change', () => {
        const file = fileEl.files?.[0];
        if (fileInfoEl) {
            fileInfoEl.textContent = file ? `${file.name} · ${(file.size / 1048576).toFixed(2)} MB` : '';
        }
    });

    if (window.EduSyncOffline) {
        if (await window.EduSyncOffline.isAssignmentDownloaded(assignmentId)) {
            statusEl?.classList.remove('hidden');
        }
        const draft = await window.EduSyncOffline.getAssignmentDraft(assignmentId);
        if (draft?.text_response && textEl && !textEl.value) {
            textEl.value = draft.text_response;
        }
    }

    document.getElementById('save-draft-btn')?.addEventListener('click', async () => {
        const text = textEl?.value || '';
        if (navigator.onLine) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('student.assignments.draft', $assignment) }}';
            form.innerHTML = `@csrf<input name="text_response" value="${text.replace(/"/g,'&quot;')}">`;
            document.body.appendChild(form);
            form.submit();
        } else if (window.EduSyncOffline) {
            await window.EduSyncOffline.saveAssignmentDraft(assignmentId, { text_response: text });
            alert('Draft saved locally.');
        }
    });

    document.getElementById('delete-submission-file')?.addEventListener('click', async () => {
        if (!confirm('Delete your uploaded submission file?')) return;
        const response = await fetch('{{ route('student.assignments.submission-file.destroy', $assignment) }}', {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        });
        if (response.ok) window.location.reload();
        else alert('The file could not be deleted. Please try again.');
    });

    document.getElementById('assignment-form')?.addEventListener('submit', async (e) => {
        if (navigator.onLine) {
            if (uploadStatusEl) uploadStatusEl.textContent = 'Uploading submission…';
            return;
        }
        e.preventDefault();
        if (!window.EduSyncOffline) return alert('Offline submit unavailable.');
        await window.EduSyncOffline.submitAssignmentOffline(assignmentId, {
            sync_uuid: syncUuid,
            text_response: textEl?.value,
            status: 'submitted',
        });
        alert('Submitted offline. Will sync when online.');
        window.location.href = '/student/sync';
    });

    document.getElementById('download-assignment')?.addEventListener('click', async () => {
        if (!window.EduSyncOffline) return;
        const json = await window.EduSyncOffline.downloadAssignment(assignmentId);
        if (json.success) {
            statusEl?.classList.remove('hidden');
            alert('Assignment saved for offline.');
        }
    });
})();
</script>
@endpush
