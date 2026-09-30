{{-- Sprache: Standard des Betriebs und ob Benutzer die Sprache selbst wählen (Benutzermenü, Profil) --}}
@php
    $standard = \App\Http\Middleware\SetLocale::standard();
    $wahlAktiv = \App\Http\Middleware\SetLocale::wahlAktiv();
@endphp
<section class="np-karte p-6 mt-5">
    <h3 class="text-sm font-semibold text-text">{{ __('Sprache') }}</h3>
    <form method="POST" action="{{ route('admin.operations.language.update') }}" class="mt-4 flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <div>
            <label for="sprache_standard" class="text-sm font-medium text-text">{{ __('Standardsprache') }}</label>
            <select id="sprache_standard" name="sprache_standard" aria-describedby="sprache_standard-fehler"
                    class="np-feld mt-1.5 sm:max-w-xs">
                @foreach(['de' => 'Deutsch', 'en' => 'English'] as $wert => $name)
                    <option value="{{ $wert }}" lang="{{ $wert }}" @selected(old('sprache_standard', $standard) === $wert)>{{ $name }}</option>
                @endforeach
            </select>
            @error('sprache_standard')<p id="sprache_standard-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>
        <label for="sprachwahl_aktiv" class="flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
            <input id="sprachwahl_aktiv" name="sprachwahl_aktiv" type="checkbox" role="switch" value="1" @checked(old('sprachwahl_aktiv', $wahlAktiv))
                   class="np-schalter">
            {{ __('Benutzer wählen die Sprache selbst') }}
        </label>
        <div class="flex justify-end">
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Speichern') }}</button>
        </div>
    </form>
</section>
