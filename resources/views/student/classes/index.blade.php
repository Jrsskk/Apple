@extends('layouts.student')

@section('header', 'My Classes')

@section('content')

@php
    $subjects = $classes->pluck('subject')->filter()->unique('id');
@endphp

<div class="mx-auto max-w-5xl">

    {{-- Page Header --}}
    <div class="mb-6">
        <div class="flex items-center gap-3">

            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M17 20h5v-2a4 4 0 0 0-4-4h-1m-3 6H5v-2a4 4 0 0 1 4-4h3a4 4 0 0 1 4 4v2Zm-3-10a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm7 1a3 3 0 1 0-6 0 3 3 0 0 0 6 0Z"
                    />
                </svg>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900">
                    My Classes
                </h2>

                <p class="mt-1 text-sm text-slate-500">
                    View your enrolled classes and subjects.
                </p>
            </div>

        </div>
    </div>


    {{-- Join Class --}}
    <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">

        <div class="border-b border-slate-100 p-4 sm:p-5">

            <div class="flex items-center gap-3">

                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">

                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M12 5v14M5 12h14"
                        />
                    </svg>

                </div>

                <div>
                    <h3 class="font-bold text-slate-900">
                        Join a Class
                    </h3>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Enter the class code provided by your teacher.
                    </p>
                </div>

            </div>

        </div>


        <div class="p-4 sm:p-5">

            <form
                method="POST"
                action="{{ route('student.classes.join') }}"
                class="flex flex-col gap-2 sm:flex-row"
            >

                @csrf

                <div class="relative flex-1">

                    <svg
                        class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M15 7a3 3 0 1 0-6 0v2h6V7Zm-8 2h10a2 2 0 0 1 2 2v7H5v-7a2 2 0 0 1 2-2Z"
                        />
                    </svg>

                    <input
                        type="text"
                        name="class_code"
                        value="{{ old('class_code') }}"
                        placeholder="Enter class code"
                        maxlength="20"
                        autocapitalize="characters"
                        autocomplete="off"
                        class="w-full rounded-xl border-slate-200 bg-slate-50 py-3 pl-10 pr-4 text-sm uppercase tracking-wider placeholder:normal-case placeholder:tracking-normal focus:border-indigo-500 focus:bg-white focus:ring-indigo-500"
                        required
                    >

                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-indigo-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:scale-[0.98] sm:w-auto"
                >
                    Join Class
                </button>

            </form>

        </div>

    </section>


    {{-- Subjects --}}
    @if($subjects->count())

        <section class="mb-6">

            <div class="mb-3 flex items-center justify-between">

                <div>
                    <h3 class="font-bold text-slate-900">
                        My Subjects
                    </h3>

                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $subjects->count() }}
                        {{ \Illuminate\Support\Str::plural('subject', $subjects->count()) }}
                    </p>
                </div>

            </div>


            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">

                @foreach($subjects as $subject)

                    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md">

                        <div class="flex items-center gap-3">

                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">

                                <svg
                                    class="h-5 w-5"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="1.8"
                                        d="M4 5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm9-2v6h5"
                                    />
                                </svg>

                            </div>

                            <p class="truncate text-sm font-semibold text-slate-800">
                                {{ $subject->name }}
                            </p>

                        </div>

                    </div>

                @endforeach

            </div>

        </section>

    @endif


    {{-- Enrolled Classes --}}
    <section>

        <div class="mb-3">

            <h3 class="font-bold text-slate-900">
                Enrolled Classes
            </h3>

            <p class="mt-0.5 text-xs text-slate-500">
                Select a class to view its activities and materials.
            </p>

        </div>


        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

            @forelse($classes as $class)

                <a
                    href="{{ route('student.classes.show', $class) }}"
                    class="group rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-md active:scale-[0.99]"
                >

                    {{-- Top --}}
                    <div class="flex items-start justify-between gap-3">

                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">

                            <svg
                                class="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M4 6.5A2.5 2.5 0 0 1 6.5 4h11A2.5 2.5 0 0 1 20 6.5v11a2.5 2.5 0 0 1-2.5 2.5h-11A2.5 2.5 0 0 1 4 17.5v-11Z"
                                />
                            </svg>

                        </div>


                        <svg
                            class="h-5 w-5 text-slate-300 transition group-hover:text-indigo-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="m9 5 7 7-7 7"
                            />
                        </svg>

                    </div>


                    {{-- Class Information --}}
                    <div class="mt-4">

                        <h4 class="truncate text-base font-bold text-slate-900 group-hover:text-indigo-700">
                            {{ $class->display_name }}
                        </h4>

                        <p class="mt-1 truncate text-sm text-slate-500">
                            {{ $class->subject?->name ?? 'No subject assigned' }}
                        </p>

                    </div>


                    {{-- Teacher --}}
                    <div class="mt-4 flex items-center gap-2 border-t border-slate-100 pt-3">

                        <div class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-slate-500">

                            <svg
                                class="h-4 w-4"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M15 19a6 6 0 0 0-12 0m6-8a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm5-2v6m3-3h-6"
                                />
                            </svg>

                        </div>

                        <div class="min-w-0">

                            <p class="text-[10px] uppercase tracking-wide text-slate-400">
                                Teacher
                            </p>

                            <p class="truncate text-xs font-medium text-slate-700">
                                {{ $class->teacher?->full_name ?? 'Not assigned' }}
                            </p>

                        </div>

                    </div>

                </a>

            @empty

                {{-- Empty State --}}
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center sm:col-span-2 lg:col-span-3">

                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-500">

                        <svg
                            class="h-8 w-8"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M17 20h5v-2a4 4 0 0 0-4-4h-1m-3 6H5v-2a4 4 0 0 1 4-4h3a4 4 0 0 1 4 4v2Zm-3-10a4 4 0 1 0-8 0 4 4 0 0 0 8 0Zm7 1a3 3 0 1 0-6 0 3 3 0 0 0 6 0Z"
                            />
                        </svg>

                    </div>

                    <h3 class="mt-4 font-bold text-slate-800">
                        No classes yet
                    </h3>

                    <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500">
                        You are not enrolled in any classes.
                        Enter a class code above to join your teacher's class.
                    </p>

                </div>

            @endforelse

        </div>

    </section>

</div>

@endsection