<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">{{ __('Passwort vergessen') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('Gib deine E-Mail-Adresse ein. Wir schicken dir einen Link zum Zurücksetzen.') }}</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf

        <div>
            <label for="email" class="block text-xs uppercase tracking-wide text-muted mb-1">{{ __('E-Mail') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   autocomplete="username" placeholder="{{ __('name@firma.ch') }}"
                   class="block w-full h-11 rounded-xl border border-border bg-input text-text px-3
                          focus:outline-hidden focus:ring-2 focus:ring-accent/50 focus:border-accent
                          @error('email') border-red-400 @enderror">
            @error('email')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-2">
            <button type="submit" :disabled="loading"
                    class="w-full flex justify-center items-center h-11 rounded-xl bg-accent text-white font-semibold np-btn-primary
                           focus:outline-hidden focus:ring-2 focus:ring-accent/50 disabled:opacity-60">
                {{ __('Link zusenden') }}
            </button>
        </div>
    </form>

    <p class="mt-6 text-center text-sm">
        <a href="{{ route('login') }}" class="text-muted hover:text-text">{{ __('Zurück zur Anmeldung') }}</a>
    </p>
</x-guest-layout>
