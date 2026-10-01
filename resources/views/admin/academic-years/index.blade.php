@extends('layouts.admin')

@section('header', 'Academic Years')

@section('content')
<div class="flex justify-between mb-4">
    <p class="text-sm text-slate-500">{{ $years->total() }} year(s)</p>
    <a href="{{ route('admin.academic-years.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Add Year</a>
</div>
<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left">
            <tr>
                <th class="p-3">Name</th>
                <th class="p-3">Period</th>
                <th class="p-3">Status</th>
                <th class="p-3">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($years as $year)
            <tr class="border-t">
                <td class="p-3 font-medium">{{ $year->name }} @if($year->is_active)<span class="text-xs text-green-600">(Active)</span>@endif</td>
                <td class="p-3">{{ $year->start_date->format('M Y') }} – {{ $year->end_date->format('M Y') }}</td>
                <td class="p-3">{{ ucfirst($year->status) }}</td>
                <td class="p-3 flex flex-wrap gap-2">
                    <a href="{{ route('admin.academic-years.edit', $year) }}" class="text-indigo-600">Edit</a>
                    @unless($year->is_active)
                    <form method="POST" action="{{ route('admin.academic-years.activate', $year) }}">@csrf<button class="text-green-600">Activate</button></form>
                    @endunless
                    @if($year->is_active)
                    <form method="POST" action="{{ route('admin.academic-years.close', $year) }}">@csrf<button class="text-orange-600">Close</button></form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="p-3">{{ $years->links() }}</div>
</div>
@endsection
