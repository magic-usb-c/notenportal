<section>
    <h2 class="text-base font-semibold text-text">Passwort ändern</h2>
    <p class="mt-1 text-sm text-muted">Für mehr Sicherheit ein langes, zufälliges Passwort verwenden.</p>

    <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('put')

        <div>
            <label for="current_password" class="block text-sm font-medium text-muted">Aktuelles Passwort</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @if($errors->updatePassword->get('current_password')) border-red-400 @endif">
            @foreach($errors->updatePassword->get('current_password') as $msg)
                <p class="mt-1 text-xs text-red-500">{{ $msg }}</p>
            @endforeach
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-muted">Neues Passwort</label>
            <input id="password" name="password" type="password" autocomplete="new-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @if($errors->updatePassword->get('password')) border-red-400 @endif">
            @foreach($errors->updatePassword->get('password') as $msg)
                <p class="mt-1 text-xs text-red-500">{{ $msg }}</p>
            @endforeach
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-muted">Passwort bestätigen</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring">
        </div>

        <div class="pt-1 flex items-center gap-4">
            <button type="submit" :disabled="loading"
                    class="px-5 py-2 h-10 rounded-xl bg-accent text-white font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                Passwort ändern
            </button>
        </div>
    </form>
</section>
