@extends('layouts.admin')

@section('header', 'Backups')

@section('content')
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="p-3">Date</th>
                <th class="p-3">Type</th>
                <th class="p-3">Status</th>
                <th class="p-3 hidden md:table-cell">File</th>
            </tr>
        </thead>
        <tbody>
            @forelse($backups as $backup)
            <tr class="border-t">
                <td class="p-3">{{ $backup->created_at->format('M d, Y H:i') }}</td>
                <td class="p-3">{{ $backup->type ?? 'database' }}</td>
                <td class="p-3">{{ $backup->status }}</td>
                <td class="p-3 hidden md:table-cell text-slate-500">{{ $backup->filename }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="p-6 text-center text-slate-500">No backup records.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="p-3">{{ $backups->links() }}</div>
</div>
@endsection
