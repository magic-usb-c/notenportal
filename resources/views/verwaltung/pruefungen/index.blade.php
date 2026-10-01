<x-app-layout>
    <x-slot name="title">{{ __('Prüfungstermine') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Prüfungstermine')" :zaehler="$anzahl">
            <nav class="np-segment" aria-label="{{ __('Zeitraum') }}">
                @foreach(['7' => 7, '30' => 30, 'alle' => 90] as $wert => $tage)
                    <a href="{{ route($bereich.'.exams.index', array_filter(['lernender_id' => $filter['lernender_id'], 'zeitraum' => $wert === 'alle' ? null : $wert])) }}"
                       @if($filter['zeitraum'] === $wert) aria-current="page" @endif>{{ __(':anzahl Tage', ['anzahl' => $tage]) }}</a>
                @endforeach
            </nav>
            <x-slot:aktionen>
                <a href="{{ route('settings.calendar') }}" class="np-knopf np-knopf-sekundaer">{{ __('Kalender-Abo') }}</a>
                @if($abgabeMoeglich)
                    <a href="{{ route($bereich.'.exams.index', array_merge($filter, ['planen' => 1])) }}" @unless($bearbeiten) x-data @click.prevent="$dispatch('open-drawer', 'abgabetermin')" @endunless
                       class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Abgabetermin') }}</a>
                @elseif(\App\Models\Pruefung::hatArtSpalte() && $lernende->isNotEmpty())
                    {{-- Ohne gewählte Person fragt das Menü zuerst, für wen (Fach/Modul hängen von Lehrberuf und Track ab) --}}
                    <x-dropdown align="right" width="56" content-classes="max-h-[min(24rem,70dvh)] overflow-y-auto p-1 text-text">
                        <x-slot name="trigger">
                            <button type="button" aria-haspopup="menu" class="np-knopf np-knopf-primaer">
                                <x-symbol name="plus" strich="2" />{{ __('Abgabetermin') }}<x-symbol name="chevron-down" strich="2" class="size-3.5" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <p class="px-2.5 pb-1 pt-1.5 text-xs text-muted">{{ __('Für wen?') }}</p>
                            @foreach($lernende as $eintrag)
                                @php $b = $eintrag['lernender']->benutzer; @endphp
                                <a href="{{ route($bereich.'.exams.index', array_filter(['lernender_id' => (int) $eintrag['lernender']->lernender_id, 'zeitraum' => $filter['zeitraum'] === 'alle' ? null : $filter['zeitraum'], 'planen' => 1])) }}"
                                   class="np-menue-eintrag">{{ $b->vorname }} {{ $b->nachname }}</a>
                            @endforeach
                        </x-slot>
                    </x-dropdown>
                @endif
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $einLernender = $filter['lernender_id'] !== null;
        $filterLink = fn (?int $id) => route($bereich.'.exams.index', array_filter(['lernender_id' => $id, 'zeitraum' => $filter['zeitraum'] === 'alle' ? null : $filter['zeitraum']]));
    @endphp

    <div class="py-6">
        {{-- Termine als gruppierte Liste, daneben die Lernenden wie die Kalenderliste in Apple Kalender: Auswahl und Zähler --}}
        <div class="np-seite mx-auto grid grid-cols-[minmax(0,1fr)_20rem] items-start gap-8 px-8">
            <div class="flex min-w-0 flex-col gap-8">
                @forelse($gruppen as $g)
                    <section class="flex flex-col gap-2">
                        <h2 @class(['flex items-baseline gap-2 px-1 text-sm font-semibold', 'text-note-knapp' => $g['fehlt'], 'text-text' => ! $g['fehlt']])>
                            {{ $g['label'] }}
                            <span class="text-xs font-normal tabular-nums text-muted">{{ $g['zeilen']->count() }}</span>
                        </h2>
                        <div class="np-karte overflow-hidden">
                            @foreach($g['zeilen'] as $i => $z)
                                @include('verwaltung.pruefungen._zeile', ['ersteZeile' => $i === 0, 'neuerTag' => $i === 0 || ! $g['zeilen'][$i - 1]['datum']->equalTo($z['datum'])])
                            @endforeach
                        </div>
                    </section>
                @empty
                    <p class="np-karte px-5 py-10 text-center text-sm text-muted">{{ __('Keine Prüfungstermine für die aktuelle Auswahl.') }}</p>
                @endforelse
            </div>

            <aside class="np-karte sticky top-[calc(var(--np-symbolleiste-hoehe)+1rem)] flex max-h-[calc(100dvh-var(--np-symbolleiste-hoehe)-2rem)] flex-col" aria-labelledby="lernende-titel">
                <h2 id="lernende-titel" class="px-4 pb-1 pt-3.5 text-sm font-semibold text-text">{{ __('Lernende') }}</h2>
                <nav class="flex flex-col gap-px overflow-y-auto p-1.5 pt-0" aria-labelledby="lernende-titel">
                    <a href="{{ $filterLink(null) }}" @unless($einLernender) aria-current="page" @endunless class="np-leistenzeile">
                        <span class="inline-flex size-7 shrink-0 items-center justify-center rounded-full bg-fill text-muted" aria-hidden="true"><x-symbol name="users" class="size-4" /></span>
                        <span class="min-w-0 flex-1 truncate">{{ __('Alle Lernenden') }}</span>
                        @include('verwaltung.pruefungen._zaehler', ['anzahl' => $anzahlAlle, 'fehlt' => $fehltAlle])
                    </a>
                    @foreach($lernende as $eintrag)
                        @php $b = $eintrag['lernender']->benutzer; @endphp
                        <a href="{{ $filterLink((int) $eintrag['lernender']->lernender_id) }}" @if($filter['lernender_id'] === (int) $eintrag['lernender']->lernender_id) aria-current="page" @endif class="np-leistenzeile">
                            <span class="np-monogramm size-7 shrink-0 text-2xs" aria-hidden="true">{{ mb_substr($b->vorname, 0, 1) }}{{ mb_substr($b->nachname, 0, 1) }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ $b->vorname }} {{ $b->nachname }}</span>
                            @include('verwaltung.pruefungen._zaehler', ['anzahl' => $eintrag['anzahl'], 'fehlt' => $eintrag['fehlt']])
                        </a>
                    @endforeach
                </nav>
            </aside>
        </div>
    </div>

    @if($abgabeMoeglich)
        <x-drawer name="abgabetermin" :offen="$bearbeiten || request()->has('planen') || old('_drawer') === 'abgabetermin'" :titel="$bearbeiten ? __('Abgabetermin bearbeiten') : __('Abgabetermin erfassen')">
            @include('verwaltung.pruefungen._abgabe_form')
        </x-drawer>
    @endif
</x-app-layout>
