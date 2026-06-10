<x-guest-layout>
    <div class="mb-4 text-sm text-muted">
        Passwort vergessen? Gib deine E-Mail-Adresse ein – wir schicken dir einen Link zum Zurücksetzen.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-muted">E-Mail</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
            @error('email')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-1">
            <button type="submit"
                    class="w-full flex justify-center px-4 py-2 h-10 rounded-xl bg-accent text-white font-medium np-btn-primary">
                Link zusenden
            </button>
        </div>
    </form>

    <div class="mt-4 text-center">
        <a href="{{ route('login') }}" class="text-sm text-muted hover:text-text underline">
            Zurück zur Anmeldung
        </a>
    </div>
</x-guest-layout>
