<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Passwort') }}</h2>
    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('put')

        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Aktuelles Passwort')" fuer="update_password_current" name="current_password" beutel="updatePassword">
                <input id="update_password_current" name="current_password" type="password" autocomplete="current-password"
                       class="np-feld w-72" @if($errors->updatePassword->has('current_password')) aria-invalid="true" aria-describedby="current_password-fehler" @endif>
            </x-einstellung>
            <x-einstellung :label="__('Neues Passwort')" fuer="update_password_password" name="password" beutel="updatePassword">
                <input id="update_password_password" name="password" type="password" autocomplete="new-password"
                       class="np-feld w-72" @if($errors->updatePassword->has('password')) aria-invalid="true" aria-describedby="password-fehler" @endif>
            </x-einstellung>
            <x-einstellung :label="__('Passwort bestätigen')" fuer="update_password_confirmation" name="password_confirmation" beutel="updatePassword">
                <input id="update_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"
                       class="np-feld w-72">
            </x-einstellung>
        </div>

        <div class="flex justify-end">
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">
                {{ __('Passwort ändern') }}
            </button>
        </div>
    </form>
</section>
