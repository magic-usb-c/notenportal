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
            [__('Lernende'), 'users', 'bg-accent/12 text-accent-text', $kennzahlen['lernende'], route('admin.learners.index')],
            [__('Berufsbildner'), 'identification', 'bg-accent/12 text-accent-text', $kennzahlen['berufsbildner'], route('admin.trainers.index')],
            [__('Noten :semester', ['semester' => $kennzahlen['semester']]), 'clipboard-document-check', 'bg-accent/12 text-accent-text', $kennzahlen['noten_semester'], route('admin.reports.grades')],
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
                <a href="{{ route('admin.learners.create') }}" class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Lernende') }}</a>
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
                            <span class="text-2xl font-semibold tabular-nums leading-none text-text">{{ $wert }}</span>
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
                                <x-symbol name="check-circle" strich="1.75" class="size-5 text-note-gut" />{{ __('Kein Handlungsbedarf') }}
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
                                                    @if($bb->rot)<a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->id, 'warnung' => 'kritisch']) }}" aria-label="{{ __(':anzahl kritisch bei :name', ['anzahl' => $bb->rot, 'name' => $bb->name]) }}" class="inline-flex min-h-6 items-center gap-1.5 rounded-md font-semibold text-note-ungenuegend underline-offset-2 hover:underline"><span class="size-1.5 rounded-full bg-note-ungenuegend" aria-hidden="true"></span>{{ $bb->rot }}</a>@else<span class="text-faint">0</span>@endif
                                                </td>
                                                <td class="text-right">
                                                    @if($bb->gelb)<a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->id, 'warnung' => 'beobachten']) }}" aria-label="{{ __(':anzahl zu beobachten bei :name', ['anzahl' => $bb->gelb, 'name' => $bb->name]) }}" class="inline-flex min-h-6 items-center gap-1.5 rounded-md font-semibold text-note-knapp underline-offset-2 hover:underline"><span class="size-1.5 rounded-full bg-note-knapp" aria-hidden="true"></span>{{ $bb->gelb }}</a>@else<span class="text-faint">0</span>@endif
                                                </td>
                                                <td class="text-right {{ $bb->neu > 20 ? 'font-semibold text-accent-text' : 'text-muted' }}">{{ $bb->neu }}</td>
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
                {{-- Erfasste Noten pro Woche: Summe als Kennzahl über dem Diagramm wie in Health --}}
                @if($zeigen['aktivitaet'])
                    <x-karte :titel="__('Erfasste Noten pro Woche')" symbol="chart-bar">
                        <p class="flex items-baseline gap-2">
                            <span class="text-2xl font-semibold tabular-nums text-text">{{ array_sum($aktivitaet['werte']) }}</span>
                            <span class="text-sm text-muted">{{ __('Noten in :anzahl Wochen', ['anzahl' => count($aktivitaet['werte'])]) }}</span>
                        </p>
                        <div class="mt-3 h-48" x-data="npChart('saeulen', {{ \Illuminate\Support\Js::from(['labels' => $aktivitaet['labels'], 'werte' => $aktivitaet['werte'], 'name' => __('Noten')]) }})">
                            <canvas x-ref="canvas" role="img" aria-label="{{ __('Erfasste Noten pro Woche') }}"></canvas>
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
