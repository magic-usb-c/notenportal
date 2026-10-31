<x-app-layout>
    <x-slot name="title">{{ __('Übersicht') }}</x-slot>
    @php
        $a = $auswertung;
        $delta = $stand->delta();
        $skala = \App\Support\NotenSkala::class;
        $meta = $lehrzeit ? implode(' · ', array_filter([
            $lehrzeit['beruf'],
            $lehrzeit['lehrjahr'] ? __(':jahr. Lehrjahr', ['jahr' => $lehrzeit['lehrjahr']]) : null,
            $lehrzeit['tage'] > 0 ? __('noch :tage Tage', ['tage' => $lehrzeit['tage']]) : __('Lehrzeit abgeschlossen'),
        ])) : null;
        $anzahlBalken = max(count($balken['semester']['labels']), count($balken['lehrzeit']['labels']));
        $balkenModus = count($balken['semester']['labels']) ? 'semester' : 'lehrzeit';
        $zeigen = [
            'stand' => $sichtbar['stand'] ?? true,
            'als_naechstes' => $sichtbar['als_naechstes'] ?? true,
            'wo_stehe_ich' => ($sichtbar['wo_stehe_ich'] ?? true) && $anzahlBalken > 0,
            'ziele' => ($sichtbar['ziele'] ?? true) && $ziele,
            'verlauf' => ($sichtbar['verlauf'] ?? true) && count($verlauf['labels']) > 0,
            'letzte_noten' => ($sichtbar['letzte_noten'] ?? true) && $letzteNoten->isNotEmpty(),
        ];
        $links = $zeigen['stand'] || $zeigen['wo_stehe_ich'] || $zeigen['verlauf'];
        $rechts = $zeigen['als_naechstes'] || $zeigen['ziele'] || $zeigen['letzte_noten'];
        $paar = $zeigen['wo_stehe_ich'] && $zeigen['verlauf'];
        $qv = $a->gesamtNote !== null ? \App\Services\Auswertung\Notenbaum\Abschluss::hauptergebnis($a) : null;
        $kachel = [
            'rot' => ['exclamation-triangle', 'bg-note-ungenuegend/12 text-note-ungenuegend'],
            'gelb' => ['pencil-square', 'bg-fill text-muted'],
            'accent' => ['chat-bubble-left-ellipsis', 'bg-accent/12 text-accent-text'],
            'neutral' => ['rectangle-stack', 'bg-fill text-muted'],
        ];
    @endphp
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Hallo :name', ['name' => auth()->user()->vorname]) }}" :untertitel="$meta">
            <x-slot:aktionen>
                <a href="{{ route('learner.exams.index', ['planen' => 1]) }}" class="np-knopf np-knopf-sekundaer">{{ __('Prüfung planen') }}</a>
                <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                   data-hauptaktion class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Note') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        @if($a->gesamtNote === null && $letzteNoten->isEmpty() && ! $alsNaechstes && ! $zeigen['ziele'])
        {{-- Ganz am Anfang: eine Ansicht, ein Leerzustand (HIG «Content unavailable») statt zweier fast leerer Karten --}}
        <div class="mx-auto np-seite px-8">
            <x-leer symbol="academic-cap" :titel="__('Noch keine Noten')"
                    :text="__('Sobald Noten oder Prüfungen erfasst sind, siehst du hier deinen Stand und was als Nächstes ansteht.')">
                <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                   class="np-knopf np-knopf-sekundaer">{{ __('Erste Note erfassen') }}</a>
            </x-leer>
        </div>
        @else
        {{-- Links (8/12) Stand, darunter Wo stehe ich und Verlauf nebeneinander und gleich hoch; rechts (4/12)
             Als Nächstes, Ziele und Letzte Noten. Fehlt eine Seite, nimmt die andere die ganze Breite. --}}
        <div class="np-raster mx-auto grid np-seite grid-cols-12 items-start gap-5 px-8">

            @if($links)
            <div @class(['flex min-w-0 flex-col gap-5', 'col-span-8' => $rechts, 'col-span-12' => ! $rechts])>

            {{-- Stand: Heldenzahl mit Semesterverlauf, Kategorien als Kennzahlen, Bullet Graph über die ganze Breite --}}
            @if($zeigen['stand'])
            <x-karte titel="{{ __('Stand') }}" symbol="chart-bar" class="min-w-0">
                @if($a->gesamtNote !== null)
                    <div class="grid grid-cols-[auto_minmax(0,1fr)] gap-x-8 gap-y-5">
                        <div class="min-w-0">
                            @if($qv)
                                <a href="{{ route('learner.qualification.index') }}" class="inline-flex min-h-6 items-center gap-0.5 text-sm font-medium text-accent-text underline-offset-2 hover:underline">{{ $qv->wurzel()->vollstaendig ? __('QV-Gesamtnote') : __('QV-Prognose') }}<x-symbol name="chevron-right" strich="2" class="size-3.5" /></a>
                            @else
                                <p class="flex min-h-6 items-center text-sm font-medium text-muted">{{ __('Gesamtschnitt') }}</p>
                            @endif
                            <div class="mt-1 flex items-end gap-5">
                                <x-note :wert="$a->gesamtNote" variante="hero" :stellen="1" class="text-display leading-none" />
                                @if(count(array_filter($stand->verlauf, fn ($v) => $v !== null)) > 1)
                                    <x-sparkline class="mb-1.5" :werte="$stand->verlauf" :breite="112" :hoehe="36" :zahl="false" :label="__('Semesterschnitte')" />
                                @endif
                            </div>
                            @if($stand->semesterId)
                                <p class="mt-3 flex flex-wrap items-baseline gap-x-2 text-sm">
                                    <x-semester class="text-muted" :id="$stand->semesterId" :lernender="$a->lernenderId" />
                                    <x-note :wert="$stand->semesterNote" :stellen="1" />
                                    @if($delta !== null && $delta != 0)
                                        <span class="text-xs tabular-nums text-muted">{{ $delta > 0 ? '▲' : '▼' }} {{ $skala::format(abs($delta), 1) }}</span>
                                    @endif
                                    @if(($lehrzeit['semester_rest'] ?? null) && ($lehrzeit['semester_id'] ?? null) === $stand->semesterId)
                                        <span class="text-xs text-muted">· {{ $lehrzeit['semester_rest'] }}</span>
                                    @endif
                                </p>
                            @endif
                        </div>

                        @if($kategorien)
                            {{-- Kategoriewerte gelten für die ganze Lehrzeit (die Notenseite zeigt je Semester) --}}
                            <div class="self-end">
                            <p class="mb-2 text-xs text-muted">{{ __('Lehrzeit') }}</p>
                            <ul class="grid grid-cols-[repeat(auto-fit,minmax(8.5rem,1fr))] gap-x-5 gap-y-4">
                                @foreach($kategorien as $kat)
                                    <li class="min-w-0 border-l border-border pl-5">
                                        <a href="{{ route('learner.grades.index', ['kategorie_id' => $kat['id']]) }}" class="group block rounded-md">
                                            <span class="block truncate text-sm text-muted transition-colors duration-100 group-hover:text-text">{{ $kat['name'] }}</span>
                                            <span class="mt-1.5 flex items-end gap-4">
                                                <x-note :wert="$kat['note']" :stellen="1" class="text-xl leading-none" />
                                                <x-sparkline :werte="$kat['verlauf']" :breite="64" :hoehe="24" :zahl="false" :label="__('Verlauf :name', ['name' => $kat['name']])" />
                                            </span>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                            </div>
                        @endif
                    </div>

                    <x-bullet class="mt-6" :wert="$a->gesamtNote" :ziel="$zielGesamt" :grenzen="$grenzen" />
                @else
                    <p class="flex items-center gap-3 text-sm text-muted">
                        {{ __('Noch keine Noten') }}
                        <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                           class="inline-flex min-h-6 items-center text-accent-text underline-offset-2 hover:underline">{{ __('Erste Note erfassen') }}</a>
                    </p>
                @endif
            </x-karte>
            @endif

            @if($zeigen['wo_stehe_ich'] || $zeigen['verlauf'])
            @php
                // Filter der beiden Statistiken: eine Leiste, eine Adresse. Kategorien und Fächer nur aus dem, was dieser Lernende hat.
                $optionen = $statistik['meta']['verlauf']['optionen'];
                $wMeta = $statistik['meta']['wostehe'];
                $f = $statistik['filter'];
                $statKategorien = collect($a->kategorien)->keys()->mapWithKeys(fn ($kid) => [(int) $kid => $a->konfiguration->kategorieName((int) $kid)])->all();
                $statFaecher = collect($a->elemente)->filter(fn ($e) => $e->typ === \App\Services\Auswertung\Element::FACH && $e->fachId !== null)
                    ->mapWithKeys(fn ($e) => [(int) $e->fachId => $a->konfiguration->fachName((int) $e->fachId)])->sort()->all();
                $auswahl = 'np-feld np-feld-klein np-auswahl w-auto max-w-56';
                $w = $statistik['diagramm']['wostehe'];
                $wSicht = $w[$w['modus']];
                $wSatz = $statistik['zusammenfassung']['wostehe'];
                $wTabelle = $statistik['tabelle']['wostehe'];
            @endphp
            <x-filterleiste :action="route('learner.dashboard')" :statistik="['standard' => \App\Support\StatistikDaten::STANDARD_LERNENDER]">
                <label for="stat-kategorie" class="sr-only">{{ __('Kategorie') }}</label>
                <select name="kategorie" id="stat-kategorie" class="{{ $auswahl }}">
                    <option value="">{{ __('Kategorie') }}</option>
                    @foreach($statKategorien as $kid => $name)
                        <option value="{{ $kid }}" @selected($f['kategorie'] === $kid)>{{ $name }}</option>
                    @endforeach
                </select>
                @if($zeigen['verlauf'])
                    <div role="group" aria-labelledby="stat-gruppe-verlauf" class="ml-3 flex flex-wrap items-center gap-2">
                        <span id="stat-gruppe-verlauf" class="text-sm text-muted">{{ __('Verlauf') }}</span>
                        <label for="stat-zeitraum" class="sr-only">{{ __('Zeitraum') }}</label>
                        <select name="zeitraum" id="stat-zeitraum" class="{{ $auswahl }}">
                            @foreach($optionen['zeitraum'] as $wert => $text)
                                <option value="{{ $wert }}" @selected($f['zeitraum'] === $wert)>{{ $text }}</option>
                            @endforeach
                        </select>
                        <label for="stat-fach" class="sr-only">{{ __('Fach') }}</label>
                        <select name="fach" id="stat-fach" class="{{ $auswahl }}">
                            <option value="">{{ __('Fach') }}</option>
                            @foreach($statFaecher as $fid => $name)
                                <option value="{{ $fid }}" @selected($f['fach'] === $fid)>{{ $name }}</option>
                            @endforeach
                        </select>
                        <label for="stat-achse" class="sr-only">{{ __('Zeitachse') }}</label>
                        <select name="achse" id="stat-achse" class="{{ $auswahl }}">
                            @foreach($optionen['achse'] as $wert => $text)
                                <option value="{{ $wert }}" @selected($f['achse'] === $wert)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if($zeigen['wo_stehe_ich'])
                    <div role="group" aria-labelledby="stat-gruppe-wostehe" class="ml-3 flex flex-wrap items-center gap-2">
                        <span id="stat-gruppe-wostehe" class="text-sm text-muted">{{ __('Wo stehe ich') }}</span>
                        <label for="stat-modus" class="sr-only">{{ __('Zeitraum') }}</label>
                        <select name="modus" id="stat-modus" class="{{ $auswahl }}">
                            @foreach($wMeta['optionen']['modus'] as $wert => $text)
                                <option value="{{ $wert }}" @selected($f['modus'] === $wert)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </x-filterleiste>

            <div @class(['grid min-w-0 gap-5', 'grid-cols-2' => $paar])>
            {{-- Wo stehe ich pro Fach und Modul: serverseitig gezeichneter Baustein, npFilter ersetzt ihn nach einer Filteränderung --}}
            @if($zeigen['wo_stehe_ich'])
                <x-diagramm :titel="$wMeta['titel']" id="np-wostehe" data-np-baustein="wostehe" class="min-w-0">
                    @if($wSicht['labels'] === [])
                        <p class="py-6 text-center text-sm text-muted">{{ $wSatz }}</p>
                    @else
                        <div class="shrink-0" x-data="npChart('balken', @js(['labels' => $wSicht['labels'], 'werte' => $wSicht['werte'], 'grenzen' => $w['grenzen']]))" style="height: {{ max(120, count($wSicht['labels']) * 30 + 56) }}px">
                            <canvas x-ref="canvas" role="img" aria-label="{{ $wSatz }}"></canvas>
                        </div>
                        <x-noten-legende class="mb-3 mt-3" neutral="bg-text/50" />
                        <p class="text-xs text-muted">{{ $wSatz }}</p>
                    @endif
                    <p class="sr-only" aria-live="polite" data-np-ansage>{{ $wSatz }}</p>
                    <x-slot:tabelle>
                        <table class="np-tabelle table-fixed text-sm">
                            <caption class="sr-only">{{ $wMeta['titel'] }}</caption>
                            <colgroup><col><col class="w-20"><col class="w-28"></colgroup>
                            <thead>
                                <tr>
                                    @foreach($wTabelle['spalten'] as $i => $spalte)
                                        <th scope="col" @class(['text-right' => $i > 0])>{{ $spalte }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($wTabelle['zeilen'] as $zeile)
                                    <tr>
                                        @foreach($zeile as $i => $zelle)
                                            @if($i === 0)
                                                <th scope="row" class="text-left font-normal text-text">{{ $zelle }}</th>
                                            @else
                                                <td @class(['text-right', 'text-muted' => $i === 2])>{{ $zelle }}</td>
                                            @endif
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:tabelle>
                </x-diagramm>
            @endif

            {{-- Notenverlauf: Prüfungen auf der Datumsachse (oder Semesternoten), Filter ohne Seitenwechsel --}}
            @if($zeigen['verlauf'])
                <x-verlauf :paket="$statistik" hoehe="h-72" class="min-w-0" />
            @endif
            </div>
            @endif
            </div>
            @endif

            @if($rechts)
            <div @class(['flex min-w-0 flex-col gap-5', 'col-span-4' => $links, 'col-span-12' => ! $links])>
            {{-- Als Nächstes: Überfälliges oben, dann Hinweise, Prüfungen, fehlende Module --}}
            @if($zeigen['als_naechstes'])
            <x-karte titel="{{ __('Als Nächstes') }}" symbol="calendar-days" :polster="false" :link="route('learner.exams.index')" :link-text="__('Agenda')">
                @if($alsNaechstes)
                    <x-slot:aktionen><span class="np-marke tabular-nums">{{ count($alsNaechstes) }}</span></x-slot:aktionen>
                    <ul class="np-liste-eingerueckt px-2 pb-2">
                        @foreach($alsNaechstes as $t)
                            <li>
                                <a href="{{ $t['link'] }}" class="flex min-h-13 items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                    @if($t['datum'])
                                        <span class="flex size-9 shrink-0 flex-col items-center justify-center rounded-lg bg-fill leading-none" aria-hidden="true">
                                            <span class="text-2xs font-medium text-muted">{{ \App\Support\Format::datum($t['datum'], 'M') }}</span>
                                            <span class="mt-0.5 text-base font-semibold tabular-nums text-text">{{ $t['datum']->format('j') }}</span>
                                        </span>
                                    @else
                                        @php [$symbol, $ton] = $kachel[$t['ton']] ?? $kachel['neutral']; @endphp
                                        <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $ton }}" aria-hidden="true">
                                            <x-symbol :name="$symbol" strich="1.75" class="size-4.5" />
                                        </span>
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="line-clamp-2 text-sm text-text">{{ $t['text'] }}</span>
                                        @if($t['detail'])<span class="block truncate text-xs text-muted" title="{{ $t['detail'] }}">{{ $t['detail'] }}</span>@endif
                                    </span>
                                    @if($t['rechts'])<span class="shrink-0 text-xs tabular-nums text-muted">{{ $t['rechts'] }}</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="flex items-center gap-3 px-5 pb-4 pt-1 text-sm text-muted">
                        {{ __('Nichts offen') }}
                        <a href="{{ route('learner.exams.index', ['planen' => 1]) }}" class="inline-flex min-h-6 items-center text-accent-text underline-offset-2 hover:underline">{{ __('Prüfung planen') }}</a>
                    </p>
                @endif
            </x-karte>
            @endif

            {{-- Ziele: nur wenn gesetzt --}}
            @if($zeigen['ziele'])
                <x-karte titel="{{ __('Ziele') }}" symbol="flag" :link="route('learner.grades.calculator')" :link-text="__('Rechner')" :polster="false">
                    <ul class="np-liste-eingerueckt px-2 pb-2 [--np-einzug:0.75rem]">
                        @foreach($ziele as $z)
                            @php
                                $l = $z['loesung'];
                                $erreicht = $z['aktuell'] !== null && $z['aktuell'] >= $z['zielwert'] - 1e-9;
                            @endphp
                            <li>
                                <a href="{{ $z['link'] }}" class="block rounded-lg px-3 py-3 transition-colors duration-100 hover:bg-fill-2">
                                    <span class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="flex min-w-0 gap-1 text-text"><span class="truncate" title="{{ $z['label'] }}">{{ $z['label'] }}</span><span class="shrink-0 whitespace-nowrap">≥ {{ $skala::format($z['zielwert'], 1) }}</span></span>
                                        <x-note :wert="$z['aktuell']" :stellen="1" class="shrink-0" />
                                    </span>
                                    <x-bullet class="mt-1.5" :wert="$z['aktuell']" :ziel="$z['zielwert']" :grenzen="$grenzen" :label="$z['label']" :skala="false" />
                                    <span class="mt-1 block text-xs text-muted">
                                        @switch($l['status'])
                                            @case('benoetigt')
                                                {{ $l['unbekannte'] === 1 ? __('Nötig in der offenen Prüfung:') : __('Nötig in den :anzahl offenen Prüfungen:', ['anzahl' => $l['unbekannte']]) }}
                                                <span class="font-semibold tabular-nums text-text">{{ $skala::format($l['note'], 2) }}</span>
                                                @break
                                            @case('erreicht')
                                                {{ __('Gesichert') }}
                                                @break
                                            @case('unerreichbar')
                                                {{ __('Mit den offenen Prüfungen höchstens :note', ['note' => $skala::format($l['maximum'], 1)]) }}
                                                @break
                                            @default
                                                {{ $erreicht ? __('Erreicht') : __('Keine offenen Prüfungen geplant') }}
                                        @endswitch
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-karte>
            @endif

            {{-- Letzte Noten --}}
            @if($zeigen['letzte_noten'])
                <x-karte titel="{{ __('Letzte Noten') }}" symbol="clipboard-document-check" :link="route('learner.grades.index')" :polster="false">
                    <ul class="np-liste-eingerueckt px-2 pb-2 [--np-einzug:0.75rem]">
                        @foreach($letzteNoten as $n)
                            <li>
                                <a href="{{ route('learner.grades.index', ['_open' => $n->note_id]) }}" class="flex min-h-12 items-center justify-between gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                    <span class="min-w-0">
                                        <span class="line-clamp-2 text-sm text-text">{{ $n->fach?->name ?? trim(($n->modulBelegung?->modul?->modul_nummer ?? '').' '.($n->modulBelegung?->modul?->titel ?? '')) }}</span>
                                        <span class="block truncate text-xs text-muted" @if($n->titel) title="{{ $n->titel }}" @endif>{{ $n->pruefungsdatum->format('d.m.Y') }}@if($n->titel) · {{ $n->titel }}@endif</span>
                                    </span>
                                    <x-note :wert="$n->note_wert" :stufe="$n->note_stufe" variante="badge" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-karte>
            @endif
            </div>
            @endif

        </div>
        @endif
    </div>

    {{-- Erfassen im Drawer wie auf /grades --}}
    <x-noten-drawer :fehler="$drawerFehler" />
</x-app-layout>
