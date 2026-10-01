@extends('layouts.teacher')

@section('header', 'Learning Materials')

@section('content')
@php $materials = \App\Models\LearningMaterial::whereIn('school_class_id', auth()->user()->taughtClasses()->pluck('id'))->with('schoolClass')->latest()->get(); @endphp
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr><th class="p-3">Title</th><th class="p-3">Class</th><th class="p-3">Type</th></tr>
            </thead>
            <tbody>
                @forelse($materials as $material)
                <tr class="border-t">
                    <td class="p-3 font-medium">{{ $material->title }}</td>
                    <td class="p-3">{{ $material->schoolClass?->display_name }}</td>
                    <td class="p-3">{{ $material->file_type ?? 'file' }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="p-6 text-center text-slate-500">No materials uploaded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
