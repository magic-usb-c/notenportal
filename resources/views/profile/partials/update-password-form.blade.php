<section>
    <h2 class="text-base font-semibold text-text">{{ __('Passwort ändern') }}</h2>

    <form method="POST" action="{{ route('password.update') }}" class="mt-5 space-y-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('put')

        <div>
            <label for="current_password" class="block text-sm font-medium text-muted">{{ __('Aktuelles Passwort') }}</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                   class="np-feld mt-1 block @if($errors->updatePassword->get('current_password')) border-note-ungenuegend @endif">
            @foreach($errors->updatePassword->get('current_password') as $msg)
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $msg }}</p>
            @endforeach
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-muted">{{ __('Neues Passwort') }}</label>
            <input id="password" name="password" type="password" autocomplete="new-password"
                   class="np-feld mt-1 block @if($errors->updatePassword->get('password')) border-note-ungenuegend @endif">
            @foreach($errors->updatePassword->get('password') as $msg)
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $msg }}</p>
            @endforeach
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-muted">{{ __('Passwort bestätigen') }}</label>
            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                   class="np-feld mt-1 block">
        </div>

        <div class="pt-1 flex items-center gap-4">
            <button type="submit" :disabled="loading"
                    class="np-knopf np-knopf-primaer np-knopf-gross">
                {{ __('Passwort ändern') }}
            </button>
        </div>
    </form>
</section>
