<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">Notenportal</h1>
        <p class="mt-1 text-sm text-muted">Hamilton Services AG</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-xs uppercase tracking-wide text-muted mb-1">E-Mail</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-muted pointer-events-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" placeholder="name@firma.ch"
                       class="block w-full h-11 rounded-xl border border-border bg-input text-text pl-10 pr-3
                              focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent
                              @error('email') border-red-400 @enderror">
            </div>
            @error('email')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs uppercase tracking-wide text-muted mb-1">Passwort</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-muted pointer-events-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••"
                       class="block w-full h-11 rounded-xl border border-border bg-input text-text pl-10 pr-3
                              focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent
                              @error('password') border-red-400 @enderror">
            </div>
            @error('password')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center gap-2 select-none cursor-pointer">
                <input id="remember_me" type="checkbox" name="remember"
                       class="rounded border-border bg-input text-accent focus:ring-2 focus:ring-accent/50">
                <span class="text-sm text-muted">Angemeldet bleiben</span>
            </label>

            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-sm text-muted hover:text-text rounded-md
                          focus:outline-none focus:ring-2 focus:ring-accent/50">
                    Passwort vergessen?
                </a>
            @endif
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="w-full flex justify-center items-center h-11 rounded-xl bg-accent text-white font-semibold np-btn-primary
                           focus:outline-none focus:ring-2 focus:ring-accent/50">
                Anmelden
            </button>
        </div>
    </form>

    <div class="mt-6 text-xs text-muted text-center">
        Accounts werden durch den Admin erstellt.
    </div>
</x-guest-layout>
