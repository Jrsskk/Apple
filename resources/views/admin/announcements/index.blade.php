@extends('layouts.admin')

@section('header', 'Announcements')

@section('content')
<div class="bg-white rounded-xl shadow-sm border p-6">
    <p class="text-slate-600">System-wide announcement management is available here. Use teacher portal announcements for class-specific posts.</p>
    <div class="mt-4 p-4 bg-slate-50 rounded-lg text-sm text-slate-500">
        Announcements are created via the API and seeder. Admin CRUD UI can be extended in a future release.
    </div>
    @php $announcements = \App\Models\Announcement::with('author')->latest()->take(10)->get(); @endphp
    <div class="mt-6 space-y-3">
        @forelse($announcements as $a)
        <div class="border rounded-lg p-4">
            <p class="font-medium">{{ $a->title }}</p>
            <p class="text-sm text-slate-600 mt-1">{{ Str::limit($a->message, 200) }}</p>
            <p class="text-xs text-slate-400 mt-2">{{ $a->published_at?->format('M d, Y') ?? 'Draft' }} · {{ $a->author?->full_name }}</p>
        </div>
        @empty
        <p class="text-slate-500">No announcements.</p>
        @endforelse
    </div>
</div>
@endsection
