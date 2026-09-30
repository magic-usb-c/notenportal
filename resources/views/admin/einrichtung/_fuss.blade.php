@php
    $schluessel = array_keys(\App\Support\Einrichtung::SCHRITTE);
    $pos = array_search($schritt, $schluessel, true);
@endphp
<div class="flex flex-wrap items-center justify-between gap-3">
    @if($pos > 0)
        <a href="{{ route('admin.setup', $schluessel[$pos - 1]) }}" class="np-knopf np-knopf-sekundaer np-knopf-gross">{{ __('← Zurück') }}</a>
    @else
        <span></span>
    @endif
    <div class="flex items-center gap-2">
        @if($pos < count($schluessel) - 1)
            <a href="{{ route('admin.setup', $schluessel[$pos + 1]) }}" class="inline-flex items-center px-4 h-10 rounded-xl text-sm text-muted hover:text-text">{{ $weiterText ?? __('Überspringen') }}</a>
        @endif
        @if($knopf ?? true)
            <button type="submit" :disabled="loading"
                    class="np-knopf np-knopf-primaer np-knopf-gross">{{ $knopf ?? __('Speichern und weiter') }}</button>
        @endif
    </div>
</div>
