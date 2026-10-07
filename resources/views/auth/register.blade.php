<x-guest-layout>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-700">Student registration</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Create your account</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Register with your email address. We’ll send you a verification link before you can access the student portal.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <label for="name" class="mb-2 block text-sm font-semibold text-slate-700">Full Name</label>
            <input id="name" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:bg-white" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email/Gmail</label>
            <input id="email" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:bg-white" type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <label for="password" class="mb-2 block text-sm font-semibold text-slate-700">Password</label>
            <input id="password" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:bg-white" type="password" name="password" required autocomplete="new-password">
            <p class="mt-2 text-xs text-slate-500">Use at least 8 characters, including uppercase and lowercase letters, a number, and a symbol.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <label for="password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirm Password</label>
            <input id="password_confirmation" class="block w-full rounded-xl border-slate-200 bg-slate-50 px-4 py-3 text-sm focus:bg-white" type="password" name="password_confirmation" required autocomplete="new-password">
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-slate-950 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-slate-300 transition hover:bg-blue-700">Create student account</button>
    </form>

    <p class="mt-7 text-center text-sm text-slate-600">
        Already have an account?
        <a class="font-semibold text-blue-700 hover:text-blue-900" href="{{ route('login') }}">Sign in</a>
    </p>
</x-guest-layout>
