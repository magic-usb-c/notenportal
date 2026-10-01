<x-app-layout>
    <x-slot name="title">{{ $modul ? $modul->modul_nummer.' '.$modul->titel : __('Module') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Module')" :zaehler="$module->count()">
            <x-slot:aktionen>
                <a href="{{ route('modules.create') }}" class="np-knopf np-knopf-sekundaer"><x-symbol name="plus" strich="2" />{{ __('Modul anlegen') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        {{-- Wie Notizen: links die Quellliste aller Module (filtert beim Tippen), rechts das gewählte Modul --}}
        {{-- Höhe der Liste: Fenster minus Symbolleiste minus Platz darüber (Seitenkopf, Seitenrand) und darunter, damit sie bei oberster Scrollposition ganz im Fenster endet --}}
        <div class="np-seite mx-auto grid grid-cols-[22rem_minmax(0,1fr)] items-start gap-8 px-8">
            <div class="np-karte sticky top-[calc(var(--np-symbolleiste-hoehe)+1rem)] flex max-h-[calc(100dvh-var(--np-symbolleiste-hoehe)-8rem)] flex-col"
                 x-data="npQuellliste({ name: 'module', suche: @js($suche) })">
                <form method="GET" action="{{ route('modules.index') }}" role="search" class="p-3 pb-2" @submit.prevent="ersten()">
                    <x-suchfeld name="suche" :value="$suche" :platzhalter="__('Nummer oder Titel')" :label="__('Module suchen')" autocomplete="off"
                                x-model="q" x-on:input="filtern()" x-on:keydown.arrow-down.prevent="bewegen(1)" x-on:keydown.escape="leeren($event)" class="w-full" />
                </form>
                <nav x-ref="liste" class="np-scroll-edge min-h-0 overflow-y-auto px-1.5 pb-2 pt-0" aria-label="{{ __('Module') }}"
                     @click="oeffnen($event)" @keydown.arrow-down.prevent="bewegen(1)" @keydown.arrow-up.prevent="bewegen(-1)">
                    <div class="flex flex-col gap-px">
                        @php
                            // Lernende sehen ihre eigenen Module zuoberst, wie eine eigene Gruppe in der Seitenleiste
                            $gruppen = $belegte->isEmpty() ? [[null, $module]] : [
                                [__('Deine Module'), $module->filter(fn ($m) => $belegte->contains($m->modul_id))],
                                [__('Weitere Module'), $module->reject(fn ($m) => $belegte->contains($m->modul_id))],
                            ];
                        @endphp
                        @foreach($gruppen as [$gruppe, $eintraege])
                            @continue($eintraege->isEmpty())
                            <div data-gruppe class="flex flex-col gap-px">
                                @if($gruppe)<h2 class="px-2.5 pb-1 pt-3 text-2xs font-semibold text-muted">{{ $gruppe }}</h2>@endif
                                @foreach($eintraege as $m)
                                    <a href="{{ route('modules.show', $m->modul_id) }}" data-suchtext="{{ mb_strtolower($m->modul_nummer.' '.$m->titel) }}"
                                       @if($modul?->modul_id === $m->modul_id) aria-current="page" @endif class="np-leistenzeile">
                                        <span class="min-w-12 shrink-0 font-medium tabular-nums">{{ $m->modul_nummer }}</span>
                                        <span @class(['min-w-0 flex-1 truncate', 'text-muted' => ! $m->aktiv])>{{ $m->titel }}</span>
                                        @unless($m->aktiv)<span class="sr-only">({{ __('Inaktiv') }})</span>@endunless
                                        @if($m->dokumente_count > 0)
                                            <span class="flex shrink-0 items-center gap-0.5 text-xs tabular-nums text-muted" title="{{ __('Unterlagen') }}">
                                                <x-symbol name="paper-clip" class="size-3.5" />{{ $m->dokumente_count }}<span class="sr-only"> {{ __('Unterlagen') }}</span>
                                            </span>
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        @endforeach
                        <p x-show="treffer === 0" x-cloak class="px-2.5 py-6 text-center text-sm text-muted">
                            {{ $module->isEmpty() ? __('Noch keine Module erfasst.') : __('Kein Modul gefunden. Leg es an, dann sehen es alle.') }}
                        </p>
                    </div>
                </nav>
            </div>

            <div class="min-w-0">
                @if($modul)
                    @include('module._detail')
                @else
                    <p class="np-karte flex items-center gap-3 px-5 py-4 text-sm text-muted">
                        {{ __('Noch keine Module erfasst.') }}
                        <a href="{{ route('modules.create') }}" class="inline-flex min-h-6 items-center text-accent-text underline-offset-2 hover:underline">{{ __('Modul anlegen') }}</a>
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
