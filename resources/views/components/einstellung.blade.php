{{-- Zeile einer gruppierten Einstellungsliste (macOS Systemeinstellungen): Bezeichnung links, Bedienelement rechts.
     «fuer» verknüpft die Bezeichnung mit einem Feld; ohne «fuer» erhält sie die id «{name}-bez» für aria-labelledby.
     «hinweis» trägt die id «{fuer|name}-hinweis» für aria-describedby. «gestapelt»: Bezeichnung über dem Bedienelement
     in voller Breite (mehrzeiliger Text).
     «fehler»: Schlüssel (oder Liste) der Validierungsfehler, Standard «name». Die Meldung trägt die id «{schlüssel}-fehler»,
     das Feld verweist mit @error(…) aria-invalid="true" aria-describedby="{schlüssel}-fehler" @enderror darauf. --}}
@props(['label', 'name' => null, 'fuer' => null, 'hinweis' => null, 'fehler' => null, 'beutel' => 'default', 'gestapelt' => false])
@php
    $bezId = $name ? $name.'-bez' : null;
    $hinweisId = ($fuer ?? $name) ? ($fuer ?? $name).'-hinweis' : null;
    $fehlerSchluessel = $fehler ?? $name;
@endphp
<div {{ $attributes->class('px-4 py-3') }}>
    <div @class(['flex min-h-7 items-center justify-between gap-6' => ! $gestapelt])>
        <div class="min-w-0">
            @if($fuer)
                <label for="{{ $fuer }}" class="text-sm text-text">{{ $label }}</label>
            @else
                <span @if($bezId) id="{{ $bezId }}" @endif class="text-sm text-text">{{ $label }}</span>
            @endif
            @if($hinweis)
                <p @if($hinweisId) id="{{ $hinweisId }}" @endif class="mt-0.5 text-xs text-muted">{{ $hinweis }}</p>
            @endif
        </div>
        <div @class(['flex shrink-0 items-center gap-3' => ! $gestapelt, 'mt-2' => $gestapelt])>{{ $slot }}</div>
    </div>
    @foreach(array_filter((array) $fehlerSchluessel) as $schluessel)
        @error($schluessel, $beutel)<p id="{{ $schluessel }}-fehler" @class(['mt-1.5 text-xs text-note-ungenuegend', 'text-right' => ! $gestapelt])>{{ $message }}</p>@enderror
    @endforeach
</div>
