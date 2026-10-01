<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
    @stack('styles')
</head>
<body class="bg-slate-50 font-sans antialiased" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen flex">
        {{-- Mobile overlay --}}
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
             class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

        {{-- Sidebar --}}
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-indigo-900 text-white transform transition-transform lg:translate-x-0 flex flex-col">
            <div class="p-4 border-b border-indigo-800">
                <a href="{{ route('dashboard') }}" class="text-xl font-bold">EduSync</a>
                <p class="text-indigo-300 text-xs mt-1">Administrator</p>
            </div>
            <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">Users</a>
                <a href="{{ route('admin.academic-years.index') }}" class="nav-link {{ request()->routeIs('admin.academic-years.*') ? 'active' : '' }}">Academic Years</a>
                <a href="{{ route('admin.subjects.index') }}" class="nav-link {{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}">Subjects</a>
                <a href="{{ route('admin.classes.index') }}" class="nav-link {{ request()->routeIs('admin.classes.*') ? 'active' : '' }}">Classes</a>
                <a href="{{ route('admin.announcements.index') }}" class="nav-link {{ request()->routeIs('admin.announcements.*') ? 'active' : '' }}">Announcements</a>
                <a href="{{ route('admin.reports.index') }}" class="nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}">Reports</a>
                <a href="{{ route('admin.audit-logs.index') }}" class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}">Audit Logs</a>
                <a href="{{ route('admin.backups.index') }}" class="nav-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">Backups</a>
            </nav>
            <div class="p-3 border-t border-indigo-800">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full text-left px-3 py-2 rounded hover:bg-indigo-800 text-sm">Logout</button>
                </form>
            </div>
        </aside>

        <div class="flex-1 flex flex-col min-w-0">
            <header class="bg-white border-b px-4 py-3 flex items-center justify-between sticky top-0 z-30">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded hover:bg-slate-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-lg font-semibold text-slate-800 truncate">@yield('header', 'Dashboard')</h1>
                <div class="flex items-center gap-3">
                    @include('components.sync-status')
                    <span class="text-sm text-slate-600 hidden sm:inline">{{ auth()->user()->full_name }}</span>
                </div>
            </header>
            <main class="flex-1 p-4 md:p-6 overflow-x-hidden">
                @if(session('success'))
                    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="mb-4 p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ session('error') }}</div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
    <style>
        .nav-link { @apply block px-3 py-2 rounded text-sm text-indigo-100 hover:bg-indigo-800 transition; }
        .nav-link.active { @apply bg-indigo-700 text-white font-medium; }
        [x-cloak] { display: none !important; }
    </style>
</body>
</html>
