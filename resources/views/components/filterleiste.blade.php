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
    Filterleiste (Katalog e): einzeilig über der Tabelle, sofort wirksam per Alpine (kein
    «Filtern»-Button), No-JS-Fallback per <noscript>-Button. «Weitere Filter» als Disclosure.
    Mobil: Suche + Button «Filter (n)», der Primär- und Weitere Filter gemeinsam aufklappt.
--}}
<div x-data="{ offen: {{ $aktiveWeitere > 0 ? 'true' : 'false' }} }" class="rounded-xl border border-border bg-card p-3">
    <form method="{{ $method }}" action="{{ $action }}" class="flex flex-col gap-3">
        {{ $hidden ?? '' }}

        <div class="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-center">
            @if($sucheName)
                <div class="relative min-w-0 md:w-56">
                    <svg class="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
                    </svg>
                    <label for="{{ $sucheName }}" class="sr-only">{{ $suchePlatzhalter }}</label>
                    <input type="search" name="{{ $sucheName }}" id="{{ $sucheName }}" value="{{ $sucheWert }}"
                           placeholder="{{ $suchePlatzhalter }}" x-on:input.debounce.400ms="$el.form.requestSubmit()"
                           class="h-9 w-full rounded-lg border border-border-strong/70 bg-input pl-8 pr-3 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30">
                </div>
            @endif

            <div :class="offen ? 'flex' : 'hidden md:flex'" class="flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center md:gap-2">
                {{ $slot }}
            </div>

            @isset($weitere)
                <button type="button" @click="offen = ! offen" :aria-expanded="offen"
                        class="hidden h-9 shrink-0 items-center gap-1.5 rounded-lg px-2.5 text-sm text-muted hover:bg-surface-2 hover:text-text md:inline-flex">
                    {{ $weitereLabel }}
                    @if($aktiveWeitere > 0)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-accent-contrast">{{ $aktiveWeitere }}</span>
                    @endif
                    <svg class="size-3.5 transition-transform duration-200" :class="offen && 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
            @endisset

            <button type="button" @click="offen = ! offen" :aria-expanded="offen"
                    class="inline-flex h-9 shrink-0 items-center gap-1.5 rounded-lg border border-border-strong/60 bg-card px-3 text-sm font-medium text-text md:hidden">
                {{ __('Filter') }}
                @if($aktiveFilter > 0)
                    <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-bold text-accent-contrast">{{ $aktiveFilter }}</span>
                @endif
            </button>

            <div class="flex shrink-0 items-center gap-3 md:ml-auto">
                @if($zurueck && $aktiveFilter > 0)
                    <a href="{{ $zurueck }}" class="whitespace-nowrap text-sm text-accent-text hover:underline underline-offset-2">{{ __('Zurücksetzen') }}</a>
                @endif
                @if($zaehler !== null)
                    <span class="whitespace-nowrap text-sm tabular-nums text-muted">{{ $zaehler }} {{ $zaehlerLabel }}</span>
                @endif
                {{ $export ?? '' }}
                <noscript>
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg border border-border-strong/60 bg-card px-3 text-sm font-medium text-text">{{ __('Filtern') }}</button>
                </noscript>
            </div>
        </div>

        @isset($weitere)
            {{-- kein x-cloak: ohne JS bleibt der Block sichtbar (bzw. bei aktiven Weitere-Filtern serverseitig offen) --}}
            <div x-show="offen" x-transition.opacity.duration.150ms
                 class="flex flex-col gap-3 border-t border-border pt-3 sm:flex-row sm:flex-wrap sm:items-center sm:gap-2">
                {{ $weitere }}
            </div>
        @endisset
    </form>
</div>
