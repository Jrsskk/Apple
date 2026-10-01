@extends('layouts.student')

@section('header', 'My Profile')

@section('content')
<div class="bg-white rounded-xl p-4 shadow-sm border mb-4 text-center">
    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto text-2xl font-bold text-indigo-600">
        {{ strtoupper(substr($user->first_name, 0, 1)) }}{{ strtoupper(substr($user->last_name, 0, 1)) }}
    </div>
    <p class="font-semibold mt-3">{{ $user->full_name }}</p>
    <p class="text-sm text-slate-500">{{ $user->email }}</p>
    @if($user->employee_number)<p class="text-xs text-slate-400 mt-1">ID: {{ $user->employee_number }}</p>@endif
</div>

<form method="POST" action="{{ route('student.profile.update') }}" class="bg-white rounded-xl p-4 shadow-sm border mb-4 space-y-3">
    @csrf @method('PATCH')
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label class="text-xs text-slate-500">First name</label>
            <input name="first_name" value="{{ old('first_name', $user->first_name) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
        </div>
        <div>
            <label class="text-xs text-slate-500">Last name</label>
            <input name="last_name" value="{{ old('last_name', $user->last_name) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
        </div>
    </div>
    <div>
        <label class="text-xs text-slate-500">Email</label>
        <input type="email" name="email" value="{{ old('email', $user->email) }}" class="w-full rounded-lg border-slate-300 text-sm" required>
    </div>
    <button type="submit" class="w-full py-3 bg-indigo-600 text-white rounded-xl font-medium">Save Profile</button>
</form>

<form method="POST" action="{{ route('student.profile.password') }}" class="bg-white rounded-xl p-4 shadow-sm border space-y-3">
    @csrf @method('PUT')
    <h3 class="font-semibold text-sm">Change Password</h3>
    <input type="password" name="current_password" placeholder="Current password" class="w-full rounded-lg border-slate-300 text-sm" required>
    <input type="password" name="password" placeholder="New password" class="w-full rounded-lg border-slate-300 text-sm" required>
    <input type="password" name="password_confirmation" placeholder="Confirm password" class="w-full rounded-lg border-slate-300 text-sm" required>
    <button type="submit" class="w-full py-3 bg-slate-700 text-white rounded-xl font-medium">Update Password</button>
</form>
@endsection
