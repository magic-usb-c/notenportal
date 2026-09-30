{{-- Sprache des Betriebs und ob Benutzer sie selbst wählen (Benutzermenü, Profil) – gilt sofort. --}}
@php
    $standard = \App\Http\Middleware\SetLocale::standard();
    $wahlAktiv = \App\Http\Middleware\SetLocale::wahlAktiv();
@endphp
<form method="POST" action="{{ route('admin.operations.language.update') }}#bedienung">
    @csrf
    @method('PUT')
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Sprache') }}</h2>
    <div class="np-karte np-gruppe">
        <x-einstellung :label="__('Standardsprache')" fuer="sprache_standard" name="sprache_standard">
            <select id="sprache_standard" name="sprache_standard" onchange="this.form.requestSubmit()" class="np-feld w-56">
                @foreach(['de' => 'Deutsch', 'en' => 'English'] as $wert => $name)
                    <option value="{{ $wert }}" lang="{{ $wert }}" @selected(old('sprache_standard', $standard) === $wert)>{{ $name }}</option>
                @endforeach
            </select>
        </x-einstellung>
        <x-einstellung :label="__('Benutzer wählen die Sprache selbst')" fuer="sprachwahl_aktiv" name="sprachwahl_aktiv"
                       :hinweis="__('Im Benutzermenü und im Profil')">
            <input id="sprachwahl_aktiv" name="sprachwahl_aktiv" type="checkbox" role="switch" value="1" @checked(old('sprachwahl_aktiv', $wahlAktiv))
                   onchange="this.form.requestSubmit()" class="np-schalter">
        </x-einstellung>
    </div>
    <noscript><button type="submit" class="np-knopf np-knopf-sekundaer mt-3">{{ __('Speichern') }}</button></noscript>
</form>
