@extends('layouts.admin')

@section('header', 'Edit User')

@section('content')
<form method="POST" action="{{ route('admin.users.update', $user) }}" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl space-y-4">
    @csrf @method('PUT')
    @include('admin.users._form')
    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg">Update User</button>
</form>
@endsection
