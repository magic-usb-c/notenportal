<x-app-layout>
    <x-slot name="title">{{ __('Agenda') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Agenda') }}">
            <nav class="np-segment" aria-label="{{ __('Ansicht') }}">
                <a href="{{ route('learner.exams.index', array_filter(['ansicht' => null, 'lektionen' => $zeigeLektionen ? 1 : null])) }}"
                   @if($ansicht === 'liste') aria-current="page" @endif>{{ __('Liste') }}</a>
                <a href="{{ route('learner.exams.index', array_filter(['ansicht' => 'monat', 'monat' => $monat->format('Y-m'), 'lektionen' => $zeigeLektionen ? 1 : null])) }}" data-np-monat-link
                   @if($ansicht === 'monat') aria-current="page" @endif>{{ __('Monat') }}</a>
            </nav>
            <x-slot:aktionen>
                <a href="{{ route('settings.calendar') }}" class="np-knopf np-knopf-sekundaer">{{ __('Kalender-Abo') }}</a>
                <a href="{{ route('learner.exams.index') }}?planen=1" @unless($bearbeiten) x-data @click.prevent="$dispatch('open-drawer', 'pruefung')" @endunless
                   class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Prüfung planen') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        // Rechner-Ziel einer künftigen Prüfung: Fach im Semester ihres Datums bzw. Modul (wie Rechner::vorschlaege)
        $konfiguration = \App\Services\Auswertung\Konfiguration::ausDb();
        $rechnerZiel = function (\App\Models\Pruefung $p) use ($konfiguration): ?string {
            if ($p->modul_id !== null) {
                return 'modul:'.$p->modul_id;
            }
            $semester = $konfiguration->semesterFuerDatum($p->datum->toDateString());

            return $semester !== null ? 'fach:'.$p->fach_id.'@semester:'.$semester : null;
        };
        $monatsLink = fn ($m) => route('learner.exams.index', array_filter(['ansicht' => 'monat', 'monat' => $m?->format('Y-m'), 'lektionen' => $zeigeLektionen ? 1 : null]));
        // Daten eines Tages für die Tagesansicht im Drawer
        // «Prüfung planen» im Tages-Drawer ist ein Link (kein zweiter Drawer über dem ersten) und bleibt in der gewählten Ansicht
        $planenLink = fn (array $tag) => route('learner.exams.index', array_filter([
            'planen' => 1,
            'datum' => $tag['datum']->toDateString(),
            'ansicht' => $ansicht === 'monat' ? 'monat' : null,
            'monat' => $ansicht === 'monat' ? $monat->format('Y-m') : null,
            'lektionen' => $zeigeLektionen ? 1 : null,
        ]));
        $tagDaten = fn (array $tag) => [
            'datum' => \App\Support\Format::datum($tag['datum'], 'l, j. F Y'),
            'planen' => $planenLink($tag),
            'eintraege' => $tag['eintraege']->map(fn ($e) => [
                'titel' => $e['titel'], 'zeit' => $e['zeit'], 'nebentext' => $e['nebentext'], 'punkt' => \App\Support\AgendaArt::punkt($e['art']),
                'href' => $e['art'] === 'pruefung' ? route('learner.exams.index', ['bearbeiten' => $e['pruefung']->pruefung_id]) : null,
            ])->values(),
        ];
        $tagLabel = fn (array $tag) => \App\Support\Format::datum($tag['datum'], 'l, j. F Y')
            .($tag['eintraege']->isNotEmpty() ? ' – '.($tag['eintraege']->count() === 1 ? __('1 Eintrag') : __(':anzahl Einträge', ['anzahl' => $tag['eintraege']->count()])) : '');
    @endphp

    {{-- Monatswechsel ohne Seitenwechsel: der Monat kommt als HTML, die alte Ansicht weicht in Richtung des Blätterns (mitRichtung, B-Katalog); ohne Unterstützung oder bei ruhiger Bewegung wird nur getauscht, bei einem Fehler lädt die Seite normal.
         Mit dem Monat wechseln, was von ihm abhängt: der Block des Stundenplan-Schalters (data-np-agendaschalter) und der Link «Monat» im Seitenkopf. Der Fokus kehrt zum geblätterten Knopf zurück, die Live-Region sagt den neuen Monat an, eine ältere Anfrage wird abgebrochen. Zurück im Verlauf lädt nur neu, wenn sich Pfad oder Query ändern (nicht bei einem Sprunglink). --}}
    <div class="py-6" @popstate.window="beiVerlauf()" x-data="{
        tagAusgewaehlt: null,
        ansicht: @js($ansicht),
        adresse: location.pathname + location.search,
        abbruch: null,
        beiVerlauf() {
            if (this.ansicht === 'monat' && location.pathname + location.search !== this.adresse) location.reload();
        },
        async wechsle(url, richtung) {
            this.abbruch?.abort();
            const abbruch = this.abbruch = new AbortController();
            try {
                const antwort = await fetch(url, { signal: abbruch.signal, credentials: 'same-origin', headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
                if (! antwort.ok) throw new Error('HTTP ' + antwort.status);
                const doc = new DOMParser().parseFromString(await antwort.text(), 'text/html');
                const neu = doc.querySelector('[data-np-monat]');
                const schalter = doc.querySelector('[data-np-agendaschalter]');
                if (! neu || ! schalter || ! document.querySelector('[data-np-monat]') || ! document.querySelector('[data-np-agendaschalter]')) throw new Error('Monat fehlt');
                const tausche = () => {
                    if (abbruch.signal.aborted) return;
                    try {
                        const alt = document.querySelector('[data-np-monat]');
                        const altSchalter = document.querySelector('[data-np-agendaschalter]');
                        if (! alt || ! altSchalter) throw new Error('Monat fehlt');
                        const hatteFokus = alt.contains(document.activeElement);
                        const knopf = hatteFokus ? document.activeElement.dataset.npBlaettern : null;
                        alt.replaceWith(document.adoptNode(neu));
                        altSchalter.replaceWith(document.adoptNode(schalter));
                        const link = document.querySelector('[data-np-monat-link]');
                        const neuerLink = doc.querySelector('[data-np-monat-link]');
                        if (link && neuerLink) link.setAttribute('href', neuerLink.getAttribute('href'));
                        history.pushState(null, '', url);
                        this.adresse = location.pathname + location.search;
                        const ansage = document.querySelector('[data-np-monat-ansage]');
                        if (ansage) ansage.textContent = neu.querySelector('h2')?.textContent ?? '';
                        if (hatteFokus) ((knopf && neu.querySelector(`[data-np-blaettern=${knopf}]:not([tabindex])`)) || neu.querySelector('h2'))?.focus({ preventScroll: true });
                    } catch (e) {
                        location.assign(url);
                    }
                };
                if (window.np && window.np.mitRichtung) window.np.mitRichtung(richtung, tausche); else tausche();
            } catch (e) {
                if (e.name === 'AbortError') return;
                location.assign(url);
            }
        },
    }">
        <p class="sr-only" role="status" aria-live="polite" data-np-monat-ansage></p>
        <div class="np-seite mx-auto flex flex-col gap-6 px-8">

            {{-- Legende und Stundenplan-Schalter als Symbolleiste über dem Inhalt; in der Liste so breit wie Liste und Monat (100rem) --}}
            <div @class(['flex items-center gap-x-5', 'max-w-[100rem]' => $ansicht === 'liste'])>
                @foreach(\App\Support\AgendaArt::ARTEN as $art)
                    @continue($art === 'lektion' && ! $zeigeLektionen)
                    <span class="inline-flex items-center gap-1.5 text-xs text-muted">
                        @include('lernender.agenda._punkt', ['art' => $art]){{ \App\Support\AgendaArt::label($art) }}
                    </span>
                @endforeach
                <form method="GET" action="{{ route('learner.exams.index') }}" class="ml-auto flex items-center gap-2.5">
                    <span class="contents" data-np-agendaschalter>
                        @if($ansicht === 'monat')
                            <input type="hidden" name="ansicht" value="monat">
                            <input type="hidden" name="monat" value="{{ $monat->format('Y-m') }}">
                        @elseif($statistik['filter']['zeitraum'] !== '8w')
                            <input type="hidden" name="zeitraum" value="{{ $statistik['filter']['zeitraum'] }}">
                        @endif
                    </span>
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
                {{-- Liste über die ganze Breite, daneben der Mini-Monat wie die Seitenleiste in Apple Kalender --}}
                <div class="grid grid-cols-[minmax(0,78rem)_20rem] items-start gap-8">
                <div class="flex min-w-0 flex-col gap-8">
                    @php
                        // Nötige Note je geplante Prüfung (S6): Zeitleiste und Zeilen der Liste lesen dieselbe Antwort
                        // Die Zeilen tragen (noch) keine Prüfungs-ID: Zuordnung über Datum und Bezeichnung, mit ID, sobald sie mitkommt
                        $noetig = collect($statistik['diagramm']['zeitleiste']['zeilen'])->filter(fn ($z) => $z['knotenId'] === null)
                            ->keyBy(fn ($z) => isset($z['id']) ? 'id:'.$z['id'] : $z['datum'].'|'.$z['titel']);
                        // Zeitleiste nur, wenn sie im Zeitraum etwas zeichnet: überfällige, Termine und spätere Prüfungen stehen in der Liste
                        $hatPruefungen = $statistik['diagramm']['zeitleiste']['zeilen'] !== [];
                    @endphp
                    @if($hatPruefungen || $statistik['filter']['zeitraum'] !== '8w')
                        {{-- Kommende Prüfungen mit der Note, die für genügend nötig ist; der Zeitraum gilt auch für die Zeilen darunter --}}
                        <div class="flex flex-col gap-3">
                            <x-filterleiste :action="route('learner.exams.index')"
                                            :statistik="['json' => false, 'standard' => ['zeitraum' => '8w'], 'ersetze' => ['[data-np-agendaliste]', '[data-np-agendaschalter]']]">
                                @if($zeigeLektionen)<input type="hidden" name="lektionen" value="1">@endif
                                <label for="zeitraum" class="sr-only">{{ __('Zeitraum') }}</label>
                                <select name="zeitraum" id="zeitraum" class="np-feld np-feld-klein np-auswahl w-auto max-w-56">
                                    @foreach($statistik['meta']['zeitleiste']['optionen']['zeitraum'] as $wert => $text)
                                        <option value="{{ $wert }}" @selected($statistik['filter']['zeitraum'] === $wert)>{{ $text }}</option>
                                    @endforeach
                                </select>
                            </x-filterleiste>
                            <x-zeitleiste :paket="$statistik" />
                        </div>
                    @endif
                    <div class="flex flex-col gap-8" data-np-agendaliste>
                    @foreach($abschnitte as $abschnitt)
                        {{-- Gruppierte Liste (HIG «Lists and tables», Stil «inset grouped»): Überschrift über der Fläche --}}
                        <section class="flex flex-col gap-2">
                            <h2 class="flex items-baseline gap-2 px-1 text-sm font-semibold text-text">
                                {{ $abschnitt['titel'] }}
                                <span class="text-xs font-normal tabular-nums text-muted">{{ $abschnitt['eintraege']->count() }}</span>
                            </h2>
                            <div class="np-karte divide-y divide-border overflow-hidden">
                                @forelse($abschnitt['eintraege'] as $e)
                                    @include('lernender.agenda._eintrag', ['e' => $e, 'faellig' => $abschnitt['faellig'], 'noetig' => $noetig])
                                @empty
                                    <p class="px-4 py-5 text-sm text-muted">{{ __('Nichts geplant') }}</p>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                    </div>
                </div>

                {{-- Gleicher Aufbau wie die Listen: Überschrift über der Fläche, damit die Kartenkanten bündig laufen --}}
                <aside class="sticky top-20 flex flex-col gap-2 self-start" aria-label="{{ __('Monatsübersicht') }}">
                    <div class="flex items-baseline justify-between gap-2 px-1">
                        <h2 class="text-sm font-semibold text-text">{{ \App\Support\Format::datum($monat, 'F Y') }}</h2>
                        <a href="{{ $monatsLink(null) }}" class="-my-1 inline-flex min-h-6 items-center gap-0.5 text-sm text-accent-text">{{ __('Monat') }}<x-symbol name="chevron-right" strich="2" class="size-3.5" /></a>
                    </div>
                    <div class="np-karte p-4">
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
                </div>
                </aside>
                </div>
            @else
                {{-- Monat wie in Apple Kalender: Monatsname links, Blättern und «Heute» rechts, Raster mit Haarlinien --}}
                @php
                    $imAktuellenMonat = $monat->isSameMonth(now());
                @endphp
                <div class="flex flex-col gap-6" data-np-monat>
                <div class="flex items-center justify-between gap-4">
                    <h2 tabindex="-1" class="text-xl font-semibold text-text outline-hidden">{{ \App\Support\Format::datum($monat, 'F Y') }}</h2>
                    <div class="flex items-center gap-1">
                        <a href="{{ $monatsLink($monat->subMonth()) }}" @click.prevent="wechsle($el.href, 'zurueck')" data-np-blaettern="zurueck" class="np-knopf np-knopf-symbol" aria-label="{{ __('Vorheriger Monat') }}" title="{{ __('Vorheriger Monat') }}"><x-symbol name="chevron-left" strich="2" /></a>
                        <a href="{{ $monatsLink(null) }}" data-np-blaettern="heute" @unless($imAktuellenMonat) @click.prevent="wechsle($el.href, @js($monat->greaterThan(now()) ? 'zurueck' : 'vor'))" @endunless @class(['np-knopf np-knopf-sekundaer np-knopf-klein', 'pointer-events-none opacity-50' => $imAktuellenMonat]) @if($imAktuellenMonat) aria-disabled="true" tabindex="-1" @endif>{{ __('Heute') }}</a>
                        <a href="{{ $monatsLink($monat->addMonth()) }}" @click.prevent="wechsle($el.href, 'vor')" data-np-blaettern="vor" class="np-knopf np-knopf-symbol" aria-label="{{ __('Nächster Monat') }}" title="{{ __('Nächster Monat') }}"><x-symbol name="chevron-right" strich="2" /></a>
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
                                    <span class="flex min-w-0 items-start gap-1.5 px-0.5 text-xs">
                                        <span class="flex h-4 shrink-0 items-center">@include('lernender.agenda._punkt', ['art' => $c['art']])</span>
                                        <span @class(['min-w-0 line-clamp-2 break-words', 'text-text' => in_array($c['art'], ['pruefung', 'erkannt'], true), 'text-muted' => ! in_array($c['art'], ['pruefung', 'erkannt'], true)])>{{ $c['titel'] }}</span>
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
                            <div>
                                <template x-if="e.href">
                                    <a :href="e.href" class="flex items-center gap-3 px-3.5 py-2.5 transition-colors duration-100 hover:bg-surface-2/60">
                                        @include('lernender.agenda._tag_inhalt')
                                    </a>
                                </template>
                                <template x-if="! e.href">
                                    <div class="flex items-center gap-3 px-3.5 py-2.5">
                                        @include('lernender.agenda._tag_inhalt')
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                    <a :href="tagAusgewaehlt.planen" class="np-knopf np-knopf-sekundaer self-start">{{ __('Prüfung planen') }}</a>
                </div>
            </template>
        </x-drawer>
    </div>
</x-app-layout>
