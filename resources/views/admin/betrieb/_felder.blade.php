{{-- Betrieb, Notengrenzen und Fristen als gruppierte Listen – in «Betrieb» und im Einrichtungsschritt «Betrieb». --}}
@php
    $wert = fn (string $k) => old($k, $werte[$k]);
    $zahl = 'np-feld w-24 text-right tabular-nums';
@endphp
<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Betrieb') }}</h2>
    <div class="np-karte">
        <x-einstellung :label="__('Name des Betriebs')" fuer="betrieb_name" name="betrieb_name">
            <input id="betrieb_name" name="betrieb_name" type="text" required maxlength="120" value="{{ $wert('betrieb_name') }}" autocomplete="organization"
                   class="np-feld w-72" @error('betrieb_name') aria-invalid="true" aria-describedby="betrieb_name-fehler" @enderror>
        </x-einstellung>
    </div>
</section>

<section x-data="{ gut: {{ (float) $wert('note_gut') }}, gen: {{ (float) $wert('note_genuegend') }}, krit: {{ (float) $wert('note_kritisch') }},
                   b(v) { return Math.max(0, Math.min(100, (v - 1) / 5 * 100)); } }">
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Notengrenzen') }}</h2>
    <div class="np-karte np-gruppe">
        @foreach(['note_gut' => [__('Gut ab'), 'gut'], 'note_genuegend' => [__('Genügend ab'), 'gen'], 'note_kritisch' => [__('Knapp ab'), 'krit']] as $k => [$text, $modell])
            <x-einstellung :label="$text" :fuer="$k" :name="$k">
                <input id="{{ $k }}" name="{{ $k }}" type="number" required min="1" max="6" step="0.05" value="{{ $wert($k) }}" x-model.number="{{ $modell }}"
                       class="{{ $zahl }}" @error($k) aria-invalid="true" aria-describedby="{{ $k }}-fehler" @enderror>
            </x-einstellung>
        @endforeach
        <div class="px-4 pt-3.5 pb-3" aria-hidden="true">
            <div class="flex h-1.5 overflow-hidden rounded-full">
                <div class="bg-note-ungenuegend transition-[width] duration-150" :style="`width: ${b(krit)}%`"></div>
                <div class="bg-note-knapp transition-[width] duration-150" :style="`width: ${Math.max(0, b(gen) - b(krit))}%`"></div>
                <div class="bg-note-genuegend transition-[width] duration-150" :style="`width: ${Math.max(0, b(gut) - b(gen))}%`"></div>
                <div class="flex-1 bg-note-gut"></div>
            </div>
            <div class="mt-1.5 flex justify-between text-2xs text-muted tabular-nums"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span></div>
        </div>
    </div>
</section>

<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Auswertung und Fristen') }}</h2>
    <div class="np-karte np-gruppe">
        <x-einstellung :label="__('Rundung Gesamtschnitt')" fuer="rundung_gesamt" name="rundung_gesamt">
            <select id="rundung_gesamt" name="rundung_gesamt" class="np-feld w-24 tabular-nums"
                    @error('rundung_gesamt') aria-invalid="true" aria-describedby="rundung_gesamt-fehler" @enderror>
                @foreach(\App\Support\Betrieb::RUNDUNGEN_GESAMT as $r)
                    <option value="{{ $r }}" @selected(abs((float) $wert('rundung_gesamt') - (float) $r) < 0.0001)>{{ $r }}</option>
                @endforeach
            </select>
            <span class="w-20" aria-hidden="true"></span>
        </x-einstellung>
        <x-einstellung :label="__('Erinnerung ohne neue Note nach')" fuer="frist_inaktiv_tage" name="frist_inaktiv_tage">
            <input id="frist_inaktiv_tage" name="frist_inaktiv_tage" type="number" required min="7" max="365" value="{{ $wert('frist_inaktiv_tage') }}"
                   class="{{ $zahl }}" @error('frist_inaktiv_tage') aria-invalid="true" aria-describedby="frist_inaktiv_tage-fehler" @enderror>
            <span class="w-20 text-sm text-muted">{{ __('Tagen') }}</span>
        </x-einstellung>
        <x-einstellung :label="__('Ende der Lehre ankündigen')" fuer="frist_lehrende_tage" name="frist_lehrende_tage">
            <input id="frist_lehrende_tage" name="frist_lehrende_tage" type="number" required min="7" max="365" value="{{ $wert('frist_lehrende_tage') }}"
                   class="{{ $zahl }}" @error('frist_lehrende_tage') aria-invalid="true" aria-describedby="frist_lehrende_tage-fehler" @enderror>
            <span class="w-20 text-sm text-muted">{{ __('Tage vorher') }}</span>
        </x-einstellung>
    </div>
</section>
