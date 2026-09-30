@props([
    'action',
    'method' => 'GET',
    'sucheName' => null,          // null = kein Suchfeld
    'sucheWert' => null,
    'suchePlatzhalter' => __('Suchen…'),
    'zaehler' => null,            // Trefferzahl rechts
    'zaehlerLabel' => __('Treffer'),
    'zurueck' => null,            // Reset-URL, erscheint nur mit aktiveFilter > 0
    'aktiveFilter' => 0,          // Anzahl aktiver Filter gesamt, für mobilen Button und Zurücksetzen
    'aktiveWeitere' => null,      // Anzahl aktiver Filter nur in «Weitere Filter»; null = aktiveFilter
    'weitereLabel' => __('Weitere Filter'),
])
@php($aktiveWeitere ??= $aktiveFilter)
{{--
    Filterleiste über der Tabelle als Symbolleiste ohne Karte (HIG «Toolbars»): Suchkapsel, Pop-up-Menüs, rechts
    Trefferzahl und Zurücksetzen. Sofort wirksam per Alpine, ohne JS per <noscript>-Knopf. «Weitere Filter» als
    Disclosure. Schmal: Suche + Knopf «Filter (n)», der Primär- und Weitere Filter gemeinsam aufklappt.
--}}
<div x-data="{ offen: {{ $aktiveWeitere > 0 ? 'true' : 'false' }} }">
    <form method="{{ $method }}" action="{{ $action }}" class="flex flex-col gap-2">
        {{ $hidden ?? '' }}

        <div class="flex flex-col gap-2 md:flex-row md:flex-wrap md:items-center">
            @if($sucheName)
                <x-suchfeld :name="$sucheName" :id="$sucheName" :value="$sucheWert" :platzhalter="$suchePlatzhalter" :label="$suchePlatzhalter"
                            x-on:input.debounce.400ms="$el.form.requestSubmit()" class="min-w-0 md:w-72" />
            @endif

            <div :class="offen ? 'flex' : 'hidden md:flex'" class="flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                {{ $slot }}
            </div>

            @isset($weitere)
                <button type="button" @click="offen = ! offen" :aria-expanded="offen"
                        class="np-knopf np-knopf-schlicht shrink-0 max-md:hidden">
                    {{ $weitereLabel }}
                    @if($aktiveWeitere > 0)
                        <span class="np-marke h-4 min-w-4 justify-center bg-accent px-1 text-accent-contrast">{{ $aktiveWeitere }}</span>
                    @endif
                    <x-symbol name="chevron-down" strich="2" class="transition-transform duration-200" ::class="offen && 'rotate-180'" />
                </button>
            @endisset

            <button type="button" @click="offen = ! offen" :aria-expanded="offen"
                    class="np-knopf np-knopf-sekundaer shrink-0 self-start md:hidden">
                <x-symbol name="funnel" />{{ __('Filter') }}
                @if($aktiveFilter > 0)
                    <span class="np-marke h-4 min-w-4 justify-center bg-accent px-1 text-accent-contrast">{{ $aktiveFilter }}</span>
                @endif
            </button>

            <div class="flex shrink-0 items-center gap-3 md:ml-auto">
                @if($zurueck && $aktiveFilter > 0)
                    <a href="{{ $zurueck }}" class="np-knopf np-knopf-schlicht">{{ __('Zurücksetzen') }}</a>
                @endif
                @if($zaehler !== null)
                    <span class="whitespace-nowrap text-sm tabular-nums text-muted">{{ $zaehler }} {{ $zaehlerLabel }}</span>
                @endif
                {{ $export ?? '' }}
                <noscript>
                    <button type="submit" class="np-knopf np-knopf-sekundaer">{{ __('Filtern') }}</button>
                </noscript>
            </div>
        </div>

        @isset($weitere)
            {{-- kein x-cloak: ohne JS bleibt der Block sichtbar (bzw. bei aktiven Weitere-Filtern serverseitig offen) --}}
            <div x-show="offen" x-transition.opacity.duration.150ms
                 class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
                {{ $weitere }}
            </div>
        @endisset
    </form>
</div>
