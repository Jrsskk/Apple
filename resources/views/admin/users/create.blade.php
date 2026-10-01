@extends('layouts.admin')

@section('header', 'Create User')

@section('content')
<form method="POST" action="{{ route('admin.users.store') }}" class="bg-white rounded-xl shadow-sm border p-6 max-w-2xl space-y-4">
    @csrf
    @include('admin.users._form')
    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg">Create User</button>
</form>
@endsection
