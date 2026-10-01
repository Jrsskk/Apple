@extends('layouts.student')

@section('header', $class->display_name)

@section('content')
<div class="bg-white rounded-xl p-4 shadow-sm border mb-4 text-sm">
    <p><span class="text-slate-500">Subject:</span> {{ $class->subject?->name }}</p>
    <p class="mt-1"><span class="text-slate-500">Teacher:</span> {{ $class->teacher?->full_name }}</p>
    @if($class->schedule)<p class="mt-1"><span class="text-slate-500">Schedule:</span> {{ $class->schedule }}</p>@endif
</div>

<section class="mb-6">
    <h3 class="font-semibold text-slate-700 mb-2">Quizzes</h3>
    @forelse($class->quizzes->filter(fn ($q) => $q->isPublished()) as $quiz)
    <a href="{{ route('student.quizzes.show', $quiz) }}" class="block bg-white rounded-xl p-3 shadow-sm border mb-2 text-sm">
        <p class="font-medium">{{ $quiz->title }}</p>
        <p class="text-slate-500 text-xs mt-1">Due {{ $quiz->deadline?->format('M d, Y') ?? 'No deadline' }}</p>
    </a>
    @empty
    <p class="text-sm text-slate-500">No quizzes.</p>
    @endforelse
</section>

<section class="mb-6">
    <h3 class="font-semibold text-slate-700 mb-2">Assignments</h3>
    @forelse($class->assignments->filter(fn ($a) => $a->status?->value === 'published') as $assignment)
    <a href="{{ route('student.assignments.show', $assignment) }}" class="block bg-white rounded-xl p-3 shadow-sm border mb-2 text-sm">
        <p class="font-medium">{{ $assignment->title }}</p>
        <p class="text-slate-500 text-xs mt-1">Due {{ $assignment->deadline?->format('M d, Y') ?? 'No deadline' }}</p>
    </a>
    @empty
    <p class="text-sm text-slate-500">No assignments.</p>
    @endforelse
</section>

@if($class->materials->count())
<section>
    <h3 class="font-semibold text-slate-700 mb-2">Materials</h3>
    @foreach($class->materials as $material)
    <div class="bg-white rounded-xl p-3 shadow-sm border mb-2 text-sm flex justify-between items-center">
        <div>
            <p class="font-medium">{{ $material->title }}</p>
            <p class="text-xs text-slate-500">{{ $material->file_type }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('student.materials.file', $material) }}" class="text-indigo-600 text-xs">Open</a>
            <button type="button" class="download-material text-emerald-600 text-xs" data-id="{{ $material->id }}">Save</button>
        </div>
    </div>
    @endforeach
</section>
@endif
@endsection

@push('scripts')
<script>
document.querySelectorAll('.download-material').forEach(btn => {
    btn.addEventListener('click', async () => {
        if (!window.EduSyncOffline) return;
        const json = await window.EduSyncOffline.downloadMaterial(btn.dataset.id);
        alert(json.success ? 'Material saved offline.' : 'Download failed.');
    });
});
</script>
@endpush
