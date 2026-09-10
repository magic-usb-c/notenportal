<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">Eigenes Passwort festlegen</h1>
    </div>

    <form method="POST" action="{{ route('passwort.wechsel.speichern') }}" class="space-y-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')

        <div>
            <label for="password" class="block text-xs uppercase tracking-widest text-muted font-medium mb-1">Neues Passwort *</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                   class="block w-full h-11 rounded-xl border border-border bg-input text-text px-3
                          focus:outline-hidden focus:ring-2 focus:ring-accent/50 focus:border-accent
                          @error('password') border-red-500! @enderror">
            @error('password')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-xs uppercase tracking-widest text-muted font-medium mb-1">Passwort wiederholen *</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="block w-full h-11 rounded-xl border border-border bg-input text-text px-3
                          focus:outline-hidden focus:ring-2 focus:ring-accent/50 focus:border-accent">
        </div>

        <div class="pt-2">
            <button type="submit" :disabled="loading"
                    class="w-full flex justify-center items-center h-11 rounded-xl bg-accent text-white font-semibold np-btn-primary
                           focus:outline-hidden focus:ring-2 focus:ring-accent/50 disabled:opacity-60">
                Speichern
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-sm text-muted hover:text-text rounded-md focus:outline-hidden focus:ring-2 focus:ring-accent/50">
            Abmelden
        </button>
    </form>
</x-guest-layout>
