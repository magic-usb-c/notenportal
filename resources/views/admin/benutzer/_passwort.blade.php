{{-- Passwortabschnitt der Benutzerformulare. $pflicht: beim Anlegen Pflicht, beim Bearbeiten leer = unverändert. --}}
<section x-data="npPasswortFelder">
    <div class="mb-2 flex items-end justify-between gap-4 px-1">
        <h2 class="text-sm font-semibold text-text">{{ $pflicht ? __('Passwort') : __('Neues Passwort') }}</h2>
        <button type="button" @click="generieren()" class="np-knopf np-knopf-schlicht np-knopf-klein -mb-1">{{ __('Passwort generieren') }}</button>
    </div>
    <div class="np-karte np-gruppe">
        <x-einstellung :label="__('Passwort')" fuer="passwort" name="passwort"
                       :hinweis="__('Mindestens 10 Zeichen mit Buchstaben und Ziffern')">
            <button type="button" @click="sichtbar = !sichtbar" :aria-pressed="sichtbar" aria-label="{{ __('Passwort anzeigen') }}"
                    class="np-knopf np-knopf-symbol np-knopf-rund">
                <x-symbol name="eye" class="size-4" x-show="!sichtbar" />
                <x-symbol name="eye-slash" class="size-4" x-show="sichtbar" x-cloak />
            </button>
            <input x-ref="pw1" :type="sichtbar ? 'text' : 'password'" name="passwort" id="passwort" minlength="10" autocomplete="new-password"
                   @if($pflicht) required @else placeholder="{{ __('Unverändert') }}" @endif
                   class="np-feld w-72" @error('passwort') aria-invalid="true" @enderror>
        </x-einstellung>
        <x-einstellung :label="__('Passwort bestätigen')" fuer="passwort_confirmation">
            <input x-ref="pw2" :type="sichtbar ? 'text' : 'password'" name="passwort_confirmation" id="passwort_confirmation" autocomplete="new-password"
                   @if($pflicht) required @endif class="np-feld w-72">
        </x-einstellung>
    </div>
</section>
