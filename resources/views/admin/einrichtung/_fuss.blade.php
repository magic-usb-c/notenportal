{{-- Fusszeile eines Einrichtungsschritts (HIG «Setup assistant»): Zurück links, Standardaktion rechts.
     $knopf: true = «Speichern und weiter», Text = eigener Speichern-Knopf, false = kein Speichern (dann ist der Weiter-Link
     die Standardaktion, ausser $weiterPrimaer = false, weil die Seite selbst eine Primäraktion trägt). --}}
@php
    $schluessel = array_keys(\App\Support\Einrichtung::SCHRITTE);
    $pos = array_search($schritt, $schluessel, true);
    $mitKnopf = ($knopf ?? true) !== false;
    $weiterPrimaer = $weiterPrimaer ?? ! $mitKnopf;
@endphp
<div class="flex items-center gap-3 border-t border-border pt-5">
    @if($pos > 0)
        <a href="{{ route('admin.setup', $schluessel[$pos - 1]) }}" class="np-knopf np-knopf-sekundaer">{{ __('Zurück') }}</a>
    @endif
    <div class="ml-auto flex items-center gap-3">
        @if($pos < count($schluessel) - 1)
            <a href="{{ route('admin.setup', $schluessel[$pos + 1]) }}"
               @class(['np-knopf', 'np-knopf-schlicht' => ! $weiterPrimaer, 'np-knopf-primaer' => $weiterPrimaer])>{{ $weiterText ?? __('Überspringen') }}</a>
        @endif
        @if($mitKnopf)
            <button type="submit" @isset($formular) form="{{ $formular }}" @endisset :disabled="loading" class="np-knopf np-knopf-primaer">{{ is_string($knopf ?? null) ? $knopf : __('Speichern und weiter') }}</button>
        @endif
    </div>
</div>
