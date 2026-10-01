@extends('layouts.teacher')

@section('header', 'Create Assignment')

@section('content')
<form method="POST" action="{{ route('teacher.assignments.store') }}" enctype="multipart/form-data" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl space-y-4">
    @csrf
    <div>
        <label class="block text-sm font-medium mb-1">Title</label>
        <input type="text" name="title" value="{{ old('title') }}" required class="w-full rounded-lg border-slate-300">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Class</label>
        <select name="school_class_id" required class="w-full rounded-lg border-slate-300" id="class-select">
            @foreach($classes as $class)
            <option value="{{ $class->id }}" data-subject="{{ $class->subject_id }}" @selected(old('school_class_id') == $class->id)>{{ $class->display_name }}</option>
            @endforeach
        </select>
        <input type="hidden" name="subject_id" id="subject-id" value="{{ old('subject_id', $classes->first()?->subject_id) }}">
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Description</label>
        <textarea name="description" rows="3" class="w-full rounded-lg border-slate-300">{{ old('description') }}</textarea>
    </div>
    <div>
        <label class="block text-sm font-medium mb-1">Instructions</label>
        <textarea name="instructions" rows="3" class="w-full rounded-lg border-slate-300">{{ old('instructions') }}</textarea>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-sm font-medium mb-1">Deadline</label>
            <input type="datetime-local" name="deadline" value="{{ old('deadline') }}" class="w-full rounded-lg border-slate-300">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Max Score</label>
            <input type="number" name="max_score" value="{{ old('max_score', 100) }}" min="1" required class="w-full rounded-lg border-slate-300">
        </div>
    </div>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="allow_resubmit" value="1" @checked(old('allow_resubmit'))> Allow resubmit</label>
    <div>
        <label class="block text-sm font-medium mb-1">Attachment</label>
        <input type="file" name="attachment" class="w-full text-sm">
    </div>
    <button type="submit" class="px-6 py-2 bg-emerald-600 text-white rounded-lg">Create Assignment</button>
</form>
@push('scripts')
<script>
document.getElementById('class-select')?.addEventListener('change', function() {
    document.getElementById('subject-id').value = this.selectedOptions[0]?.dataset.subject || '';
});
</script>
@endpush
@endsection
