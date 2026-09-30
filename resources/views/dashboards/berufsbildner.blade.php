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
        $imPlan = ! count($aufmerksamkeit) ? __('Alle :anzahl Lernenden im Plan', ['anzahl' => $zeilen->count()]) : null;
        // Intelligente Listen wie in Erinnerungen: Zähler oben, Tippen filtert die Tabelle
        $listen = [
            ['alle', __('Alle'), 'users', 'bg-accent/12 text-accent-text', $zeilen->count()],
            ['rot', __('Kritisch'), 'exclamation-triangle', 'bg-note-ungenuegend/12 text-note-ungenuegend', $zeilen->filter(fn ($z) => $z->stand->status === 'rot')->count()],
            ['gelb', __('Beobachten'), 'eye', 'bg-note-knapp/14 text-note-knapp', $zeilen->filter(fn ($z) => $z->stand->status === 'gelb')->count()],
            ['neu', __('Neue Noten'), 'inbox-stack', 'bg-fill text-muted', $zeilen->filter(fn ($z) => $z->neu > 0)->count()],
        ];
    @endphp
    <x-slot name="header">
        <x-seitenkopf :titel="__('Hallo :name', ['name' => auth()->user()->vorname])"
                      :untertitel="implode(' · ', array_filter([\App\Support\Format::date(now()), $imPlan]))">
            <x-slot:aktionen>
                <a href="{{ route('trainer.grades.export_all') }}" class="np-knopf np-knopf-sekundaer"><x-symbol name="arrow-down-tray" strich="2" />CSV</a>
                <a href="{{ route('trainer.learners.create') }}" class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Lernende') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        {{-- Raster nach Breite des Inhalts: oben die Filterkacheln, darunter links die Klassentabelle (8/12),
             rechts Aufmerksamkeit, Termine und Lehrende über die ganze Höhe (4/12). Schmal untereinander in
             der Reihenfolge Aufmerksamkeit, Tabelle, Termine. --}}
        <div class="@container mx-auto np-seite px-4 sm:px-6 lg:px-8" x-data="{ filter: 'alle', suche: '' }">
        <div class="np-raster grid grid-cols-1 items-start gap-5 @min-[60rem]:grid-cols-12 @min-[60rem]:grid-rows-[auto_auto_1fr]">

            @if($zeigen['lernende'] && $zeilen->isNotEmpty())
                <div role="radiogroup" x-radiogroup aria-label="{{ __('Filter') }}"
                     class="grid grid-cols-2 gap-3 @min-[40rem]:grid-cols-4 @min-[60rem]:col-span-12">
                    @foreach($listen as [$wert, $name, $symbol, $ton, $anzahl])
                        <button type="button" role="radio" :aria-checked="filter === '{{ $wert }}'" @click="filter = '{{ $wert }}'"
                                class="group np-karte np-karte-klickbar flex items-start justify-between gap-3 p-4 text-left text-text aria-checked:bg-accent! aria-checked:text-accent-contrast">
                            <span class="flex min-w-0 flex-col gap-3">
                                <span class="flex size-8 items-center justify-center rounded-full {{ $ton }} group-aria-checked:bg-accent-contrast/20 group-aria-checked:text-accent-contrast" aria-hidden="true">
                                    <x-symbol :name="$symbol" strich="2" class="size-4" />
                                </span>
                                <span class="truncate text-sm font-medium text-muted group-aria-checked:text-accent-contrast">{{ $name }}</span>
                            </span>
                            <span class="text-2xl font-bold tabular-nums leading-none">{{ $anzahl }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            {{-- Braucht Aufmerksamkeit: rechts oben, schmal als Erstes nach den Kacheln --}}
            @if($rechts)
            <div @class(['flex min-w-0 flex-col gap-5', '@min-[60rem]:col-span-4 @min-[60rem]:col-start-9 @min-[60rem]:row-span-2 @min-[60rem]:row-start-2' => $zeigen['lernende'], '@min-[60rem]:col-span-12' => ! $zeigen['lernende']])>
                @if($zeigen['aufmerksamkeit'])
                    <x-karte :titel="__('Braucht Aufmerksamkeit')" symbol="exclamation-triangle" :polster="false">
                        <x-slot:aktionen><span class="np-marke tabular-nums">{{ count($aufmerksamkeit) }}</span></x-slot:aktionen>
                        <ul class="np-liste-eingerueckt px-2 pb-2">
                            @foreach($aufmerksamkeit as $eintrag)
                                @php
                                    $z = $eintrag['zeile'];
                                    $b = $z->lernender->benutzer;
                                    $gruende = implode(' · ', $eintrag['gruende']);
                                @endphp
                                <li>
                                    <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="flex min-h-13 items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100 hover:bg-fill-2">
                                        <span class="relative shrink-0" aria-hidden="true">
                                            <span class="np-monogramm size-9 text-xs">{{ mb_substr($b->vorname, 0, 1).mb_substr($b->nachname, 0, 1) }}</span>
                                            <span class="absolute -bottom-0.5 -right-0.5 size-3 rounded-full border-2 border-card {{ $z->stand->status === 'rot' ? 'bg-note-ungenuegend' : 'bg-note-knapp' }}"></span>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-sm font-medium text-text">{{ $b->vorname }} {{ $b->nachname }}<span class="sr-only">, {{ $z->stand->status === 'rot' ? __('kritisch') : __('beobachten') }}</span></span>
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
                                                        <span class="text-3xs font-semibold uppercase text-accent-text">{{ rtrim($datum->isoFormat('MMM'), '.') }}</span>
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
                                                <span class="shrink-0 text-xs tabular-nums text-muted">{{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                @endforeach
                            </ul>
                        @endif
                    </x-karte>
                @endif

                {{-- Lehrende bald --}}
                @if($zeigen['lehrende'])
                    <x-karte :titel="__('Lehrende bald')" symbol="academic-cap" :polster="false">
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

            {{-- Meine Lernenden: Spalten nach Breite der Karte (Container Query), nicht des Fensters --}}
            @if($zeigen['lernende'])
            <x-karte :titel="__('Meine Lernenden')" symbol="users" :polster="false"
                     @class(['@container min-w-0 @min-[60rem]:col-start-1 @min-[60rem]:row-start-2 @min-[60rem]:row-span-2', '@min-[60rem]:col-span-8' => $rechts, '@min-[60rem]:col-span-12' => ! $rechts])>
                <x-slot:aktionen>
                    <x-suchfeld x-model="suche" class="w-full sm:w-52" :label="__('Lernende suchen')" />
                    @if($zeilen->count() > 1)
                        {{-- Schmal fehlen die Spaltenköpfe Status, Gesamt und Verlauf: dieselben Sortierungen in beiden Richtungen als Auswahl,
                             sichtbar bis der letzte davon (Verlauf, @2xl) erscheint --}}
                        @php
                            $mitTrend = $zeilen->contains(fn ($z) => $z->stand->delta() !== null);
                            $sortierungen = array_filter([
                                ['status', 'asc', __('Kritische zuerst')],
                                ['status', 'desc', __('Unauffällige zuerst')],
                                ['name', 'asc', __('Name A–Z')],
                                ['name', 'desc', __('Name Z–A')],
                                ['semester', 'asc', __('Tiefste Semesternote zuerst')],
                                ['semester', 'desc', __('Höchste Semesternote zuerst')],
                                ['gesamt', 'asc', __('Tiefste Gesamtnote zuerst')],
                                ['gesamt', 'desc', __('Höchste Gesamtnote zuerst')],
                                $mitTrend ? ['trend', 'asc', __('Stärkster Rückgang zuerst')] : null,
                                $mitTrend ? ['trend', 'desc', __('Stärkster Anstieg zuerst')] : null,
                            ]);
                            $gewaehlt = ($filter['sort'] ?? 'status').':'.($filter['sort'] === null ? 'asc' : $filter['dir']);
                        @endphp
                        <label for="sortierung" class="sr-only">{{ __('Sortieren') }}</label>
                        <select id="sortierung" x-on:change="window.location.href = $el.value" class="np-feld np-feld-klein np-auswahl w-full sm:w-auto @2xl:hidden">
                            @foreach($sortierungen as [$sort, $dir, $label])
                                <option value="{{ request()->fullUrlWithQuery(['sort' => $sort, 'dir' => $dir]) }}" @selected($gewaehlt === "{$sort}:{$dir}")>{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                </x-slot:aktionen>

                @if($zeilen->isEmpty())
                    <p class="px-5 pb-4 pt-1 text-sm text-muted">{{ __('Keine aktiv betreuten Lernenden') }}</p>
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
                        <table class="np-tabelle text-sm">
                            <caption class="sr-only">{{ __('Meine Lernenden') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col" class="hidden @xl:table-cell" aria-sort="{{ $ariaSort('status') }}">{!! $sortLink('status', __('Status')) !!}</th>
                                    <th scope="col" aria-sort="{{ $ariaSort('name') }}">{!! $sortLink('name', __('Lernende')) !!}</th>
                                    <th scope="col" class="hidden text-right @xl:table-cell">{{ __('Lj') }}</th>
                                    <th scope="col" class="hidden @2xl:table-cell" @if($trendSortierbar) aria-sort="{{ $ariaSort('trend') }}" @endif>{!! $trendSortierbar ? $sortLink('trend', __('Verlauf')) : e(__('Verlauf')) !!}</th>
                                    <th scope="col" class="text-right" aria-sort="{{ $ariaSort('semester') }}">{!! $sortLink('semester', __('Semester'), true) !!}</th>
                                    <th scope="col" class="hidden text-right @xl:table-cell" aria-sort="{{ $ariaSort('gesamt') }}">{!! $sortLink('gesamt', __('Gesamt'), true) !!}</th>
                                    <th scope="col" class="text-right">{{ __('Neue Noten') }}</th>
                                    <th scope="col" class="hidden @4xl:table-cell">{{ __('Nächste Prüfung') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($zeilen as $z)
                                    @php
                                        $b = $z->lernender->benutzer;
                                        $s = $z->stand;
                                        $d = $s->delta();
                                    @endphp
                                    <tr x-show="(filter === 'alle' || filter === '{{ $s->status }}' || (filter === 'neu' && {{ $z->neu }} > 0)) && (suche === '' || @js(mb_strtolower($b->vorname.' '.$b->nachname)).includes(suche.toLowerCase()))">
                                        <td class="hidden h-12 @xl:table-cell"><x-status :status="$s->status" /></td>
                                        <td class="h-12 py-1.5">
                                            <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="font-medium text-text hover:text-accent-text">{{ $b->vorname }} {{ $b->nachname }}</a>
                                            {{-- Schmal stehen die ausgeblendeten Spalten hier: Lehrjahr, Gesamt, nächste Prüfung --}}
                                            <div class="text-xs text-muted">
                                                <span title="{{ $z->lernender->lehrberuf?->name }}">{{ $z->lernender->lehrberuf?->kuerzel }}</span>
                                                <span class="@xl:hidden">@if($z->lehrjahr) · {{ __(':jahr. Lehrjahr', ['jahr' => $z->lehrjahr]) }}@endif · <span class="whitespace-nowrap">{{ __('Gesamt') }} <x-note :wert="$s->auswertung->gesamtNote" :stellen="1" /></span></span>
                                            </div>
                                            @if($z->naechstePruefung)
                                                <div class="text-xs text-muted @4xl:hidden">{{ __('Prüfung am :datum', ['datum' => $z->naechstePruefung->datum->format('d.m.Y')]) }} · {{ $z->naechstePruefung->bezeichnung() }}</div>
                                            @endif
                                            <x-status :status="$s->status" class="mb-1.5 mt-1 @xl:hidden" />
                                        </td>
                                        <td class="hidden text-right text-muted @xl:table-cell">{{ $z->lehrjahr ?? '–' }}</td>
                                        <td class="hidden @2xl:table-cell"><x-sparkline :werte="$s->verlauf" :breite="96" :hoehe="24" :zahl="false" /></td>
                                        <td class="whitespace-nowrap text-right">
                                            <x-note :wert="$s->semesterNote" :stellen="1" />
                                            @if($d !== null && $d != 0)
                                                <span class="ml-1 text-2xs {{ $d > 0 ? 'text-muted' : 'text-note-knapp' }}">{{ $d > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format(abs($d), 1) }}</span>
                                            @endif
                                        </td>
                                        <td class="hidden text-right font-semibold @xl:table-cell"><x-note :wert="$s->auswertung->gesamtNote" :stellen="1" /></td>
                                        <td class="text-right">
                                            @if($z->neu)
                                                <a href="{{ route('trainer.learners.grades.index', $z->lernender->lernender_id) }}" class="np-marke bg-accent/12! text-accent-text hover:bg-accent/20!">{{ $z->neu }}</a>
                                            @else
                                                <a href="{{ route('trainer.learners.grades.index', $z->lernender->lernender_id) }}" class="text-faint hover:text-text">0</a>
                                            @endif
                                        </td>
                                        <td class="hidden max-w-64 @4xl:table-cell">
                                            @if($z->naechstePruefung)
                                                <div class="truncate text-text">{{ $z->naechstePruefung->bezeichnung() }}</div>
                                                <div class="text-xs text-muted">{{ $z->naechstePruefung->datum->format('d.m.Y') }}</div>
                                            @else
                                                <span class="text-faint">–</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-karte>
            @endif

        </div>
        </div>
    </div>
</x-app-layout>
