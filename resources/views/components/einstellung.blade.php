{{-- Zeile einer gruppierten Einstellungsliste (macOS Systemeinstellungen): Bezeichnung links, Bedienelement rechts.
     «fuer» verknüpft die Bezeichnung mit einem Feld; ohne «fuer» erhält sie die id «{name}-bez» für aria-labelledby.
     «fehler»: Schlüssel (oder Liste) der Validierungsfehler, Standard «name». --}}
@props(['label', 'name' => null, 'fuer' => null, 'hinweis' => null, 'fehler' => null, 'beutel' => 'default'])
@php
    $bezId = $name ? $name.'-bez' : null;
    $fehlerSchluessel = $fehler ?? $name;
@endphp
<div {{ $attributes->class('px-4 py-3') }}>
    <div class="flex min-h-7 items-center justify-between gap-6">
        <div class="min-w-0">
            @if($fuer)
                <label for="{{ $fuer }}" class="text-sm text-text">{{ $label }}</label>
            @else
                <span @if($bezId) id="{{ $bezId }}" @endif class="text-sm text-text">{{ $label }}</span>
            @endif
            @if($hinweis)
                <p class="mt-0.5 text-xs text-muted">{{ $hinweis }}</p>
            @endif
        </div>
        <div class="flex shrink-0 items-center gap-3">{{ $slot }}</div>
    </div>
    @foreach(array_filter((array) $fehlerSchluessel) as $schluessel)
        @error($schluessel, $beutel)<p class="mt-1.5 text-right text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
    @endforeach
</div>
