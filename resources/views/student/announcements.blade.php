@extends('layouts.student')

@section('header', 'Announcements')

@section('content')
<div class="space-y-3">
    @forelse($announcements as $a)
    <div class="bg-white rounded-xl p-4 shadow-sm border">
        <p class="font-semibold text-slate-800">{{ $a->title }}</p>
        <p class="text-xs text-slate-400 mt-1">{{ $a->published_at?->format('M d, Y g:i A') }}</p>
        <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ $a->message }}</p>
    </div>
    @empty
    <p class="text-sm text-slate-500 text-center py-8">No announcements.</p>
    @endforelse
</div>
@if($announcements->hasPages())
<div class="mt-4">{{ $announcements->links() }}</div>
@endif
@endsection
