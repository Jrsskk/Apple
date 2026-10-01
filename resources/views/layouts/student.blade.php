<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">

    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">

    <title>@yield('title', 'Home') - EduSync</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js',
        'resources/js/offline-sync.js'
    ])

    @stack('styles')
</head>

<body
    class="min-h-screen bg-slate-50 font-sans antialiased text-slate-800"
    x-data="{ sidebarOpen: false }"
    @keydown.escape.window="sidebarOpen = false"
>

<div class="min-h-screen">

    {{-- Mobile Overlay --}}
    <div
        x-show="sidebarOpen"
        x-cloak
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-slate-950/50 backdrop-blur-sm lg:hidden"
    ></div>


    {{-- SIDEBAR --}}
    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col border-r border-slate-200 bg-white shadow-xl transition-transform duration-300 lg:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
    >

        {{-- Logo --}}
        <div class="flex h-20 items-center border-b border-slate-100 px-5">

            <div class="flex items-center gap-3">

                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-indigo-600 text-lg font-black text-white shadow-md">
                    E
                </div>

                <div>
                    <div class="text-lg font-bold tracking-tight text-slate-900">
                        EduSync
                    </div>

                    <div class="text-xs font-medium text-slate-500">
                        Student Portal
                    </div>
                </div>

            </div>

            {{-- Mobile Close --}}
            <button
                @click="sidebarOpen = false"
                class="ml-auto flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 lg:hidden"
                aria-label="Close menu"
            >
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>

        </div>


        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto px-3 py-5">

            <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                Main Menu
            </p>

            @php
                $nav = [
                    [
                        'route' => 'dashboard',
                        'label' => 'Home',
                        'icon' => 'M3 12.75L12 4l9 8.75V20a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1v-7.25Z'
                    ],
                    [
                        'route' => 'student.classes.index',
                        'label' => 'Classes',
                        'icon' => 'M4 7.5A2.5 2.5 0 0 1 6.5 5h11A2.5 2.5 0 0 1 20 7.5v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Zm4.5 0h7m-7 4h7m-7 4h4'
                    ],
                    [
                        'route' => 'student.materials.index',
                        'label' => 'Materials',
                        'icon' => 'M7 4.5A2.5 2.5 0 0 1 9.5 2h5A2.5 2.5 0 0 1 17 4.5v15A2.5 2.5 0 0 1 14.5 22h-5A2.5 2.5 0 0 1 7 19.5v-15Zm2 0h6m-6 4h6m-6 4h6m-6 4h4'
                    ],
                    [
                        'route' => 'student.activities.index',
                        'label' => 'Tasks',
                        'icon' => 'M9 5h6m-9 4h12M7 9V7.5A2.5 2.5 0 0 1 9.5 5h5A2.5 2.5 0 0 1 17 7.5V9m-10 0h10v8.5A2.5 2.5 0 0 1 14.5 20h-5A2.5 2.5 0 0 1 7 17.5V9Z'
                    ],
                    [
                        'route' => 'student.grades.index',
                        'label' => 'Grades',
                        'icon' => 'M5 19V7.5A1.5 1.5 0 0 1 6.5 6H9v10H6.5A1.5 1.5 0 0 0 5 17.5zm7 0V5h2.5A1.5 1.5 0 0 1 16 6.5V19h-4zm-6.5 0h13'
                    ],
                    [
                        'route' => 'student.profile.edit',
                        'label' => 'Profile',
                        'icon' => 'M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 8a7 7 0 0 1 14 0'
                    ],
                    [
                        'route' => 'student.announcements.index',
                        'label' => 'Announcements',
                        'icon' => 'M6 17h12l-1.2-8.4A4.8 4.8 0 0 0 12 4a4.8 4.8 0 0 0-4.8 4.6L6 17Zm4 0a2 2 0 0 0 4 0'
                    ],
                    [
                        'route' => 'student.sync.index',
                        'label' => 'Sync',
                        'icon' => 'M20 12a8 8 0 1 1-2.3-5.7M20 4v6h-6'
                    ],
                ];
            @endphp


            <div class="space-y-1">

                @foreach($nav as $item)

                    @php
                        $isActive = request()->routeIs($item['route'])
                            || request()->routeIs($item['route'].'.*');
                    @endphp

                    <a
                        href="{{ route($item['route']) }}"
                        @click="sidebarOpen = false"
                        class="group flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium transition-all duration-200
                        {{ $isActive
                            ? 'bg-indigo-50 text-indigo-700'
                            : 'text-slate-600 hover:bg-slate-50 hover:text-indigo-600'
                        }}"
                    >

                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                            {{ $isActive
                                ? 'bg-indigo-600 text-white shadow-sm'
                                : 'bg-slate-100 text-slate-500 group-hover:bg-indigo-50 group-hover:text-indigo-600'
                            }}"
                        >

                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="{{ $item['icon'] }}"
                                />
                            </svg>

                        </span>

                        <span>{{ $item['label'] }}</span>

                        @if($isActive)
                            <span class="ml-auto h-2 w-2 rounded-full bg-indigo-600"></span>
                        @endif

                    </a>

                @endforeach

            </div>

        </nav>


        {{-- User / Logout --}}
        <div class="border-t border-slate-100 p-3">

            <div class="mb-2 flex items-center gap-3 rounded-xl bg-slate-50 p-3">

                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700">
                    {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                </div>

                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-slate-800">
                        {{ auth()->user()->full_name }}
                    </p>

                    <p class="text-xs text-slate-500">
                        Student
                    </p>
                </div>

            </div>


            <form method="POST" action="{{ route('logout') }}">
                @csrf

                <button
                    type="submit"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-slate-600 transition hover:bg-red-50 hover:text-red-600"
                >

                    <svg
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M10 17l5-5m0 0l-5-5m5 5H3m10 5v1a2 2 0 0 0 2 2h7a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-7a2 2 0 0 0-2 2v1"
                        />
                    </svg>

                    <span>Logout</span>

                </button>

            </form>

        </div>

    </aside>


    {{-- MAIN CONTENT --}}
    <div class="min-h-screen lg:pl-64">

        {{-- Header --}}
        <header class="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">

            <div class="flex h-16 items-center justify-between px-4 sm:px-6">

                <div class="flex items-center gap-3">

                    {{-- Mobile Menu --}}
                    <button
                        @click="sidebarOpen = true"
                        class="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 lg:hidden"
                        aria-label="Open menu"
                    >

                        <svg
                            class="h-5 w-5"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 7h16M4 12h16M4 17h16"
                            />
                        </svg>

                    </button>


                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-600">
                            Student
                        </p>

                        <h1 class="text-lg font-bold text-slate-900">
                            @yield('header', 'Home')
                        </h1>
                    </div>

                </div>


                <div class="flex items-center gap-3">

                    @include('components.sync-status')

                    <div class="hidden h-9 w-9 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-700 sm:flex">
                        {{ strtoupper(substr(auth()->user()->first_name, 0, 1)) }}
                    </div>

                </div>

            </div>

        </header>


        {{-- Page Content --}}
        <main class="min-h-[calc(100vh-4rem)] overflow-x-hidden p-4 sm:p-6">

            @if(session('success'))

                <div class="mb-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
                    {{ session('success') }}
                </div>

            @endif


            @if($errors->any())

                <div class="mb-5 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>

            @endif


            @yield('content')

        </main>

    </div>

</div>


@stack('scripts')

</body>
</html>