<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-muted">E-Mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="username" placeholder="name@firma.ch"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
            @error('email')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-muted">Passwort</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   placeholder="••••••••"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('password') border-red-400 @enderror">
            @error('password')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between gap-3">
            <label for="remember_me" class="inline-flex items-center gap-2 select-none cursor-pointer">
                <input id="remember_me" type="checkbox" name="remember"
                       class="rounded border-border bg-input text-accent focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg">
                <span class="text-sm text-muted">Angemeldet bleiben</span>
            </label>

            @if(Route::has('password.request'))
                <a href="{{ route('password.request') }}"
                   class="text-sm text-muted hover:text-text underline decoration-transparent hover:decoration-inherit rounded-md
                          focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg">
                    Passwort vergessen?
                </a>
            @endif
        </div>

        <div class="pt-1">
            <button type="submit"
                    class="w-full flex justify-center px-4 py-2 h-10 rounded-xl bg-accent text-white font-medium hover:opacity-90
                           focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg">
                Anmelden
            </button>
        </div>
    </form>

    <div class="mt-6 text-xs text-muted text-center">
        Accounts werden durch den Admin erstellt.
    </div>
</x-guest-layout>
