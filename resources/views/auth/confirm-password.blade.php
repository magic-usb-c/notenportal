<x-guest-layout>
    <div class="mb-4 text-sm text-muted">
        Sicherheitsbereich – bitte bestätige dein Passwort, bevor du weitergehst.
    </div>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf

        <div>
            <label for="password" class="block text-sm font-medium text-muted">Passwort</label>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('password') border-red-400 @enderror">
            @error('password')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-1">
            <button type="submit" :disabled="loading"
                    class="w-full flex justify-center px-4 py-2 h-10 rounded-xl bg-accent text-white font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                Bestätigen
            </button>
        </div>
    </form>
</x-guest-layout>
