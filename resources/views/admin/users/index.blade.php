@extends('layouts.admin')

@section('header', 'User Management')

@section('content')
<div class="flex flex-col sm:flex-row justify-between gap-3 mb-4">
    <form method="GET" class="flex flex-wrap gap-2">
        <input type="search" name="search" value="{{ request('search') }}" placeholder="Search users..." class="rounded-lg border-slate-300 text-sm">
        <select name="role" class="rounded-lg border-slate-300 text-sm">
            <option value="">All roles</option>
            @foreach(['admin','teacher','student'] as $r)
            <option value="{{ $r }}" @selected(request('role')===$r)>{{ ucfirst($r) }}</option>
            @endforeach
        </select>
        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Filter</button>
    </form>
    <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm text-center">Add User</a>
</div>

<div class="bg-white rounded-xl shadow-sm border overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left">
                <tr>
                    <th class="p-3">Name</th>
                    <th class="p-3 hidden md:table-cell">Email</th>
                    <th class="p-3">Role</th>
                    <th class="p-3 hidden sm:table-cell">Status</th>
                    <th class="p-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr class="border-t">
                    <td class="p-3">
                        <p class="font-medium">{{ $user->full_name }}</p>
                        <p class="text-xs text-slate-500 md:hidden">{{ $user->email }}</p>
                    </td>
                    <td class="p-3 hidden md:table-cell">{{ $user->email }}</td>
                    <td class="p-3"><span class="px-2 py-0.5 rounded bg-slate-100 text-xs">{{ $user->role->value }}</span></td>
                    <td class="p-3 hidden sm:table-cell">{{ $user->status->value }}</td>
                    <td class="p-3">
                        <div class="flex gap-2">
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600">Edit</a>
                            <form method="POST" action="{{ route('admin.users.reset-password', $user) }}">@csrf<button class="text-orange-600">Reset</button></form>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="p-3">{{ $users->links() }}</div>
</div>
@endsection
