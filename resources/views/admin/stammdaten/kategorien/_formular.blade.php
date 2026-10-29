{{-- Formular «Notenkategorie» für Anlegen und Bearbeiten. $kategorie ist null beim Anlegen.
     Rundungen als Text normalisiert ("0.50" aus der DB = Option "0.5"); leere Promotionsfelder = keine Regel. --}}
@php
    $wert = fn (string $k, mixed $standard = null) => old($k, $kategorie ? $kategorie->{$k} : $standard);
    $rundung = fn (string $k, string $standard) => (string) (float) $wert($k, $standard);
    $rundungen = ['0' => __('Ungerundet'), '0.1' => '0.1', '0.25' => '0.25', '0.5' => '0.5', '1' => '1'];
    $zahl = 'np-feld w-24 text-right tabular-nums';
    // Zahlen aus der DB ohne Nachkommastellen-Rest ("1.00" -> "1"); eine Eingabe nach Validierungsfehler bleibt, wie getippt.
    $zahlWert = fn (string $k, mixed $standard = null) => old($k, $kategorie ? ($kategorie->{$k} === null ? '' : (string) (float) $kategorie->{$k}) : $standard);
@endphp
<form method="POST" action="{{ $kategorie ? route('admin.master-data.categories.update', $kategorie->kategorie_id) : route('admin.master-data.categories.store') }}"
      class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($kategorie)
        @method('PUT')
    @endif

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Notenkategorie') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Name')" fuer="name" name="name">
                <input type="text" id="name" name="name" value="{{ $wert('name') }}" required maxlength="50"
                       class="np-feld w-72" @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Code')" fuer="code" name="code">
                <input type="text" id="code" name="code" value="{{ $wert('code') }}" required maxlength="30" spellcheck="false" autocapitalize="characters"
                       class="np-feld w-40" @error('code') aria-invalid="true" aria-describedby="code-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Sortierung')" fuer="sortierung" name="sortierung">
                <input type="number" id="sortierung" name="sortierung" value="{{ $wert('sortierung') }}" min="0" placeholder="{{ __('Optional') }}"
                       class="{{ $zahl }}" @error('sortierung') aria-invalid="true" aria-describedby="sortierung-fehler" @enderror>
            </x-einstellung>
            @if($kategorie)
                <x-einstellung :label="__('Aktiv')" fuer="aktiv" name="aktiv">
                    <input type="hidden" name="aktiv" value="0">
                    <input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" @checked($wert('aktiv')) class="np-schalter">
                </x-einstellung>
            @endif
        </div>
    </section>

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Berechnung') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Rundung Zeugnisnote')" name="rundung_element">
                <x-segment-auswahl name="rundung_element" required :wert="$rundung('rundung_element', '0.5')" :optionen="$rundungen" />
            </x-einstellung>
            <x-einstellung :label="__('Rundung Schnitt')" name="rundung_schnitt">
                <x-segment-auswahl name="rundung_schnitt" required :wert="$rundung('rundung_schnitt', '0.1')" :optionen="$rundungen" />
            </x-einstellung>
            <x-einstellung :label="__('Gewicht im Gesamtschnitt')" fuer="gewicht_gesamt" name="gewicht_gesamt">
                <input type="number" id="gewicht_gesamt" name="gewicht_gesamt" value="{{ $zahlWert('gewicht_gesamt', '1') }}" required min="0" step="0.25"
                       class="{{ $zahl }}" @error('gewicht_gesamt') aria-invalid="true" aria-describedby="gewicht_gesamt-fehler" @enderror>
            </x-einstellung>
        </div>
    </section>

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Promotion') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Mindestschnitt')" fuer="promotion_min_schnitt" name="promotion_min_schnitt">
                <input type="number" id="promotion_min_schnitt" name="promotion_min_schnitt" value="{{ $zahlWert('promotion_min_schnitt') }}" min="1" max="6" step="0.1"
                       placeholder="{{ __('Optional') }}" class="{{ $zahl }}"
                       @error('promotion_min_schnitt') aria-invalid="true" aria-describedby="promotion_min_schnitt-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Max. ungenügende Noten')" fuer="promotion_max_ungenuegend" name="promotion_max_ungenuegend">
                <input type="number" id="promotion_max_ungenuegend" name="promotion_max_ungenuegend" value="{{ $zahlWert('promotion_max_ungenuegend') }}" min="0" max="20" step="1"
                       placeholder="{{ __('Optional') }}" class="{{ $zahl }}"
                       @error('promotion_max_ungenuegend') aria-invalid="true" aria-describedby="promotion_max_ungenuegend-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Max. Minuspunkte')" fuer="promotion_max_minuspunkte" name="promotion_max_minuspunkte">
                <input type="number" id="promotion_max_minuspunkte" name="promotion_max_minuspunkte" value="{{ $zahlWert('promotion_max_minuspunkte') }}" min="0" max="20" step="0.5"
                       placeholder="{{ __('Optional') }}" class="{{ $zahl }}"
                       @error('promotion_max_minuspunkte') aria-invalid="true" aria-describedby="promotion_max_minuspunkte-fehler" @enderror>
            </x-einstellung>
        </div>
        <p class="mt-2 px-1 text-xs text-muted">{{ __('Leere Felder setzen keine Bedingung.') }}</p>
    </section>

    <x-formular-aktionen :abbrechen="route('admin.master-data.categories.index')">{{ $kategorie ? __('Änderungen speichern') : __('Kategorie anlegen') }}</x-formular-aktionen>
</form>
