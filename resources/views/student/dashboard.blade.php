@extends('layouts.student')

@section('header', 'Home')

@section('content')

<div class="mx-auto max-w-5xl">

    {{-- Welcome --}}
    <div class="mb-6">
        <p class="text-sm font-medium text-indigo-600">
            {{ now()->hour < 12 ? 'Good morning' : (now()->hour < 17 ? 'Good afternoon' : 'Good evening') }}
        </p>

        <h2 class="mt-1 text-2xl font-bold tracking-tight text-slate-900">
            {{ auth()->user()->first_name }} 👋
        </h2>

        <p class="mt-1 text-sm text-slate-500">
            Here's what's happening with your learning today.
        </p>
    </div>


    {{-- Summary Cards --}}
    <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">

        {{-- Pending --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">

                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M12 8v4l2.5 2.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
                        />
                    </svg>
                </div>

            </div>

            <p class="mt-3 text-2xl font-bold text-slate-900">
                {{ $pending_count }}
            </p>

            <p class="text-xs text-slate-500">
                Pending activities
            </p>
        </div>


        {{-- Quizzes --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M9 5h6M7 9h10M7 13h10M7 17h6"
                    />
                </svg>
            </div>

            <p class="mt-3 text-2xl font-bold text-slate-900">
                {{ $upcoming_quizzes->count() }}
            </p>

            <p class="text-xs text-slate-500">
                Upcoming quizzes
            </p>
        </div>


        {{-- Assignments --}}
        <div class="col-span-2 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:col-span-1">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-violet-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M6 4h12a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm3 4h6m-6 4h6m-6 4h3"
                    />
                </svg>
            </div>

            <p class="mt-3 text-2xl font-bold text-slate-900">
                {{ $upcoming_assignments->count() }}
            </p>

            <p class="text-xs text-slate-500">
                Upcoming assignments
            </p>
        </div>

    </div>


    {{-- Sync Notice --}}
    @if($sync_pending > 0)

        <a
            href="{{ route('student.sync.index') }}"
            class="mb-6 flex items-center justify-between gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 transition hover:bg-amber-100"
        >

            <div class="flex min-w-0 items-center gap-3">

                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="1.8"
                            d="M20 12a8 8 0 1 1-2.3-5.7M20 4v6h-6"
                        />
                    </svg>
                </div>

                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-900">
                        {{ $sync_pending }}
                        {{ $sync_pending > 1 ? 'activities' : 'activity' }}
                        waiting to sync
                    </p>

                    <p class="mt-0.5 text-xs text-amber-700">
                        Your offline work needs to be synchronized.
                    </p>
                </div>

            </div>

            <span class="shrink-0 text-xs font-bold text-indigo-600">
                Details →
            </span>

        </a>

    @endif


    {{-- Join Class --}}
    <section class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">

        <div class="mb-4 flex items-center gap-3">

            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="1.8"
                        d="M12 4v16m8-8H4"
                    />
                </svg>
            </div>

            <div class="min-w-0 flex-1">
                <h3 class="font-bold text-slate-900">
                    Join a Class
                </h3>

                <p class="text-xs text-slate-500">
                    Enter the class code provided by your teacher.
                </p>
            </div>

            <a
                href="{{ route('student.classes.index') }}"
                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
            >
                My classes
            </a>

        </div>


        <form
            method="POST"
            action="{{ route('student.classes.join') }}"
            class="flex flex-col gap-2 sm:flex-row"
        >

            @csrf

            <input
                type="text"
                name="class_code"
                value="{{ old('class_code') }}"
                placeholder="Enter class code"
                maxlength="20"
                autocapitalize="characters"
                autocomplete="off"
                class="w-full min-w-0 rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm uppercase tracking-wider placeholder:normal-case placeholder:tracking-normal focus:border-indigo-500 focus:bg-white focus:ring-indigo-500 sm:flex-1"
                required
            >

            <button
                type="submit"
                class="w-full rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:scale-[0.98] sm:w-auto"
            >
                Join Class
            </button>

        </form>

    </section>


    {{-- Two Column Content --}}
    <div class="grid gap-6 lg:grid-cols-2">


        {{-- Upcoming Quizzes --}}
        <section>

            <div class="mb-3 flex items-center justify-between">
                <div>
                    <h3 class="font-bold text-slate-900">
                        Upcoming Quizzes
                    </h3>

                    <p class="mt-0.5 text-xs text-slate-500">
                        Your upcoming assessments
                    </p>
                </div>
            </div>


            <div class="space-y-3">

                @forelse($upcoming_quizzes as $quiz)

                    <a
                        href="{{ route('student.quizzes.show', $quiz) }}"
                        class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md active:scale-[0.99]"
                    >

                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600">

                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M9 5h6M7 9h10M7 13h7M7 17h5"
                                />
                            </svg>

                        </div>

                        <div class="min-w-0 flex-1">

                            <p class="truncate text-sm font-semibold text-slate-800">
                                {{ $quiz->title }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Due
                                {{ $quiz->deadline?->format('M d, Y g:i A') ?? 'No deadline' }}
                            </p>

                        </div>

                        <svg
                            class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:text-indigo-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            />
                        </svg>

                    </a>

                @empty

                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center">

                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                            ✓
                        </div>

                        <p class="mt-3 text-sm font-semibold text-slate-700">
                            No upcoming quizzes
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            You're all caught up.
                        </p>

                    </div>

                @endforelse

            </div>

        </section>


        {{-- Upcoming Assignments --}}
        <section>

            <div class="mb-3">

                <h3 class="font-bold text-slate-900">
                    Upcoming Assignments
                </h3>

                <p class="mt-0.5 text-xs text-slate-500">
                    Tasks that need your attention
                </p>

            </div>


            <div class="space-y-3">

                @forelse($upcoming_assignments as $assignment)

                    <a
                        href="{{ route('student.assignments.show', $assignment) }}"
                        class="group flex items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-indigo-200 hover:shadow-md active:scale-[0.99]"
                    >

                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-600">

                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Zm3 5h4m-4 4h4m-4 4h2"
                                />
                            </svg>

                        </div>

                        <div class="min-w-0 flex-1">

                            <p class="truncate text-sm font-semibold text-slate-800">
                                {{ $assignment->title }}
                            </p>

                            <p class="mt-1 text-xs text-slate-500">
                                Due
                                {{ $assignment->deadline?->format('M d, Y g:i A') ?? 'No deadline' }}
                            </p>

                        </div>

                        <svg
                            class="h-5 w-5 shrink-0 text-slate-300 transition group-hover:text-indigo-600"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M9 5l7 7-7 7"
                            />
                        </svg>

                    </a>

                @empty

                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center">

                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-slate-100 text-slate-400">
                            ✓
                        </div>

                        <p class="mt-3 text-sm font-semibold text-slate-700">
                            No upcoming assignments
                        </p>

                        <p class="mt-1 text-xs text-slate-500">
                            You're all caught up.
                        </p>

                    </div>

                @endforelse

            </div>

        </section>

    </div>


    {{-- Materials --}}
    <section class="mt-6">

        <div class="mb-3 flex items-center justify-between">

            <div>
                <h3 class="font-bold text-slate-900">
                    Learning Materials
                </h3>

                <p class="mt-0.5 text-xs text-slate-500">
                    Recently shared resources
                </p>
            </div>

            <a
                href="{{ route('student.materials.index') }}"
                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
            >
                View all →
            </a>

        </div>


        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">

            @forelse($materials as $material)

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md">

                    <div class="flex items-start gap-3">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M4 5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5Zm9-2v6h5"
                                />
                            </svg>
                        </div>

                        <div class="min-w-0 flex-1">

                            <p class="truncate text-sm font-semibold text-slate-800">
                                {{ $material->title }}
                            </p>

                            <p class="mt-1 truncate text-xs text-slate-500">
                                {{ $material->schoolClass?->display_name ?? 'Class material' }}
                            </p>

                            <div class="mt-3 flex items-center justify-between">

                                <span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-medium uppercase text-slate-500">
                                    {{ $material->file_type }}
                                </span>

                                <a
                                    href="{{ route('student.materials.file', $material) }}"
                                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
                                >
                                    Open →
                                </a>

                            </div>

                        </div>

                    </div>

                </div>

            @empty

                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center sm:col-span-2 lg:col-span-3">

                    <p class="text-sm font-semibold text-slate-700">
                        No recent materials
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        Learning materials shared by your teachers will appear here.
                    </p>

                </div>

            @endforelse

        </div>

    </section>


    {{-- Announcements --}}
    <section class="mt-6">

        <div class="mb-3 flex items-center justify-between">

            <div>
                <h3 class="font-bold text-slate-900">
                    Announcements
                </h3>

                <p class="mt-0.5 text-xs text-slate-500">
                    Latest updates from your teachers
                </p>
            </div>

            <a
                href="{{ route('student.announcements.index') }}"
                class="text-xs font-semibold text-indigo-600 hover:text-indigo-800"
            >
                See all →
            </a>

        </div>


        <div class="space-y-3">

            @forelse($announcements as $a)

                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">

                    <div class="flex items-start gap-3">

                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">

                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="1.8"
                                    d="M12 18h.01M8.2 9a4 4 0 1 1 7.6 0c0 2-1.8 2.7-2.8 4-.4.5-.6 1-.6 2h-1c0-1-.2-1.5-.6-2-1-1.3-2.8-2-2.8-4Z"
                                />
                            </svg>

                        </div>

                        <div class="min-w-0">

                            <p class="font-semibold text-slate-800">
                                {{ $a->title }}
                            </p>

                            <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                {{ Str::limit($a->message, 140) }}
                            </p>

                        </div>

                    </div>

                </div>

            @empty

                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center">

                    <p class="text-sm font-semibold text-slate-700">
                        No announcements
                    </p>

                    <p class="mt-1 text-xs text-slate-500">
                        New announcements will appear here.
                    </p>

                </div>

            @endforelse

        </div>

    </section>

</div>

@endsection