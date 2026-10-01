@props([
    'id',
    'name' => 'gewichtung_prozent',
    'model' => null,   // Alpine-Variable; ohne sie ein schlichtes Feld mit `wert`
    'wert' => null,
    'stufen' => [25, 50, 100],
])

{{-- Gewichtung in Prozent: Zahl mit «%» im Feld, daneben die häufigen Werte als Segment (wie macOS «Segmented Control») --}}
<div class="mt-1.5 flex items-center gap-3">
    <div class="relative w-28 shrink-0">
        <input type="number" id="{{ $id }}" name="{{ $name }}" min="0" max="100" step="0.01" inputmode="decimal"
               @if($model) x-model="{{ $model }}" @else value="{{ $wert }}" @endif
               {{ $attributes->class('np-feld pr-7! text-right tabular-nums') }}>
        <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted" aria-hidden="true">%</span>
    </div>
    @if($model && $stufen)
        <div class="np-segment" role="group" aria-label="{{ __('Häufige Gewichtungen') }}">
            @foreach($stufen as $g)
                <button type="button" @click="{{ $model }} = '{{ $g }}'" :aria-pressed="parseFloat({{ $model }}) === {{ $g }} ? 'true' : 'false'">{{ $g }} %</button>
            @endforeach
        </div>
    @endif
</div>
