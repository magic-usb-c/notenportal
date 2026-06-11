<section>
    <h2 class="text-base font-semibold text-text">Profildaten</h2>
    <p class="mt-1 text-sm text-muted">Name und E-Mail-Adresse ändern.</p>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-5 space-y-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('patch')

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label for="vorname" class="block text-sm font-medium text-muted">Vorname *</label>
                <input id="vorname" name="vorname" type="text"
                       value="{{ old('vorname', $user->vorname) }}" required autocomplete="given-name"
                       class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                              focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-red-400 @enderror">
                @error('vorname')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label for="nachname" class="block text-sm font-medium text-muted">Nachname *</label>
                <input id="nachname" name="nachname" type="text"
                       value="{{ old('nachname', $user->nachname) }}" required autocomplete="family-name"
                       class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                              focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-red-400 @enderror">
                @error('nachname')
                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-muted">E-Mail *</label>
            <input id="email" name="email" type="email"
                   value="{{ old('email', $user->email) }}" required autocomplete="email"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
            @error('email')
                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-1 flex items-center gap-4">
            <button type="submit" :disabled="loading"
                    class="px-5 py-2 h-10 rounded-xl bg-accent text-white font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                Speichern
            </button>
        </div>
    </form>
</section>
