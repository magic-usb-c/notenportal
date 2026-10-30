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
        <x-seitenkopf :titel="__('Übersicht')" :untertitel="\App\Support\Format::date(now())">
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
                                                <td class="text-right {{ $bb->neu > 0 ? 'font-semibold text-text' : 'text-muted' }}">{{ $bb->neu }}</td>
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
                {{-- Erfassung je Woche (S9): Säulen ab 0 mit Medianlinie, nur Betriebssummen. Der Zeitraum gilt ohne Seitenwechsel:
                     npFilter holt die Seite und ersetzt den Baustein «erfassung». --}}
                @if($zeigen['aktivitaet'])
                    @php
                        $erf = $statistik['diagramm']['erfassung'];
                        $erfSatz = $statistik['zusammenfassung']['erfassung'];
                        $erfTabelle = $statistik['tabelle']['erfassung'];
                        $erfOptionen = $statistik['meta']['erfassung']['optionen']['zeitraum'];
                        $erfAnzahl = count($erf['werte']);
                        $erfSpitze = $erf['werte'] === [] ? 0 : max($erf['werte']);
                        // Achse ab 0 mit ganzzahligen Marken; die Obergrenze ist ein Vielfaches des Schritts
                        $erfSchritt = match (true) {
                            $erfSpitze <= 4 => 1,
                            $erfSpitze <= 10 => 2,
                            $erfSpitze <= 20 => 5,
                            $erfSpitze <= 50 => 10,
                            default => (int) (ceil($erfSpitze / 5 / 10) * 10),
                        };
                        $erfOben = max($erfSchritt, (int) (ceil($erfSpitze / $erfSchritt) * $erfSchritt));
                        $erfMarken = range(0, $erfOben, $erfSchritt);
                        $erfHoehe = fn (int|float $v) => round($v / $erfOben * 100, 2);
                        $erfLabelSchritt = $erfAnzahl <= 12 ? 2 : ($erfAnzahl <= 26 ? 4 : 8);
                        // Ohne Noten im Zeitraum bleibt nur der Leerzustand; der längste Zeitraum ist der Weiterweg
                        $erfLeer = array_sum($erf['werte']) === 0;
                        $erfLaengster = array_key_last($erfOptionen);
                        $erfLaenger = $statistik['filter']['zeitraum'] !== $erfLaengster;
                    @endphp
                    <x-filterleiste :action="route('admin.dashboard')" :statistik="['json' => false, 'standard' => ['zeitraum' => '12w']]">
                        <span id="stat-erfassung-zeitraum" class="sr-only">{{ __('Zeitraum') }}</span>
                        <x-segment-auswahl name="zeitraum" beschriftung="stat-erfassung-zeitraum" :optionen="$erfOptionen" :wert="$statistik['filter']['zeitraum']" />
                    </x-filterleiste>
                    <x-diagramm :titel="__('Erfasste Noten pro Woche')" id="np-erfassung" data-np-baustein="erfassung">
                        @if($erfLeer)
                            <x-leer symbol="chart-bar" :titel="__('Keine Noten erfasst')" :text="__('In diesen :anzahl Wochen wurde keine Note erfasst.', ['anzahl' => $erfAnzahl])" class="py-10">
                                @if($erfLaenger)
                                    <a href="{{ route('admin.dashboard', ['zeitraum' => $erfLaengster]) }}" class="np-knopf np-knopf-sekundaer">{{ __('Zeitraum auf :zeitraum erweitern', ['zeitraum' => $erfOptionen[$erfLaengster]]) }}</a>
                                @endif
                            </x-leer>
                        @else
                        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <p class="flex items-baseline gap-2">
                                <span class="text-xl font-semibold tabular-nums text-text">{{ array_sum($erf['werte']) }}</span>
                                <span class="text-sm text-muted">{{ __('Noten in :anzahl Wochen', ['anzahl' => $erfAnzahl]) }}</span>
                            </p>
                            @if($erf['median'])
                                <span class="inline-flex items-center gap-1.5 text-xs tabular-nums text-muted" aria-hidden="true">
                                    <span class="h-0.5 w-4 rounded-full bg-text"></span>{{ __('Median') }} {{ \App\Support\NotenSkala::format($erf['median'], floor($erf['median']) == $erf['median'] ? 0 : 1) }}
                                </span>
                            @endif
                        </div>
                        <div class="flex gap-2" role="img" aria-label="{{ $erfSatz }}">
                            <div class="relative h-48 w-7 shrink-0" aria-hidden="true">
                                @foreach($erfMarken as $marke)
                                    <span class="absolute right-0 translate-y-1/2 text-2xs tabular-nums leading-none text-muted" style="bottom: {{ $erfHoehe($marke) }}%">{{ $marke }}</span>
                                @endforeach
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="relative h-48" aria-hidden="true">
                                    @foreach($erfMarken as $marke)
                                        <div class="absolute inset-x-0 border-t {{ $marke === 0 ? 'border-border-strong/60' : 'border-border' }}" style="bottom: {{ $erfHoehe($marke) }}%"></div>
                                    @endforeach
                                    <div class="absolute inset-0 flex items-end gap-px">
                                        @foreach($erf['werte'] as $i => $wert)
                                            <div class="flex h-full min-w-0 flex-1 items-end" title="{{ $erf['labels'][$i] }}: {{ $wert }}">
                                                @if($wert > 0)
                                                    <div class="w-full rounded-t-sm bg-chart-1" style="height: {{ max(1.5, $erfHoehe($wert)) }}%"></div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    @if($erf['median'])
                                        <div class="absolute inset-x-0 h-0.5 bg-text" style="bottom: calc({{ $erfHoehe($erf['median']) }}% - 1px)"></div>
                                    @endif
                                </div>
                                <div class="relative mt-1 h-4" aria-hidden="true">
                                    @foreach($erf['labels'] as $i => $label)
                                        @if(($erfAnzahl - 1 - $i) % $erfLabelSchritt === 0)
                                            <span class="absolute -translate-x-1/2 whitespace-nowrap text-2xs tabular-nums text-muted" style="left: {{ round(($i + 0.5) / $erfAnzahl * 100, 2) }}%">{{ $label }}</span>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        @endif
                        {{-- Beim Laden leer (Zeile oben und Beschriftung des Diagramms sagen es schon); nach einem Filterwechsel sagt statistik.js data-np-text einmal an --}}
                        <p class="sr-only" aria-live="polite" data-np-ansage data-np-text="{{ $erfSatz }}"></p>
                        @unless($erfLeer)
                        <x-slot:tabelle>
                            <table class="np-tabelle text-sm">
                                <caption class="sr-only">{{ __('Erfasste Noten pro Woche') }}</caption>
                                <thead>
                                    <tr>
                                        @foreach($erfTabelle['spalten'] as $i => $spalte)
                                            <th scope="col" @class(['text-right' => $i > 0])>{{ $spalte }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($erfTabelle['zeilen'] as $zeile)
                                        <tr>
                                            @foreach($zeile as $i => $zelle)
                                                <td @class(['text-right' => $i > 0])>{{ $zelle }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-slot:tabelle>
                        @endunless
                    </x-diagramm>
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
