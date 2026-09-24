<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">{{ __('Neues Passwort festlegen') }}</h1>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-xs uppercase tracking-wide text-muted mb-1">{{ __('E-Mail') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus
                   autocomplete="username"
                   class="block w-full h-11 rounded-xl border border-border bg-input text-text px-3
                          focus:outline-hidden focus:ring-2 focus:ring-accent/50 focus:border-accent
                          @error('email') border-note-ungenuegend @enderror">
            @error('email')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-xs uppercase tracking-wide text-muted mb-1">{{ __('Neues Passwort') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="block w-full h-11 rounded-xl border border-border bg-input text-text px-3
                          focus:outline-hidden focus:ring-2 focus:ring-accent/50 focus:border-accent
                          @error('password') border-note-ungenuegend @enderror">
            @error('password')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs uppercase tracking-wide text-muted mb-1">{{ __('Passwort bestätigen') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="block w-full h-11 rounded-xl border border-border bg-input text-text px-3
                          focus:outline-hidden focus:ring-2 focus:ring-accent/50 focus:border-accent">
        </div>

        <div class="pt-2">
            <button type="submit" :disabled="loading"
                    class="w-full flex justify-center items-center h-11 rounded-xl bg-accent text-accent-contrast font-semibold np-btn-primary
                           focus:outline-hidden focus:ring-2 focus:ring-accent/50 disabled:opacity-60">
                {{ __('Passwort speichern') }}
            </button>
        </div>
    </form>
</x-guest-layout>
