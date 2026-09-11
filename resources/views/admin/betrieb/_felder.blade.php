@php
    $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
    $label = 'text-sm font-medium text-text';
    $wert = fn (string $k) => old($k, $werte[$k]);
@endphp
<div class="flex flex-col gap-6">
    <div>
        <label for="betrieb_name" class="{{ $label }}">{{ __('Name des Betriebs') }} *</label>
        <input id="betrieb_name" name="betrieb_name" type="text" required maxlength="120" value="{{ $wert('betrieb_name') }}" class="{{ $feld }}" autocomplete="organization">
        @error('betrieb_name')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
    </div>

    <div x-data="{ gut: {{ (float) $wert('note_gut') }}, gen: {{ (float) $wert('note_genuegend') }}, krit: {{ (float) $wert('note_kritisch') }},
                   b(v) { return Math.max(0, Math.min(100, (v - 1) / 5 * 100)); } }">
        <div class="grid grid-cols-3 gap-4">
            @foreach(['note_gut' => [__('Gut ab'), 'gut'], 'note_genuegend' => [__('Genügend ab'), 'gen'], 'note_kritisch' => [__('Knapp ab'), 'krit']] as $k => [$text, $modell])
                <div>
                    <label for="{{ $k }}" class="{{ $label }}">{{ $text }} *</label>
                    <input id="{{ $k }}" name="{{ $k }}" type="number" required min="1" max="6" step="0.05" value="{{ $wert($k) }}" x-model.number="{{ $modell }}" class="{{ $feld }} tabular-nums">
                    @error($k)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="mt-3 flex h-2 rounded-full overflow-hidden" aria-hidden="true">
            <div class="bg-note-ungenuegend transition-all" :style="`width: ${b(krit)}%`"></div>
            <div class="bg-note-knapp transition-all" :style="`width: ${Math.max(0, b(gen) - b(krit))}%`"></div>
            <div class="bg-note-genuegend transition-all" :style="`width: ${Math.max(0, b(gut) - b(gen))}%`"></div>
            <div class="bg-note-gut flex-1"></div>
        </div>
        <div class="mt-1 flex justify-between text-[11px] text-muted tabular-nums" aria-hidden="true"><span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span></div>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
        <div>
            <label for="rundung_gesamt" class="{{ $label }}">{{ __('Rundung Gesamtschnitt') }} *</label>
            <select id="rundung_gesamt" name="rundung_gesamt" class="{{ $feld }}">
                @foreach(\App\Support\Betrieb::RUNDUNGEN_GESAMT as $r)
                    <option value="{{ $r }}" @selected(abs((float) $wert('rundung_gesamt') - (float) $r) < 0.0001)>{{ $r }}</option>
                @endforeach
            </select>
            @error('rundung_gesamt')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="frist_inaktiv_tage" class="{{ $label }}">{{ __('Ohne neue Note nach (Tage)') }} *</label>
            <input id="frist_inaktiv_tage" name="frist_inaktiv_tage" type="number" required min="7" max="365" value="{{ $wert('frist_inaktiv_tage') }}" class="{{ $feld }} tabular-nums">
            @error('frist_inaktiv_tage')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="frist_lehrende_tage" class="{{ $label }}">{{ __('Lehrende ankündigen (Tage vorher)') }} *</label>
            <input id="frist_lehrende_tage" name="frist_lehrende_tage" type="number" required min="7" max="365" value="{{ $wert('frist_lehrende_tage') }}" class="{{ $feld }} tabular-nums">
            @error('frist_lehrende_tage')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
