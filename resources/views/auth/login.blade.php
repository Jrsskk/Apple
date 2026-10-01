<x-guest-layout>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-blue-700">Secure school portal</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950">Welcome back</h1>
        <p class="mt-2 text-sm leading-6 text-slate-500">Sign in with your EduSync account to continue to your workspace.</p>
    </div>

    <x-auth-session-status class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5" id="login-form">
        @csrf
        <div>
            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Email or username</label>
            <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-0 grid w-12 place-items-center text-slate-400"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h16v16H4zM4 6l8 7 8-7"/></svg></span><input id="email" class="block w-full rounded-xl border-slate-200 bg-slate-50 py-3 pl-12 pr-4 text-sm placeholder:text-slate-400 focus:bg-white" type="text" name="email" value="{{ old('email') }}" placeholder="name@school.edu or username" required autofocus autocomplete="username"></div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <div class="mb-2 flex items-center justify-between"><label for="password" class="text-sm font-semibold text-slate-700">Password</label>@if (Route::has('password.request'))<a class="text-xs font-semibold text-blue-700 hover:text-blue-900" href="{{ route('password.request') }}">Forgot password?</a>@endif</div>
            <div class="relative"><span class="pointer-events-none absolute inset-y-0 left-0 grid w-12 place-items-center text-slate-400"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 018 0v3"/></svg></span><input id="password" class="block w-full rounded-xl border-slate-200 bg-slate-50 py-3 pl-12 pr-12 text-sm focus:bg-white" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password"><button type="button" id="password-toggle" class="absolute inset-y-0 right-0 grid w-12 place-items-center text-xs font-semibold text-slate-500 hover:text-blue-700" aria-label="Show password">Show</button></div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <label for="remember_me" class="flex cursor-pointer items-center gap-2.5 text-sm text-slate-600"><input id="remember_me" type="checkbox" class="rounded border-slate-300 text-blue-700 focus:ring-blue-600" name="remember">Keep me signed in on this device</label>
        <button type="submit" class="flex w-full items-center justify-center rounded-xl bg-slate-950 px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-slate-300 transition hover:-translate-y-0.5 hover:bg-blue-700 disabled:cursor-wait disabled:opacity-70"><span>Sign in to EduSync</span><svg class="ml-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
    </form>
    <p class="mt-7 text-center text-xs leading-5 text-slate-400">Having trouble signing in? Contact your school administrator.</p>
    <script>document.getElementById('password-toggle').addEventListener('click',function(){const input=document.getElementById('password');const show=input.type==='password';input.type=show?'text':'password';this.textContent=show?'Hide':'Show';this.setAttribute('aria-label',show?'Hide password':'Show password')});document.getElementById('login-form').addEventListener('submit',function(){const button=this.querySelector('button[type=submit]');button.disabled=true;button.querySelector('span').textContent='Signing in…'});</script>
</x-guest-layout>
