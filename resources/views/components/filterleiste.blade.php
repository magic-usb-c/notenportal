@props([
    'action',
    'method' => 'GET',
    'sucheName' => null,          // null = kein Suchfeld
    'sucheWert' => null,
    'suchePlatzhalter' => __('Suchen…'),
    'zaehler' => null,            // Trefferzahl rechts
    'zaehlerLabel' => __('Treffer'),
    'zurueck' => null,            // Reset-URL, erscheint nur mit aktiveFilter > 0
    'aktiveFilter' => 0,          // Anzahl aktiver Filter, blendet «Zurücksetzen» ein
])
{{--
    Filterleiste über der Tabelle als Symbolleiste ohne Karte (HIG «Toolbars», «Search fields»): Suchkapsel und
    Pop-up-Menüs in einer Zeile, rechts Zurücksetzen und Trefferzahl. Jede Änderung gilt sofort (Alpine), ohne JS
    per <noscript>-Knopf. Der Slot «weitere» folgt nach einem Trenner – seltener gebrauchte Filter.
--}}
<form method="{{ $method }}" action="{{ $action }}" class="flex flex-wrap items-center gap-2">
    {{ $hidden ?? '' }}

    @if($sucheName)
        <x-suchfeld :name="$sucheName" :id="$sucheName" :value="$sucheWert" :platzhalter="$suchePlatzhalter" :label="$suchePlatzhalter"
                    x-on:input.debounce.400ms="$el.form.requestSubmit()" class="w-72" />
    @endif

    {{ $slot }}

    @isset($weitere)
        <span class="mx-1 h-5 w-px bg-border" aria-hidden="true"></span>
        {{ $weitere }}
    @endisset

    <div class="ml-auto flex shrink-0 items-center gap-3">
        @if($zurueck && $aktiveFilter > 0)
            <a href="{{ $zurueck }}" class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Zurücksetzen') }}</a>
        @endif
        @if($zaehler !== null)
            <span class="whitespace-nowrap text-sm tabular-nums text-muted">{{ $zaehler }} {{ $zaehlerLabel }}</span>
        @endif
        {{ $export ?? '' }}
        <noscript>
            <button type="submit" class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Filtern') }}</button>
        </noscript>
    </div>
</form>
