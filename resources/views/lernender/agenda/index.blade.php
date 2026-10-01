<x-app-layout>
    <x-slot name="title">{{ __('Agenda') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Agenda') }}">
            <nav class="np-segment" aria-label="{{ __('Ansicht') }}">
                <a href="{{ route('learner.exams.index', array_filter(['ansicht' => null, 'lektionen' => $zeigeLektionen ? 1 : null])) }}"
                   @if($ansicht === 'liste') aria-current="page" @endif>{{ __('Liste') }}</a>
                <a href="{{ route('learner.exams.index', array_filter(['ansicht' => 'monat', 'monat' => $monat->format('Y-m'), 'lektionen' => $zeigeLektionen ? 1 : null])) }}"
                   @if($ansicht === 'monat') aria-current="page" @endif>{{ __('Monat') }}</a>
            </nav>
            <x-slot:aktionen>
                <a href="{{ route('settings.calendar') }}" class="np-knopf np-knopf-sekundaer">{{ __('Kalender-Abo') }}</a>
                <a href="{{ route('learner.grades.calculator') }}" class="np-knopf np-knopf-sekundaer">{{ __('Was brauche ich?') }}</a>
                <a href="{{ route('learner.exams.index') }}?planen=1" @unless($bearbeiten) x-data @click.prevent="$dispatch('open-drawer', 'pruefung')" @endunless
                   class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Prüfung planen') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        // Liste in Lesebreite mit Mini-Monat daneben (wie die Seitenleiste in Apple Kalender); das Monatsraster nutzt das ganze Fenster.
        $breite = $ansicht === 'liste' ? 'xl:max-w-[calc(64rem+19rem+2rem)]' : '';
        $monatsLink = fn ($m) => route('learner.exams.index', array_filter(['ansicht' => 'monat', 'monat' => $m?->format('Y-m'), 'lektionen' => $zeigeLektionen ? 1 : null]));
        // Daten eines Tages für die Tagesansicht im Drawer
        $tagDaten = fn (array $tag) => [
            'datum' => \App\Support\Format::datum($tag['datum'], 'l, j. F Y'),
            'eintraege' => $tag['eintraege']->map(fn ($e) => [
                'titel' => $e['titel'], 'zeit' => $e['zeit'], 'nebentext' => $e['nebentext'], 'punkt' => \App\Support\AgendaArt::punkt($e['art']),
                'href' => $e['art'] === 'pruefung' ? route('learner.exams.index', ['bearbeiten' => $e['pruefung']->pruefung_id]) : null,
            ])->values(),
        ];
        $tagLabel = fn (array $tag) => \App\Support\Format::datum($tag['datum'], 'l, j. F Y')
            .($tag['eintraege']->isNotEmpty() ? ' – '.($tag['eintraege']->count() === 1 ? __('1 Eintrag') : __(':anzahl Einträge', ['anzahl' => $tag['eintraege']->count()])) : '');
    @endphp

    <div class="py-6" x-data="{ tagAusgewaehlt: null }">
        <div class="np-seite mx-auto flex flex-col gap-6 px-4 sm:px-6 lg:px-8">

            {{-- Legende und Stundenplan-Schalter als Symbolleiste über dem Inhalt --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 {{ $breite }}">
                @foreach(\App\Support\AgendaArt::ARTEN as $art)
                    @continue($art === 'lektion' && ! $zeigeLektionen)
                    <span class="inline-flex items-center gap-1.5 text-xs text-muted">
                        @include('lernender.agenda._punkt', ['art' => $art]){{ \App\Support\AgendaArt::label($art) }}
                    </span>
                @endforeach
                <form method="GET" action="{{ route('learner.exams.index') }}" class="ml-auto flex items-center gap-2.5">
                    @if($ansicht === 'monat')
                        <input type="hidden" name="ansicht" value="monat">
                        <input type="hidden" name="monat" value="{{ $monat->format('Y-m') }}">
                    @endif
                    <label for="lektionen" class="text-sm text-text">{{ __('Stundenplan') }}</label>
                    <input id="lektionen" type="checkbox" name="lektionen" value="1" role="switch" class="np-schalter"
                           @checked($zeigeLektionen) data-sofort>
                    <noscript><button type="submit" class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Anwenden') }}</button></noscript>
                </form>
            </div>

            @if($ansicht === 'liste')
                @php
                    $abschnitte = array_filter([
                        $gruppen['ueberfaellig']->isNotEmpty() ? ['titel' => __('Note fehlt'), 'eintraege' => $gruppen['ueberfaellig'], 'faellig' => true, 'immer' => false] : null,
                        ['titel' => __('Diese Woche'), 'eintraege' => $gruppen['diese_woche'], 'faellig' => false, 'immer' => true],
                        $gruppen['naechste_woche']['eintraege']->isNotEmpty() ? ['titel' => $gruppen['naechste_woche']['label'], 'eintraege' => $gruppen['naechste_woche']['eintraege'], 'faellig' => false, 'immer' => false] : null,
                        $gruppen['spaeter']->isNotEmpty() ? ['titel' => __('Später'), 'eintraege' => $gruppen['spaeter'], 'faellig' => false, 'immer' => false] : null,
                    ]);
                @endphp
                <div class="grid items-start gap-8 xl:grid-cols-[minmax(0,64rem)_19rem]">
                <div class="flex min-w-0 flex-col gap-8">
                    @foreach($abschnitte as $abschnitt)
                        {{-- Gruppierte Liste (HIG «Lists and tables», Stil «inset grouped»): Überschrift über der Fläche --}}
                        <section class="flex flex-col gap-2">
                            <h2 @class(['flex items-baseline gap-2 px-1 text-sm font-semibold', 'text-note-knapp' => $abschnitt['faellig'], 'text-text' => ! $abschnitt['faellig']])>
                                {{ $abschnitt['titel'] }}
                                <span class="text-xs font-normal tabular-nums text-muted">{{ $abschnitt['eintraege']->count() }}</span>
                            </h2>
                            <div class="np-karte divide-y divide-border overflow-hidden">
                                @forelse($abschnitt['eintraege'] as $e)
                                    @include('lernender.agenda._eintrag', ['e' => $e, 'faellig' => $abschnitt['faellig']])
                                @empty
                                    <p class="px-4 py-6 text-center text-sm text-muted">{{ __('Nichts geplant') }}</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>

                <aside class="np-karte hidden p-4 xl:sticky xl:top-20 xl:block" aria-label="{{ __('Monatsübersicht') }}">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold text-text">{{ \App\Support\Format::datum($monat, 'F Y') }}</h2>
                        <a href="{{ $monatsLink(null) }}" class="-mr-1.5 inline-flex h-7 items-center gap-0.5 rounded-full pl-2.5 pr-1.5 text-sm text-accent-text transition-colors duration-100 hover:bg-accent/10">{{ __('Monat') }}<x-symbol name="chevron-right" strich="2" class="size-3.5" /></a>
                    </div>
                    <div class="grid grid-cols-7 text-center">
                        @foreach(range(0, 6) as $i)
                            <div class="py-1 text-2xs font-medium text-muted">{{ \App\Support\Format::date(now()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays($i), 'dd') }}</div>
                        @endforeach
                        @foreach($monatsraster as $tag)
                            @php
                                $arten = $tag['eintraege']->pluck('art')->map(fn ($a) => in_array($a, ['pruefung', 'erkannt'], true) ? 'bg-accent' : 'bg-muted')->unique()->take(3);
                            @endphp
                            <button type="button" aria-label="{{ $tagLabel($tag) }}"
                                    @click="tagAusgewaehlt = @js($tagDaten($tag)); $dispatch('open-drawer', 'tag')"
                                    class="flex h-10 flex-col items-center justify-center gap-0.5 rounded-lg transition-colors duration-100 hover:bg-surface-2/60 focus-visible:outline-2 focus-visible:outline-ring">
                                <span @class(['inline-flex size-6 items-center justify-center rounded-full text-xs tabular-nums',
                                              'bg-accent font-semibold text-accent-contrast' => $tag['heute'],
                                              'text-text' => ! $tag['heute'] && $tag['imMonat'],
                                              'text-muted' => ! $tag['heute'] && ! $tag['imMonat']])>{{ $tag['datum']->format('j') }}</span>
                                <span class="flex h-1 gap-0.5" aria-hidden="true">
                                    @foreach($arten as $farbe)<span class="size-1 rounded-full {{ $farbe }}"></span>@endforeach
                                </span>
                            </button>
                        @endforeach
                    </div>
                </aside>
                </div>
            @else
                {{-- Monat wie in Apple Kalender: Monatsname links, Blättern und «Heute» rechts, Raster mit Haarlinien --}}
                @php
                    $imAktuellenMonat = $monat->isSameMonth(now());
                @endphp
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-xl font-semibold text-text">{{ \App\Support\Format::datum($monat, 'F Y') }}</h2>
                    <div class="flex items-center gap-1">
                        <a href="{{ $monatsLink($monat->subMonth()) }}" class="np-knopf np-knopf-symbol" aria-label="{{ __('Vorheriger Monat') }}" title="{{ __('Vorheriger Monat') }}"><x-symbol name="chevron-left" strich="2" /></a>
                        <a href="{{ $monatsLink(null) }}" @class(['np-knopf np-knopf-sekundaer np-knopf-klein', 'pointer-events-none opacity-50' => $imAktuellenMonat]) @if($imAktuellenMonat) aria-disabled="true" tabindex="-1" @endif>{{ __('Heute') }}</a>
                        <a href="{{ $monatsLink($monat->addMonth()) }}" class="np-knopf np-knopf-symbol" aria-label="{{ __('Nächster Monat') }}" title="{{ __('Nächster Monat') }}"><x-symbol name="chevron-right" strich="2" /></a>
                    </div>
                </div>

                <div class="np-karte overflow-hidden">
                    <div class="grid grid-cols-7 border-b border-border">
                        @foreach(range(0, 6) as $i)
                            <div class="px-2.5 py-2 text-right text-xs font-medium text-muted">{{ \App\Support\Format::date(now()->startOfWeek(\Carbon\CarbonInterface::MONDAY)->addDays($i), 'ddd') }}</div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-7 *:border-b *:border-l *:border-border [&>*:nth-child(7n+1)]:border-l-0 [&>*:nth-last-child(-n+7)]:border-b-0">
                        @foreach($monatsraster as $tag)
                            @php
                                $chips = $tag['eintraege']->take(4);
                                $mehr = $tag['eintraege']->count() - $chips->count();
                            @endphp
                            <button type="button" aria-label="{{ $tagLabel($tag) }}"
                                    @click="tagAusgewaehlt = @js($tagDaten($tag)); $dispatch('open-drawer', 'tag')"
                                    @class(['flex min-h-[max(7rem,12vh)] flex-col gap-1 p-1.5 text-left transition-colors duration-100 hover:bg-surface-2/60 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring',
                                            'bg-fill-2' => ! $tag['imMonat']])>
                                <span @class(['inline-flex size-6 items-center justify-center self-end rounded-full text-sm tabular-nums',
                                              'bg-accent font-semibold text-accent-contrast' => $tag['heute'],
                                              'text-text' => ! $tag['heute'] && $tag['imMonat'],
                                              'text-muted' => ! $tag['heute'] && ! $tag['imMonat']])>{{ $tag['datum']->format('j') }}</span>
                                @foreach($chips as $c)
                                    <span class="flex min-w-0 items-center gap-1.5 px-0.5 text-xs">
                                        @include('lernender.agenda._punkt', ['art' => $c['art']])
                                        <span @class(['truncate', 'text-text' => in_array($c['art'], ['pruefung', 'erkannt'], true), 'text-muted' => ! in_array($c['art'], ['pruefung', 'erkannt'], true)])>{{ $c['titel'] }}</span>
                                        @if($c['zeit'])<span class="ml-auto shrink-0 tabular-nums text-muted">{{ $c['zeit'] }}</span>@endif
                                    </span>
                                @endforeach
                                @if($mehr > 0)
                                    <span class="px-0.5 text-xs text-muted">{{ __(':anzahl weitere', ['anzahl' => $mehr]) }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <x-drawer name="pruefung" :offen="$bearbeiten || request()->has('planen') || old('_drawer') === 'pruefung'" :titel="$bearbeiten ? __('Prüfung bearbeiten') : __('Prüfung planen')">
            @include('lernender.agenda._form')
        </x-drawer>

        <x-drawer name="tag" :titel="__('Tag')">
            <template x-if="tagAusgewaehlt">
                <div class="flex flex-col gap-3">
                    <h3 class="text-base font-semibold text-text" x-text="tagAusgewaehlt?.datum"></h3>
                    <template x-if="tagAusgewaehlt && tagAusgewaehlt.eintraege.length === 0">
                        <p class="text-sm text-muted">{{ __('Nichts geplant.') }}</p>
                    </template>
                    <div class="np-karte divide-y divide-border overflow-hidden" x-show="tagAusgewaehlt && tagAusgewaehlt.eintraege.length > 0">
                        <template x-for="e in (tagAusgewaehlt ? tagAusgewaehlt.eintraege : [])" :key="e.titel + e.zeit">
                            <a :href="e.href ?? '#'" :tabindex="e.href ? null : -1" class="flex items-center gap-3 px-3.5 py-2.5"
                               :class="e.href ? 'transition-colors duration-100 hover:bg-surface-2/60' : 'pointer-events-none'">
                                <span class="size-2 shrink-0 rounded-full" :class="e.punkt" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-text" x-text="e.titel"></span>
                                    <span class="block truncate text-xs text-muted" x-text="(e.zeit ? e.zeit + ' · ' : '') + e.nebentext"></span>
                                </span>
                                <template x-if="e.href"><x-symbol name="chevron-right" strich="2" class="size-3.5 shrink-0 text-muted" /></template>
                            </a>
                        </template>
                    </div>
                </div>
            </template>
        </x-drawer>
    </div>
</x-app-layout>
