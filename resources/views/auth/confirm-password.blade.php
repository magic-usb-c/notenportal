<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">{{ __('Passwort bestätigen') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('Das ist ein geschützter Bereich. Bitte bestätige dein Passwort, bevor du fortfährst.') }}</p>
    </div>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf

        <div>
            <label for="password" class="block text-xs uppercase tracking-wide text-muted mb-1">{{ __('Passwort') }}</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-muted pointer-events-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <input id="password" type="password" name="password" required autocomplete="current-password" autofocus
                       placeholder="••••••••"
                       class="block w-full h-11 rounded-xl border border-border bg-input text-text pl-10 pr-3
                              focus:outline-hidden focus:ring-2 focus:ring-accent/50 focus:border-accent
                              @error('password') border-red-400 @enderror">
            </div>
            @error('password')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="w-full flex justify-center items-center h-11 rounded-xl bg-accent text-white font-semibold np-btn-primary
                           focus:outline-hidden focus:ring-2 focus:ring-accent/50">
                {{ __('Bestätigen') }}
            </button>
        </div>
    </form>
</x-guest-layout>
