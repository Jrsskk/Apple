@extends('layouts.admin')

@section('header', 'Audit Logs')

@section('content')
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Time</th>
                    <th class="p-3">User</th>
                    <th class="p-3">Action</th>
                    <th class="p-3 hidden md:table-cell">Module</th>
                    <th class="p-3 hidden lg:table-cell">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr class="border-t">
                    <td class="p-3 whitespace-nowrap">{{ $log->created_at->format('M d, H:i') }}</td>
                    <td class="p-3">{{ $log->user?->full_name ?? 'System' }}</td>
                    <td class="p-3"><span class="px-2 py-0.5 bg-slate-100 rounded text-xs">{{ $log->action }}</span></td>
                    <td class="p-3 hidden md:table-cell">{{ $log->module }}</td>
                    <td class="p-3 hidden lg:table-cell text-slate-600">{{ Str::limit($log->description, 80) }}</td>
                </tr>
                @empty
                <tr><td colspan="5" class="p-6 text-center text-slate-500">No audit logs.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $logs->links() }}</div>
</div>
@endsection
