@extends('layouts.teacher')

@section('header', 'Announcements')

@section('content')
@php $announcements = \App\Models\Announcement::where('created_by', auth()->id())->orWhereIn('school_class_id', auth()->user()->taughtClasses()->pluck('id'))->latest()->take(15)->get(); @endphp
<div class="space-y-3">
    @forelse($announcements as $a)
    <div class="bg-white rounded-xl shadow-sm border p-4">
        <p class="font-medium">{{ $a->title }}</p>
        <p class="text-sm text-slate-600 mt-1">{{ Str::limit($a->message, 200) }}</p>
        <p class="text-xs text-slate-400 mt-2">{{ $a->published_at?->format('M d, Y') ?? 'Unpublished' }}</p>
    </div>
    @empty
    <p class="text-slate-500">No announcements for your classes.</p>
    @endforelse
</div>
@endsection
