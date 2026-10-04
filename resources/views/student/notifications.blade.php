@extends('layouts.student')

@section('header', 'Notifications')

@section('content')
<div class="mb-4 flex items-center justify-between gap-3">
    <p class="text-sm text-slate-500">Your latest updates and alerts.</p>
    @if(auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('student.notifications.read-all') }}">
            @csrf
            <button class="rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Mark All as Read
            </button>
        </form>
    @endif
</div>
<div class="space-y-2">
    @forelse($notifications as $notification)
    <div class="rounded-xl border bg-white p-4 shadow-sm {{ $notification->read_at ? 'border-slate-200 opacity-75' : 'border-indigo-200' }}">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="font-semibold text-slate-800">{{ $notification->data['title'] ?? 'Notification' }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ $notification->data['message'] ?? '' }}</p>
                <time class="mt-2 block text-xs text-slate-400" datetime="{{ $notification->created_at->toIso8601String() }}">
                    {{ $notification->created_at->format('M j, Y g:i A') }}
                </time>
            </div>
            <span class="shrink-0 rounded-full px-2 py-1 text-xs font-medium {{ $notification->read_at ? 'bg-slate-100 text-slate-500' : 'bg-indigo-50 text-indigo-700' }}">
                {{ $notification->read_at ? 'Read' : 'Unread' }}
            </span>
        </div>
        @unless($notification->read_at)
            <form class="mt-3" method="POST" action="{{ route('student.notifications.read', $notification->id) }}">
                @csrf
                <button class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">Mark as Read</button>
            </form>
        @endunless
    </div>
    @empty
    <p class="text-slate-500 text-sm">No notifications.</p>
    @endforelse
</div>
@if($notifications->hasPages())
<div class="mt-4">{{ $notifications->links() }}</div>
@endif
@endsection
