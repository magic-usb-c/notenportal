{{-- Formular «Modul» (Admin-Stammdaten) für Anlegen und Bearbeiten. $modul ist null beim Anlegen. --}}
@php
    $wert = fn (string $k, mixed $standard = null) => old($k, $modul ? $modul->{$k} : $standard);
    $mbk = $modul ? \App\Support\Modulbaukasten::modulLink($modul->modul_nummer, $modul->version) : null;
    $versionBeschrieben = implode(' ', array_filter([$mbk ? null : 'version-hinweis', $errors->has('version') ? 'version-fehler' : null]));
@endphp
<form method="POST" action="{{ $modul ? route('admin.master-data.modules.update', $modul->modul_id) : route('admin.master-data.modules.store') }}"
      class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($modul)
        @method('PUT')
    @endif

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Modul') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Modulnummer')" fuer="modul_nummer" name="modul_nummer">
                <input type="text" id="modul_nummer" name="modul_nummer" value="{{ $wert('modul_nummer') }}" required maxlength="50" spellcheck="false"
                       @unless($modul) placeholder="M100" @endunless
                       class="np-feld w-32 tabular-nums" @error('modul_nummer') aria-invalid="true" aria-describedby="modul_nummer-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Titel')" fuer="titel" name="titel">
                <input type="text" id="titel" name="titel" value="{{ $wert('titel') }}" required maxlength="255"
                       class="np-feld w-80" @error('titel') aria-invalid="true" aria-describedby="titel-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Katalogversion')" fuer="version" name="version"
                           :hinweis="$mbk ? null : ($modul ? __('Ohne Version gibt es keinen Verweis auf den Modulbaukasten.') : __('Erzeugt den Verweis auf den Modulbaukasten.'))">
                @if($mbk)
                    <a href="{{ $mbk }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 items-center text-sm text-accent-text hover:underline underline-offset-2">{{ __('Im Modulbaukasten öffnen') }}</a>
                @endif
                <input type="text" id="version" name="version" value="{{ $wert('version') }}" inputmode="numeric" maxlength="2" placeholder="1"
                       @if($versionBeschrieben) aria-describedby="{{ $versionBeschrieben }}" @endif
                       class="np-feld w-16 text-right tabular-nums" @error('version') aria-invalid="true" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Ziel-Gewicht-Summe')" fuer="ziel_gewicht_summe_default" name="ziel_gewicht_summe_default">
                <input type="number" id="ziel_gewicht_summe_default" name="ziel_gewicht_summe_default" value="{{ old('ziel_gewicht_summe_default', $modul ? (string) (float) $modul->ziel_gewicht_summe_default : 100) }}"
                       step="0.01" min="0" class="np-feld w-28 text-right tabular-nums"
                       @error('ziel_gewicht_summe_default') aria-invalid="true" aria-describedby="ziel_gewicht_summe_default-fehler" @enderror>
            </x-einstellung>
            @if($modul)
                <x-einstellung :label="__('Modul aktiv')" fuer="aktiv" name="aktiv">
                    <input type="hidden" name="aktiv" value="0">
                    <input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" @checked($wert('aktiv')) class="np-schalter">
                </x-einstellung>
            @endif
            <x-einstellung :label="__('Beschreibung')" fuer="beschreibung" name="beschreibung" gestapelt>
                <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="2000" placeholder="{{ __('Optional') }}"
                          class="np-feld" @error('beschreibung') aria-invalid="true" aria-describedby="beschreibung-fehler" @enderror>{{ $wert('beschreibung') }}</textarea>
            </x-einstellung>
        </div>
    </section>

    <x-formular-aktionen :abbrechen="route('admin.master-data.modules.index')">{{ $modul ? __('Änderungen speichern') : __('Modul anlegen') }}</x-formular-aktionen>
</form>
