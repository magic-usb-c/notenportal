<x-app-layout>
    <x-slot name="title">{{ __('Übersicht') }}</x-slot>
    @php
        $zeigen = [
            'aufmerksamkeit' => ($sichtbar['aufmerksamkeit'] ?? true) && count($aufmerksamkeit),
            'lernende' => $sichtbar['lernende'] ?? true,
            'agenda' => $sichtbar['agenda'] ?? true,
            'lehrende' => ($sichtbar['lehrende'] ?? true) && $lehrende->isNotEmpty(),
        ];
        $rechts = $zeigen['aufmerksamkeit'] || $zeigen['agenda'] || $zeigen['lehrende'];
        $laufend = $zeilen->filter(fn ($z) => $z->stand->status !== \App\Services\Auswertung\Lernstand::ABGESCHLOSSEN)->count();
        $imPlan = ! count($aufmerksamkeit) && $laufend ? __('Alle :anzahl Lernenden im Plan', ['anzahl' => $laufend]) : null;
        // Intelligente Listen wie in Erinnerungen: Zähler oben, ein Klick filtert die Tabelle
        $listen = [
            ['alle', __('Alle'), 'users', 'bg-accent/12 text-accent-text', $zeilen->count()],
            ['rot', __('Kritisch'), 'exclamation-triangle', 'bg-note-ungenuegend/14 text-note-ungenuegend', $zeilen->filter(fn ($z) => $z->stand->status === 'rot')->count()],
            ['gelb', __('Beobachten'), 'eye', 'bg-note-knapp/14 text-note-knapp', $zeilen->filter(fn ($z) => $z->stand->status === 'gelb')->count()],
            ['neu', __('Mit neuen Noten'), 'inbox-stack', 'bg-fill text-muted', $zeilen->filter(fn ($z) => $z->neu > 0)->count()],
        ];
        // Filter und Suche prüfen im Browser dieselben Werte, die die Zeile zeigt
        $filterDaten = $zeilen->map(fn ($z) => [
            's' => $z->stand->status,
            'n' => $z->neu,
            'q' => mb_strtolower($z->lernender->benutzer->vorname.' '.$z->lernender->benutzer->nachname),
        ])->values();
    @endphp
    <x-slot name="header">
        <x-seitenkopf :titel="__('Hallo :name', ['name' => auth()->user()->vorname])"
                      :untertitel="implode(' · ', array_filter([\App\Support\Format::date(now()), $imPlan]))">
            <x-slot:aktionen>
                <a href="{{ route('trainer.grades.export_all') }}" class="np-knopf np-knopf-sekundaer"><x-symbol name="arrow-down-tray" strich="2" />{{ __('Exportieren') }}</a>
                <a href="{{ route('trainer.learners.create') }}" class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Lernende erfassen') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        {{-- Oben die Filterkacheln über die ganze Breite, darunter links die Klassentabelle (8/12) und rechts
             Aufmerksamkeit, Termine und Lehrende (4/12). --}}
        <div class="np-raster mx-auto flex np-seite flex-col gap-5 px-8"
             x-data="{
                 filter: 'alle',
                 suche: '',
                 zeilen: @js($filterDaten),
                 passt(z) {
                     const suche = this.suche.trim().toLowerCase();
                     return (this.filter === 'alle' || this.filter === z.s || (this.filter === 'neu' && z.n > 0))
                         && (suche === '' || z.q.includes(suche));
                 },
             }">

            @if($zeigen['lernende'] && $zeilen->isNotEmpty())
                <div role="radiogroup" x-radiogroup aria-label="{{ __('Filter') }}" class="grid grid-cols-4 gap-4">
                    @foreach($listen as [$wert, $name, $symbol, $ton, $anzahl])
                        <button type="button" role="radio" :aria-checked="filter === '{{ $wert }}'" @click="filter = '{{ $wert }}'"
                                :class="filter === '{{ $wert }}' ? 'bg-accent/12! border-accent/50' : ''"
                                class="np-karte np-karte-klickbar flex items-start justify-between gap-3 border border-transparent p-4 text-left text-text">
                            <span class="flex min-w-0 flex-col gap-3">
                                <span class="flex size-8 items-center justify-center rounded-full {{ $ton }}" aria-hidden="true">
                                    <x-symbol :name="$symbol" strich="2" class="size-4" />
                                </span>
                                <span class="truncate text-sm font-medium text-muted">{{ $name }}</span>
                            </span>
                            <span :class="filter === '{{ $wert }}' ? 'text-accent-text' : ''" class="text-xl font-semibold tabular-nums leading-none">{{ $anzahl }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-12 items-start gap-5">
                {{-- Meine Lernenden --}}
                @if($zeigen['lernende'])
                <x-karte :titel="__('Meine Lernenden')" symbol="users" :polster="false" @class(['min-w-0', 'col-span-8' => $rechts, 'col-span-12' => ! $rechts])>
                    @if($zeilen->isNotEmpty())
                        <x-slot:aktionen>
                            <x-suchfeld x-model="suche" class="w-56" :label="__('Lernende suchen')" />
                        </x-slot:aktionen>
                    @endif

                    @if($zeilen->isEmpty())
                        <x-leer symbol="users" :titel="__('Keine aktiv betreuten Lernenden')">
                            <a href="{{ route('trainer.learners.create') }}" class="np-knopf np-knopf-sekundaer">{{ __('Lernende erfassen') }}</a>
                        </x-leer>
                    @else
                        @php
                            $trendSortierbar = $zeilen->contains(fn ($z) => $z->stand->delta() !== null);
                            $sortLink = function (string $spalte, string $label, bool $rechts = false) use ($filter) {
                                $aktiv = $filter['sort'] === $spalte;
                                $naechsteDir = $aktiv && $filter['dir'] === 'asc' ? 'desc' : 'asc';
                                $pfeil = ! $aktiv
                                    ? '<span class="invisible text-faint group-hover/sort:visible group-focus-visible/sort:visible" aria-hidden="true">↑</span>'
                                    : '<span aria-hidden="true">'.($filter['dir'] === 'asc' ? '↑' : '↓').'</span>';
                                $url = e(request()->fullUrlWithQuery(['sort' => $spalte, 'dir' => $naechsteDir]));

                                return '<a href="'.$url.'" class="group/sort inline-flex min-h-6 items-center gap-1 hover:text-text'.($rechts ? ' flex-row-reverse' : '').($aktiv ? ' font-semibold text-text' : '').'">'.e($label).' '.$pfeil.'</a>';
                            };
                            $ariaSort = fn (string $spalte) => $filter['sort'] === $spalte ? ($filter['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
                        @endphp
                        <div class="overflow-x-auto px-2 pb-2">
                            <table class="np-tabelle table-fixed text-sm">
                                <caption class="sr-only">{{ __('Meine Lernenden') }}</caption>
                                <colgroup>
                                    <col class="w-32"><col class="w-48"><col class="w-20"><col class="w-32"><col class="w-24"><col class="w-24"><col class="w-24"><col>
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col" aria-sort="{{ $ariaSort('status') }}">{!! $sortLink('status', __('Status')) !!}</th>
                                        <th scope="col" aria-sort="{{ $ariaSort('name') }}">{!! $sortLink('name', __('Lernende')) !!}</th>
                                        <th scope="col" class="text-right">{{ __('Lehrjahr') }}</th>
                                        <th scope="col" @if($trendSortierbar) aria-sort="{{ $ariaSort('trend') }}" @endif>{!! $trendSortierbar ? $sortLink('trend', __('Verlauf')) : e(__('Verlauf')) !!}</th>
                                        <th scope="col" class="text-right" aria-sort="{{ $ariaSort('semester') }}">{!! $sortLink('semester', __('Semester'), true) !!}</th>
                                        <th scope="col" class="text-right" aria-sort="{{ $ariaSort('gesamt') }}">{!! $sortLink('gesamt', __('Gesamt'), true) !!}</th>
                                        <th scope="col" class="text-right">{{ __('Neue Noten') }}</th>
                                        <th scope="col">{{ __('Nächste Prüfung') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($zeilen as $z)
                                        @php
                                            $b = $z->lernender->benutzer;
                                            $s = $z->stand;
                                            $d = $s->delta();
                                            $ziel = route('trainer.learners.show', $z->lernender->lernender_id);
                                        @endphp
                                        <tr data-href="{{ $ziel }}" x-show="passt(zeilen[{{ $loop->index }}])">
                                            <td class="h-13"><x-status :status="$s->status" /></td>
                                            <td class="py-1.5">
                                                <a href="{{ $ziel }}" class="block truncate font-medium text-text">{{ $b->vorname }} {{ $b->nachname }}</a>
                                                @if($z->lernender->lehrberuf)
                                                    <div class="truncate text-xs text-muted" title="{{ $z->lernender->lehrberuf->name }}">{{ $z->lernender->lehrberuf->kuerzel ?: $z->lernender->lehrberuf->name }}</div>
                                                @endif
                                            </td>
                                            <td class="text-right tabular-nums text-muted">{{ $z->lehrjahr ?? '–' }}</td>
                                            <td><x-sparkline :werte="$s->verlauf" :breite="96" :hoehe="24" :zahl="false" /></td>
                                            <td class="whitespace-nowrap text-right">
                                                <x-note :wert="$s->semesterNote" :stellen="1" />
                                                @if($d !== null && $d != 0)
                                                    <span class="ml-1 text-2xs {{ $d > 0 ? 'text-muted' : 'text-note-knapp' }}">{{ $d > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format(abs($d), 1) }}</span>
                                                @endif
                                            </td>
                                            <td class="text-right font-semibold"><x-note :wert="$s->auswertung->gesamtNote" :stellen="1" /></td>
                                            <td class="text-right">
                                                @if($z->neu)
                                                    <a href="{{ route('trainer.learners.grades.index', $z->lernender->lernender_id) }}" class="inline-flex min-h-6 items-center gap-1.5 font-semibold text-text hover:text-accent-text"
                                                       aria-label="{{ __(':anzahl neue Noten von :name', ['anzahl' => $z->neu, 'name' => $b->vorname.' '.$b->nachname]) }}"><span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>{{ $z->neu }}</a>
                                                @else
                                                    <span class="tabular-nums text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($z->naechstePruefung)
                                                    <div class="truncate text-text" title="{{ $z->naechstePruefung->bezeichnung() }}">{{ $z->naechstePruefung->bezeichnung() }}</div>
                                                    <div class="text-xs tabular-nums text-muted">{{ $z->naechstePruefung->datum->format('d.m.Y') }}</div>
                                                @else
                                                    <span class="text-muted">–</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr x-show="! zeilen.some((z) => passt(z))" x-cloak>
                                        <td colspan="8" class="h-13 text-center text-muted">{{ __('Keine Lernenden für diesen Filter.') }} <button type="button" @click="filter = 'alle'; suche = ''" class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Alle anzeigen') }}</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-karte>
                @endif

                @if($rechts)
                <div @class(['flex min-w-0 flex-col gap-5', 'col-span-4' => $zeigen['lernende'], 'col-span-12' => ! $zeigen['lernende']])>
                    @if($zeigen['aufmerksamkeit'])
                        <x-karte :titel="__('Braucht Aufmerksamkeit')" symbol="exclamation-triangle" :polster="false">
                            <x-slot:aktionen><span class="np-marke tabular-nums">{{ count($aufmerksamkeit) }}</span></x-slot:aktionen>
                            <ul class="np-liste-eingerueckt px-2 pb-2">
                                @foreach($aufmerksamkeit as $eintrag)
                                    @php
                                        $z = $eintrag['zeile'];
                                        $b = $z->lernender->benutzer;
                                        $gruende = implode(' · ', $eintrag['gruende']);
                                        // Gleiche Zuordnung wie <x-status>: neue Noten holen auch laufende und abgeschlossene Lehren hierher.
                                        [$punkt, $wort] = match ($z->stand->status) {
                                            'rot' => ['bg-note-ungenuegend', __('Kritisch')],
                                            'gelb' => ['bg-note-knapp', __('Beobachten')],
                                            'neutral' => ['bg-muted', __('Offen')],
                                            'abgeschlossen' => ['bg-muted', __('Abgeschlossen')],
                                            default => ['bg-note-gut', __('Im Plan')],
                                        };
                                    @endphp
                                    <li>
                                        <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="flex min-h-13 items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                            <span class="relative shrink-0" aria-hidden="true">
                                                <span class="np-monogramm size-9 text-xs">{{ mb_substr($b->vorname, 0, 1).mb_substr($b->nachname, 0, 1) }}</span>
                                                <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-card {{ $punkt }}"></span>
                                            </span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm font-medium text-text">{{ $b->vorname }} {{ $b->nachname }}<span class="sr-only">, {{ $wort }}</span></span>
                                                <span class="line-clamp-2 text-xs text-muted" title="{{ $gruende }}">{{ $gruende }}</span>
                                            </span>
                                            <x-symbol name="chevron-right" strich="2" class="size-3.5 shrink-0 text-faint" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </x-karte>
                    @endif

                    {{-- Nächste 14 Tage: Kalenderkachel beim ersten Termin eines Tages --}}
                    @if($zeigen['agenda'])
                        <x-karte :titel="__('Nächste 14 Tage')" symbol="calendar-days" :link="route('trainer.exams.index')" :link-text="__('Alle Termine')" :polster="false">
                            @if($agenda->isEmpty())
                                <p class="px-5 pb-4 pt-1 text-sm text-muted">{{ __('Keine geplant') }}</p>
                            @else
                                <ul class="np-liste-eingerueckt px-2 pb-2">
                                    @foreach($agenda as $tag => $pruefungenAmTag)
                                        @php $datum = \Carbon\Carbon::parse($tag); @endphp
                                        @foreach($pruefungenAmTag as $p)
                                            <li>
                                                <a href="{{ route('trainer.learners.show', $p->lernender_id) }}" class="flex min-h-13 items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                                    @if($loop->first)
                                                        <span class="flex size-9 shrink-0 flex-col items-center justify-center rounded-lg bg-fill leading-none" title="{{ \App\Support\Format::date($datum, 'wochentag_tag') }}">
                                                            <span class="text-2xs font-medium text-muted">{{ rtrim($datum->isoFormat('MMM'), '.') }}</span>
                                                            <span class="mt-0.5 text-base font-semibold tabular-nums text-text">{{ $datum->format('j') }}</span>
                                                            <span class="sr-only">{{ \App\Support\Format::date($datum, 'wochentag_tag') }}</span>
                                                        </span>
                                                    @else
                                                        <span class="size-9 shrink-0" aria-hidden="true"></span>
                                                    @endif
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm text-text">{{ $p->bezeichnung() }}</span>
                                                        <span class="block truncate text-xs text-muted">{{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }}</span>
                                                    </span>
                                                    <span class="shrink-0 text-xs tabular-nums text-muted" title="{{ __('Gewichtung') }}"><span class="sr-only">{{ __('Gewichtung') }} </span>{{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</span>
                                                </a>
                                            </li>
                                        @endforeach
                                    @endforeach
                                </ul>
                            @endif
                        </x-karte>
                    @endif

                    @if($zeigen['lehrende'])
                        <x-karte :titel="__('Lehre endet bald')" symbol="academic-cap" :polster="false">
                            <ul class="np-liste-eingerueckt px-2 pb-2">
                                @foreach($lehrende as $l)
                                    <li>
                                        <a href="{{ route('trainer.learners.show', $l->lernender_id) }}" class="flex min-h-12 items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                            <span class="np-monogramm size-9 shrink-0 text-xs" aria-hidden="true">{{ mb_substr($l->benutzer->vorname, 0, 1).mb_substr($l->benutzer->nachname, 0, 1) }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm text-text">{{ $l->benutzer->vorname }} {{ $l->benutzer->nachname }}</span>
                                                <span class="block text-xs tabular-nums text-muted">{{ $l->lehrende->format('d.m.Y') }}</span>
                                            </span>
                                            <span class="shrink-0 text-xs tabular-nums text-muted">{{ __('in :tage Tagen', ['tage' => (int) now()->startOfDay()->diffInDays($l->lehrende)]) }}</span>
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
    </div>
</x-app-layout>
