{{-- Segmentierte Auswahl aus Optionsfeldern (HIG «Segmented controls»). Weitere Attribute (x-model, x-on:change,
     x-bind:disabled) gehen an jedes Optionsfeld; beschriftet über die Zeile <x-einstellung name="…">.
     Die Auswahlmarke (np-segment-marke, B6) gleitet zum gewählten Segment. Das Skript legt sie erst an, wenn ihre Lage
     gemessen ist (--np-segment-x, --np-segment-breite in px): ohne Skript trägt das gewählte Segment seine Fläche selbst
     (die CSS blendet sie erst aus, wenn eine Marke da ist), und die Marke springt beim ersten Zeichnen nicht herein. --}}
@props(['name', 'optionen', 'wert' => null, 'beschriftung' => null])
<div class="np-segment" role="radiogroup" aria-labelledby="{{ $beschriftung ?? $name.'-bez' }}" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-fehler" @enderror
     x-data="{
         messen() {
             const gewaehlt = this.$root.querySelector('input:checked')?.closest('label');
             if (! gewaehlt || ! gewaehlt.offsetWidth) return;
             this.$root.style.setProperty('--np-segment-x', gewaehlt.offsetLeft + 'px');
             this.$root.style.setProperty('--np-segment-breite', gewaehlt.offsetWidth + 'px');
             if (this.$root.querySelector(':scope > .np-segment-marke')) return;
             const marke = document.createElement('span');
             marke.className = 'np-segment-marke';
             marke.setAttribute('aria-hidden', 'true');
             this.$root.prepend(marke);
         },
     }"
     x-init="messen(); new ResizeObserver(() => messen()).observe($root); document.fonts?.ready.then(() => messen())"
     @change="$nextTick(() => messen())">
    @foreach($optionen as $optionWert => $text)
        <label><input type="radio" name="{{ $name }}" value="{{ $optionWert }}" class="sr-only" @checked((string) $wert === (string) $optionWert) {{ $attributes }}>{{ $text }}</label>
    @endforeach
</div>
