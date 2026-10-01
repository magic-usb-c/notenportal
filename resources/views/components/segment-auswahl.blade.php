{{-- Segmentierte Auswahl aus Optionsfeldern (HIG «Segmented controls»). Weitere Attribute (x-model, x-on:change,
     x-bind:disabled) gehen an jedes Optionsfeld; beschriftet über die Zeile <x-einstellung name="…">. --}}
@props(['name', 'optionen', 'wert' => null, 'beschriftung' => null])
<div class="np-segment" role="radiogroup" aria-labelledby="{{ $beschriftung ?? $name.'-bez' }}" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-fehler" @enderror>
    @foreach($optionen as $optionWert => $text)
        <label><input type="radio" name="{{ $name }}" value="{{ $optionWert }}" class="sr-only" @checked((string) $wert === (string) $optionWert) {{ $attributes }}>{{ $text }}</label>
    @endforeach
</div>
