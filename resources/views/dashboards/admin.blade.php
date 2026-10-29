<x-app-layout>
    <x-slot name="title">{{ __('Übersicht') }}</x-slot>
    @php
        $zeigen = [
            'handlungsbedarf' => $sichtbar['handlungsbedarf'] ?? true,
            'berufsbildner' => $sichtbar['berufsbildner'] ?? true,
            'aktivitaet' => $sichtbar['aktivitaet'] ?? true,
            'lehrende' => ($sichtbar['lehrende'] ?? true) && $lehrende->isNotEmpty(),
        ];
        $links = $zeigen['handlungsbedarf'] || $zeigen['berufsbildner'];
        $rechts = $zeigen['aktivitaet'] || $zeigen['lehrende'];
        // Kennzahlen als Kacheln: Symbol, Wert, Name; Farbe nur, wenn es etwas zu tun gibt
        $kacheln = [
            [__('Lernende'), 'users', 'bg-fill text-muted', $kennzahlen['lernende'], route('admin.learners.index')],
            [__('Berufsbildner'), 'identification', 'bg-fill text-muted', $kennzahlen['berufsbildner'], route('admin.trainers.index')],
            [__('Noten :semester', ['semester' => $kennzahlen['semester']]), 'clipboard-document-check', 'bg-fill text-muted', $kennzahlen['noten_semester'], route('admin.reports.grades')],
            [__('Kritisch'), 'exclamation-triangle', $kennzahlen['rot'] ? 'bg-note-ungenuegend/12 text-note-ungenuegend' : 'bg-fill text-muted', $kennzahlen['rot'], route('admin.learners.index', ['warnung' => 'kritisch'])],
            [__('Beobachten'), 'eye', $kennzahlen['gelb'] ? 'bg-note-knapp/14 text-note-knapp' : 'bg-fill text-muted', $kennzahlen['gelb'], route('admin.learners.index', ['warnung' => 'beobachten'])],
            [__('Offene Meldungen'), 'chat-bubble-left-ellipsis', $kennzahlen['feedback'] ? 'bg-accent/12 text-accent-text' : 'bg-fill text-muted', $kennzahlen['feedback'], route('admin.feedback.index')],
        ];
        $ton = [
            'rot' => 'bg-note-ungenuegend/12 text-note-ungenuegend',
            'gelb' => 'bg-note-knapp/14 text-note-knapp',
            'accent' => 'bg-accent/12 text-accent-text',
        ];
    @endphp
    <x-slot name="header">
        <x-seitenkopf :titel="__('Betrieb')" :untertitel="\App\Support\Format::date(now())">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}" class="np-knopf np-knopf-sekundaer"><x-symbol name="user-plus" strich="2" />{{ __('Benutzer erfassen') }}</a>
                <a href="{{ route('admin.learners.create') }}" data-hauptaktion class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Lernende erfassen') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        {{-- Oben die Kennzahlen, darunter links Handlungsbedarf und Berufsbildner (8/12),
             rechts Aktivität und Lehrende (4/12). --}}
        <div class="np-raster mx-auto grid np-seite grid-cols-12 items-start gap-5 px-8">

            <ul class="col-span-12 grid grid-cols-6 gap-4" aria-label="{{ __('Kennzahlen') }}">
                @foreach($kacheln as [$name, $symbol, $farbe, $wert, $link])
                    <li>
                        <a href="{{ $link }}" class="np-karte np-karte-klickbar flex h-full items-start justify-between gap-3 p-4">
                            <span class="flex min-w-0 flex-col gap-3">
                                <span class="flex size-8 items-center justify-center rounded-full {{ $farbe }}" aria-hidden="true">
                                    <x-symbol :name="$symbol" strich="2" class="size-4" />
                                </span>
                                <span class="truncate text-sm font-medium text-muted" title="{{ $name }}">{{ $name }}</span>
                            </span>
                            <span class="text-xl font-semibold tabular-nums leading-none text-text">{{ $wert }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>

            @if($links)
            <div @class(['flex min-w-0 flex-col gap-5', 'col-span-8' => $rechts, 'col-span-12' => ! $rechts])>
                {{-- Handlungsbedarf: eine Liste für Einrichtungslücken, Sicherung, Meldungen, Fehler und kritische Lernende --}}
                @if($zeigen['handlungsbedarf'])
                    <x-karte :titel="__('Handlungsbedarf')" symbol="bell" :polster="false">
                        @if($handlungsbedarf)
                            <x-slot:aktionen><span class="np-marke tabular-nums">{{ count($handlungsbedarf) }}</span></x-slot:aktionen>
                            <ul class="np-liste-eingerueckt px-2 pb-2">
                                @foreach($handlungsbedarf as $h)
                                    <li>
                                        <a href="{{ $h['link'] }}" class="flex min-h-13 items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg {{ $ton[$h['ton']] ?? 'bg-fill text-muted' }}" aria-hidden="true">
                                                <x-symbol :name="$h['symbol']" strich="1.75" class="size-4.5" />
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm text-text" title="{{ $h['text'] }}">{{ $h['text'] }}</span>
                                                @if($h['meta'])
                                                    <span class="block truncate text-xs text-muted" title="{{ $h['meta'] }}">{{ $h['meta'] }}</span>
                                                @endif
                                            </span>
                                            @if($h['note'] !== null)
                                                <x-note :wert="$h['note']" variante="badge" />
                                            @elseif($h['badge'] !== null)
                                                <span class="np-marke tabular-nums">{{ $h['badge'] }}</span>
                                            @endif
                                            <x-symbol name="chevron-right" strich="2" class="size-3.5 shrink-0 text-faint" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="flex items-center gap-3 px-5 pb-4 pt-1 text-sm text-muted">
                                <x-symbol name="check-circle" strich="1.75" class="size-5 text-muted" />{{ __('Kein Handlungsbedarf') }}
                            </p>
                        @endif
                    </x-karte>
                @endif

                {{-- Berufsbildner mit ihren Lernenden --}}
                @if($zeigen['berufsbildner'])
                    <x-karte :titel="__('Berufsbildner')" symbol="identification" :polster="false" :link="route('admin.trainers.index')">
                        @if($proBb->isEmpty())
                            <p class="px-5 pb-4 pt-1 text-sm text-muted">{{ __('Noch keine Berufsbildner') }}</p>
                        @else
                            <div class="overflow-x-auto px-2 pb-2">
                                <table class="np-tabelle text-sm">
                                    <caption class="sr-only">{{ __('Berufsbildner') }}</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('Name') }}</th>
                                            <th scope="col" class="text-right">{{ __('Lernende') }}</th>
                                            <th scope="col" class="text-right">{{ __('Kritisch') }}</th>
                                            <th scope="col" class="text-right">{{ __('Beobachten') }}</th>
                                            <th scope="col" class="text-right">{{ __('Ungesehen') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($proBb as $bb)
                                            <tr>
                                                <td class="h-11">
                                                    <span class="flex items-center gap-3">
                                                        <span class="np-monogramm size-7 shrink-0 text-2xs" aria-hidden="true">{{ collect(explode(' ', $bb->name))->map(fn ($t) => mb_substr($t, 0, 1))->take(2)->implode('') }}</span>
                                                        <span class="truncate text-text">{{ $bb->name }}</span>
                                                    </span>
                                                </td>
                                                <td class="text-right text-text">{{ $bb->lernende }}</td>
                                                <td class="text-right">
                                                    @if($bb->rot)<a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->id, 'warnung' => 'kritisch']) }}" aria-label="{{ __(':anzahl kritisch bei :name', ['anzahl' => $bb->rot, 'name' => $bb->name]) }}" class="inline-flex min-h-6 items-center gap-1.5 rounded-md font-semibold text-note-ungenuegend underline-offset-2 hover:underline"><span class="size-1.5 rounded-full bg-note-ungenuegend" aria-hidden="true"></span>{{ $bb->rot }}</a>@else<span class="text-muted">0</span>@endif
                                                </td>
                                                <td class="text-right">
                                                    @if($bb->gelb)<a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->id, 'warnung' => 'beobachten']) }}" aria-label="{{ __(':anzahl zu beobachten bei :name', ['anzahl' => $bb->gelb, 'name' => $bb->name]) }}" class="inline-flex min-h-6 items-center gap-1.5 rounded-md font-semibold text-note-knapp underline-offset-2 hover:underline"><span class="size-1.5 rounded-full bg-note-knapp" aria-hidden="true"></span>{{ $bb->gelb }}</a>@else<span class="text-muted">0</span>@endif
                                                </td>
                                                <td class="text-right {{ $bb->neu > 20 ? 'font-semibold text-text' : 'text-muted' }}">{{ $bb->neu }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </x-karte>
                @endif
            </div>
            @endif

            @if($rechts)
            <div @class(['flex min-w-0 flex-col gap-5', 'col-span-4' => $links, 'col-span-12' => ! $links])>
                {{-- Erfassung je Woche (S9): Säulen ab 0 mit Median-Linie, nur Betriebssummen. Das Segment gilt sofort (npFilter),
                     Säulen, Satz und Tabelle kommen als Baustein neu vom Server. --}}
                @if($zeigen['aktivitaet'])
                    @php
                        $erfassung = $statistik['diagramm']['erfassung'];
                        $wochen = $erfassung['werte'];
                        $anzahlWochen = count($wochen);
                        $summe = array_sum($wochen);
                        $satz = $statistik['zusammenfassung']['erfassung'];
                        $tabelle = $statistik['tabelle']['erfassung'];
                        $median = $erfassung['median'];
                        $zeitraeume = $statistik['meta']['erfassung']['optionen']['zeitraum'];
                        // Achse ab 0 bis zum nächsten runden Wert über dem Höchstwert (höchstens vier Teilstriche)
                        $spitze = max([1, ...$wochen]);
                        $schritt = 1;
                        foreach ([1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000] as $kandidat) {
                            $schritt = $kandidat;
                            if ($spitze / $kandidat <= 4) {
                                break;
                            }
                        }
                        $oben = (int) (ceil($spitze / $schritt) * $schritt);
                        $teilstriche = range(0, $oben, $schritt);
                        // Zeichenfläche in Einheiten: links Platz für die Achsenzahlen, oben ein Rand
                        $breite = 600;
                        $hoehe = 180;
                        $achsenrand = 30;
                        $kopfrand = 8;
                        $platz = ($breite - $achsenrand - 4) / $anzahlWochen;
                        $saeule = max(2, $platz * 0.72);
                        $y = fn (float|int $v) => round($hoehe - ($v / $oben) * ($hoehe - $kopfrand), 2);
                        // Beschriftung von rechts (aktuelle Woche) her, nie dichter als etwa alle 40 px
                        $takt = $anzahlWochen <= 12 ? 2 : ($anzahlWochen <= 26 ? 4 : 8);
                    @endphp
                    <x-karte :titel="__('Erfasste Noten pro Woche')" symbol="chart-bar" :polster="false">
                        <x-slot:aktionen>
                            <form method="GET" action="{{ route('admin.dashboard') }}" x-data="npFilter(@js(['json' => false, 'standard' => ['zeitraum' => '12w']]))">
                                <div class="np-segment np-segment-klein" role="radiogroup" aria-label="{{ __('Zeitraum') }}">
                                    @foreach($zeitraeume as $wert => $text)
                                        <label><input type="radio" name="zeitraum" value="{{ $wert }}" class="sr-only" @checked($statistik['filter']['zeitraum'] === $wert)>{{ $text }}</label>
                                    @endforeach
                                </div>
                                <noscript><button type="submit" class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Filtern') }}</button></noscript>
                            </form>
                        </x-slot:aktionen>
                        <div id="np-erfassung" data-np-baustein="erfassung" class="flex flex-col">
                            <div class="px-5 pb-4 pt-2">
                                @if($summe === 0)
                                    <p class="py-6 text-center text-sm text-muted" id="np-erfassung-satz" aria-live="polite" data-np-ansage>{{ $satz }}</p>
                                @else
                                <p class="flex flex-wrap items-baseline gap-x-2">
                                    <span class="text-xl font-semibold tabular-nums text-text">{{ $summe }}</span>
                                    <span class="text-sm text-muted">{{ __('Noten in :anzahl Wochen', ['anzahl' => $anzahlWochen]) }}</span>
                                </p>
                                @if($median > 0)
                                    <p class="mt-1 inline-flex items-center gap-2 text-xs tabular-nums text-muted" aria-hidden="true">
                                        <svg class="w-4" viewBox="0 0 16 2"><line x1="0" x2="16" y1="1" y2="1" class="stroke-text" stroke-width="1.5"/></svg>
                                        {{ __('Median :wert je Woche', ['wert' => rtrim(rtrim(number_format($median, 1, '.', ''), '0'), '.')]) }}
                                    </p>
                                @endif
                                <div class="relative mt-3">
                                    <svg class="block w-full" viewBox="0 0 {{ $breite }} {{ $hoehe }}" role="img" aria-label="{{ __('Erfasste Noten pro Woche') }}" aria-describedby="np-erfassung-satz">
                                        @foreach($teilstriche as $strich)
                                            <line x1="{{ $achsenrand }}" x2="{{ $breite }}" y1="{{ $y($strich) }}" y2="{{ $y($strich) }}" @class(['stroke-border-strong' => $strich === 0, 'stroke-border' => $strich !== 0]) stroke-width="1"/>
                                        @endforeach
                                        @foreach($wochen as $i => $wert)
                                            @php $x = round($achsenrand + $i * $platz, 2); @endphp
                                            <g>
                                                <title>{{ $erfassung['labels'][$i] }}: {{ $wert }}</title>
                                                <rect x="{{ $x }}" y="0" width="{{ round($platz, 2) }}" height="{{ $hoehe }}" class="fill-transparent"/>
                                                @if($wert > 0)
                                                    <rect x="{{ round($x + ($platz - $saeule) / 2, 2) }}" y="{{ $y($wert) }}" width="{{ round($saeule, 2) }}" height="{{ round($hoehe - $y($wert), 2) }}" rx="{{ round(min(2, $saeule / 2), 2) }}" class="fill-chart-1/80"/>
                                                @endif
                                            </g>
                                        @endforeach
                                        @if($median > 0)
                                            <line x1="{{ $achsenrand }}" x2="{{ $breite }}" y1="{{ $y($median) }}" y2="{{ $y($median) }}" class="stroke-text" stroke-width="1.5"/>
                                        @endif
                                    </svg>
                                    <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                        @foreach($teilstriche as $strich)
                                            <span class="absolute left-0 -translate-y-1/2 text-2xs tabular-nums text-muted" style="top: {{ round($y($strich) / $hoehe * 100, 2) }}%">{{ $strich }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="relative mt-1 h-4 text-2xs tabular-nums text-muted" aria-hidden="true">
                                    @foreach($erfassung['labels'] as $i => $label)
                                        @if(($anzahlWochen - 1 - $i) % $takt === 0)
                                            @php $letzteWoche = $i === $anzahlWochen - 1; @endphp
                                            {{-- das letzte Label endet am Plot-Ende statt darüber hinauszuragen --}}
                                            <span @class(['absolute whitespace-nowrap', '-translate-x-1/2' => ! $letzteWoche, '-translate-x-full' => $letzteWoche]) style="left: {{ round(($achsenrand + ($i + ($letzteWoche ? 1 : 0.5)) * $platz) / $breite * 100, 2) }}%">{{ $label }}</span>
                                        @endif
                                    @endforeach
                                </div>
                                <p class="sr-only" id="np-erfassung-satz" aria-live="polite" data-np-ansage>{{ $satz }}</p>
                                @endif
                            </div>
                            <details class="group/tabelle np-details border-t border-border">
                                <summary class="flex min-h-9 cursor-pointer list-none items-center gap-1.5 px-5 py-2.5 text-xs font-medium text-muted hover:bg-surface-2/60 hover:text-text">
                                    <span class="inline-block transition-transform duration-200 ruhig:transition-none group-open/tabelle:rotate-90" aria-hidden="true">▸</span>
                                    {{ __('Als Tabelle') }}
                                </summary>
                                <div class="max-h-72 overflow-auto px-5 pb-4">
                                    <table class="np-tabelle text-sm">
                                        <thead>
                                            <tr>
                                                @foreach($tabelle['spalten'] as $i => $spalte)
                                                    <th scope="col" @class(['text-right' => $i > 0])>{{ $spalte }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tabelle['zeilen'] as $zeile)
                                                <tr>
                                                    @foreach($zeile as $i => $zelle)
                                                        <td @class(['text-right tabular-nums' => $i > 0])>{{ $zelle }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </details>
                        </div>
                    </x-karte>
                @endif

                @if($zeigen['lehrende'])
                    <x-karte :titel="__('Lehre endet bald')" symbol="academic-cap" :polster="false">
                        <ul class="np-liste-eingerueckt px-2 pb-2">
                            @foreach($lehrende as $l)
                                <li>
                                    <a href="{{ route('admin.learners.show', $l->lernender_id) }}" class="flex min-h-12 items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                        <span class="np-monogramm size-9 shrink-0 text-xs" aria-hidden="true">{{ mb_substr($l->benutzer->vorname, 0, 1).mb_substr($l->benutzer->nachname, 0, 1) }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm text-text">{{ $l->benutzer->vorname }} {{ $l->benutzer->nachname }}</span>
                                            <span class="block truncate text-xs text-muted" title="{{ $l->lehrberuf?->name }}">{{ $l->lehrberuf?->kuerzel }}</span>
                                        </span>
                                        <span class="shrink-0 text-xs tabular-nums text-muted">{{ $l->lehrende->format('d.m.Y') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </x-karte>
                @endif
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
