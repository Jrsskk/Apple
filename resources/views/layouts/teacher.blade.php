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
        <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>
        <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed lg:static inset-y-0 left-0 z-50 w-64 bg-emerald-900 text-white transform transition-transform lg:translate-x-0 flex flex-col">
            <div class="p-4 border-b border-emerald-800">
                <a href="{{ route('dashboard') }}" class="text-xl font-bold">EduSync</a>
                <p class="text-emerald-300 text-xs mt-1">Teacher Portal</p>
            </div>
            <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
                <a href="{{ route('dashboard') }}" class="t-nav {{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
                <a href="{{ route('teacher.classes.index') }}" class="t-nav {{ request()->routeIs('teacher.classes.*') ? 'active' : '' }}">Classes</a>
                <a href="{{ route('teacher.quizzes.index') }}" class="t-nav {{ request()->routeIs('teacher.quizzes.*') ? 'active' : '' }}">Quizzes</a>
                <a href="{{ route('teacher.assignments.index') }}" class="t-nav {{ request()->routeIs('teacher.assignments.*') ? 'active' : '' }}">Assignments</a>
                <a href="{{ route('teacher.materials.index') }}" class="t-nav {{ request()->routeIs('teacher.materials.*') ? 'active' : '' }}">Materials</a>
                <a href="{{ route('teacher.grades.index') }}" class="t-nav {{ request()->routeIs('teacher.grades.*') ? 'active' : '' }}">Grades</a>
                <a href="{{ route('teacher.analytics.index') }}" class="t-nav {{ request()->routeIs('teacher.analytics.*') ? 'active' : '' }}">Analytics</a>
                <a href="{{ route('teacher.announcements.index') }}" class="t-nav {{ request()->routeIs('teacher.announcements.*') ? 'active' : '' }}">Announcements</a>
                <a href="{{ route('teacher.reports.index') }}" class="t-nav {{ request()->routeIs('teacher.reports.*') ? 'active' : '' }}">Reports</a>
            </nav>
            <div class="p-3 border-t border-emerald-800">
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="w-full text-left px-3 py-2 rounded hover:bg-emerald-800 text-sm">Logout</button></form>
            </div>
        </aside>
        <div class="flex-1 flex flex-col min-w-0">
            <header class="bg-white border-b px-4 py-3 flex items-center justify-between sticky top-0 z-30">
                <button @click="sidebarOpen = !sidebarOpen" class="lg:hidden p-2 rounded hover:bg-slate-100">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-lg font-semibold text-slate-800 truncate">@yield('header', 'Dashboard')</h1>
                <span class="text-sm text-slate-600 hidden sm:inline">{{ auth()->user()->full_name }}</span>
            </header>
            <main class="flex-1 p-4 md:p-6 overflow-x-hidden">
                @if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">{{ session('success') }}</div>@endif
                @yield('content')
            </main>
        </div>
    </div>
    @stack('scripts')
    <style>.t-nav{@apply block px-3 py-2 rounded text-sm text-emerald-100 hover:bg-emerald-800 transition}.t-nav.active{@apply bg-emerald-700 text-white font-medium}[x-cloak]{display:none!important}</style>
</body>
</html>
