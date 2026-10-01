@extends('layouts.student')

@section('header', 'Notifications')

@section('content')
<div class="space-y-2">
    @forelse($notifications as $notification)
    <div class="bg-white rounded-xl p-4 shadow-sm border {{ $notification->read_at ? 'opacity-75' : '' }}">
        <p class="font-medium text-slate-800">{{ $notification->data['title'] ?? 'Notification' }}</p>
        <p class="text-sm text-slate-600 mt-1">{{ $notification->data['message'] ?? '' }}</p>
        <p class="text-xs text-slate-400 mt-2">{{ $notification->created_at->diffForHumans() }}</p>
    </div>
    @empty
    <p class="text-slate-500 text-sm">No notifications.</p>
    @endforelse
</div>
@if($notifications->hasPages())
<div class="mt-4">{{ $notifications->links() }}</div>
@endif
@endsection
