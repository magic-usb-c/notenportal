<x-app-layout>
    <x-slot name="title">{{ __('Übersicht') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Hallo :name', ['name' => auth()->user()->vorname])" :untertitel="\App\Support\Format::date(now())">
            <x-slot:aktionen>
                <a href="{{ route('trainer.grades.export_all') }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">CSV</a>
                <a href="{{ route('trainer.learners.create') }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> {{ __('Lernende') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="np-raster np-seite mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            {{-- Braucht Aufmerksamkeit --}}
            @if(($sichtbar['aufmerksamkeit'] ?? true) && count($aufmerksamkeit))
                <x-karte :titel="__('Braucht Aufmerksamkeit')" :polster="false">
                    <div class="divide-y divide-border/70">
                        @foreach($aufmerksamkeit as $eintrag)
                            @php $z = $eintrag['zeile']; @endphp
                            <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="flex items-center gap-3 px-5 py-3 transition-colors duration-100 hover:bg-surface-2/60">
                                <span class="size-1.5 shrink-0 rounded-full {{ $z->stand->status === 'rot' ? 'bg-note-ungenuegend' : 'bg-note-knapp' }}" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-text">{{ $z->lernender->benutzer->vorname }} {{ $z->lernender->benutzer->nachname }}</span>
                                    <span class="block truncate text-xs text-muted">{{ implode(' · ', $eintrag['gruende']) }}</span>
                                </span>
                                <span class="text-muted" aria-hidden="true">›</span>
                            </a>
                        @endforeach
                    </div>
                </x-karte>
            @else
                <p class="px-1 text-sm text-muted">{{ __('Alle :anzahl Lernenden im Plan', ['anzahl' => $zeilen->count()]) }}</p>
            @endif

            {{-- Meine Lernenden --}}
            @if($sichtbar['lernende'] ?? true)
            {{-- Spalten nach Breite der Karte, nicht des Fensters: mit Seitenleiste ist die Karte 256 px schmaler --}}
            <x-karte :titel="__('Meine Lernenden')" :polster="false" class="@container"
                     x-data="{
                        filter: 'alle',
                        suche: '',
                        zeilen: {{ \Illuminate\Support\Js::from($zeilen->map(fn ($z) => ['status' => $z->stand->status, 'neu' => $z->neu])->values()) }},
                        get zaehlAlle() { return this.zeilen.length },
                        get zaehlKritisch() { return this.zeilen.filter(z => z.status === 'rot').length },
                        get zaehlBeobachten() { return this.zeilen.filter(z => z.status === 'gelb').length },
                        get zaehlNeu() { return this.zeilen.filter(z => z.neu > 0).length },
                     }">
                <x-slot:aktionen>
                    <div class="flex min-w-0 max-w-full flex-wrap items-center justify-end gap-2">
                        <input type="search" x-model="suche" placeholder="{{ __('Suchen') }}" aria-label="{{ __('Lernende suchen') }}"
                               class="h-8 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-48">
                        <div role="radiogroup" x-radiogroup aria-label="{{ __('Filter') }}" class="grid w-full grid-cols-2 gap-0.5 sm:flex sm:w-auto sm:items-center sm:gap-1 rounded-lg bg-surface-2 p-0.5 text-xs">
                            <button type="button" role="radio" :aria-checked="filter === 'alle'" @click="filter = 'alle'" class="h-8 flex-auto shrink-0 whitespace-nowrap rounded-md px-1.5 sm:px-2.5" :class="filter === 'alle' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="@js(__('Alle').' ') + zaehlAlle"></button>
                            <button type="button" role="radio" :aria-checked="filter === 'rot'" @click="filter = 'rot'" class="h-8 flex-auto shrink-0 whitespace-nowrap rounded-md px-1.5 sm:px-2.5" :class="filter === 'rot' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="@js(__('Kritisch').' ') + zaehlKritisch"></button>
                            <button type="button" role="radio" :aria-checked="filter === 'gelb'" @click="filter = 'gelb'" class="h-8 flex-auto shrink-0 whitespace-nowrap rounded-md px-1.5 sm:px-2.5" :class="filter === 'gelb' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="@js(__('Beobachten').' ') + zaehlBeobachten"></button>
                            <button type="button" role="radio" :aria-checked="filter === 'neu'" @click="filter = 'neu'" class="h-8 flex-auto shrink-0 whitespace-nowrap rounded-md px-1.5 sm:px-2.5" :class="filter === 'neu' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="@js(__('Neue Noten').' ') + zaehlNeu"></button>
                        </div>
                        @if($zeilen->count() > 1)
                            {{-- Schmal fehlen die Spaltenköpfe Status, Gesamt und Verlauf: dieselben Sortierungen als Auswahl --}}
                            @php
                                $sortierungen = array_filter([
                                    ['status', 'asc', __('Kritische zuerst')],
                                    ['name', 'asc', __('Name A–Z')],
                                    ['name', 'desc', __('Name Z–A')],
                                    ['semester', 'asc', __('Tiefste Semesternote zuerst')],
                                    ['semester', 'desc', __('Höchste Semesternote zuerst')],
                                    ['gesamt', 'asc', __('Tiefste Gesamtnote zuerst')],
                                    ['gesamt', 'desc', __('Höchste Gesamtnote zuerst')],
                                    $zeilen->contains(fn ($z) => $z->stand->delta() !== null) ? ['trend', 'asc', __('Stärkster Rückgang zuerst')] : null,
                                ]);
                                $gewaehlt = ($filter['sort'] ?? 'status').':'.($filter['sort'] === null ? 'asc' : $filter['dir']);
                            @endphp
                            <label for="sortierung" class="sr-only">{{ __('Sortieren') }}</label>
                            <select id="sortierung" x-on:change="window.location.href = $el.value"
                                    class="h-8 w-full rounded-lg border border-border-strong/60 bg-input px-2.5 py-0 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 @xl:hidden">
                                @foreach($sortierungen as [$sort, $dir, $label])
                                    <option value="{{ request()->fullUrlWithQuery(['sort' => $sort, 'dir' => $dir]) }}" @selected($gewaehlt === "{$sort}:{$dir}")>{{ $label }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>
                </x-slot:aktionen>

                @if($zeilen->isEmpty())
                    <p class="px-5 pb-5 text-sm text-muted">{{ __('Keine aktiv betreuten Lernenden') }}</p>
                @else
                    @php
                        $trendSortierbar = $zeilen->contains(fn ($z) => $z->stand->delta() !== null);
                        $sortLink = function (string $spalte, string $label) use ($filter) {
                            $aktiv = $filter['sort'] === $spalte;
                            $naechsteDir = $aktiv && $filter['dir'] === 'asc' ? 'desc' : 'asc';
                            $pfeil = ! $aktiv
                                ? '<span class="invisible text-muted group-hover/sort:visible group-focus-visible/sort:visible" aria-hidden="true">↑</span>'
                                : '<span aria-hidden="true">'.($filter['dir'] === 'asc' ? '↑' : '↓').'</span>';
                            $url = e(request()->fullUrlWithQuery(['sort' => $spalte, 'dir' => $naechsteDir]));

                            return '<a href="'.$url.'" class="group/sort inline-flex items-center gap-1 hover:text-text'.($aktiv ? ' text-text font-semibold' : '').'">'.e($label).' '.$pfeil.'</a>';
                        };
                        $ariaSort = fn (string $spalte) => $filter['sort'] === $spalte ? ($filter['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';
                    @endphp
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm tabular-nums">
                            <thead>
                                <tr class="border-y border-border">
                                    <th scope="col" class="hidden h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted @xl:table-cell" aria-sort="{{ $ariaSort('status') }}">{!! $sortLink('status', __('Status')) !!}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted" aria-sort="{{ $ariaSort('name') }}">{!! $sortLink('name', __('Lernende')) !!}</th>
                                    <th scope="col" class="hidden h-9 bg-surface-2 px-2 text-right text-2xs font-medium text-muted @xl:table-cell">{{ __('Lj') }}</th>
                                    <th scope="col" class="hidden h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted @2xl:table-cell" @if($trendSortierbar) aria-sort="{{ $ariaSort('trend') }}" @endif>{!! $trendSortierbar ? $sortLink('trend', __('Verlauf')) : e(__('Verlauf')) !!}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-right text-2xs font-medium text-muted" aria-sort="{{ $ariaSort('semester') }}">{!! $sortLink('semester', __('Semester')) !!}</th>
                                    <th scope="col" class="hidden h-9 bg-surface-2 px-3 text-right text-2xs font-medium text-muted @xl:table-cell" aria-sort="{{ $ariaSort('gesamt') }}">{!! $sortLink('gesamt', __('Gesamt')) !!}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-right text-2xs font-medium text-muted">{{ __('Neue Noten') }}</th>
                                    <th scope="col" class="hidden h-9 bg-surface-2 px-5 text-left text-2xs font-medium text-muted @4xl:table-cell">{{ __('Nächste Prüfung') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($zeilen as $z)
                                    @php
                                        $b = $z->lernender->benutzer;
                                        $s = $z->stand;
                                        $d = $s->delta();
                                    @endphp
                                    <tr class="border-b border-border last:border-0 hover:bg-surface-2/60"
                                        x-show="(filter === 'alle' || filter === '{{ $s->status }}' || (filter === 'neu' && {{ $z->neu }} > 0)) && (suche === '' || @js(mb_strtolower($b->vorname.' '.$b->nachname)).includes(suche.toLowerCase()))">
                                        <td class="hidden h-11 px-3 @xl:table-cell"><x-status :status="$s->status" /></td>
                                        <td class="h-11 px-3">
                                            <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="font-medium text-text hover:text-accent-text">{{ $b->vorname }} {{ $b->nachname }}</a>
                                            {{-- Schmal stehen die ausgeblendeten Spalten hier: Lehrjahr, Gesamt, nächste Prüfung --}}
                                            <div class="text-xs text-muted">
                                                <span title="{{ $z->lernender->lehrberuf?->name }}">{{ $z->lernender->lehrberuf?->kuerzel }}</span>
                                                <span class="@xl:hidden">@if($z->lehrjahr) · {{ __(':jahr. Lehrjahr', ['jahr' => $z->lehrjahr]) }}@endif · <span class="whitespace-nowrap">{{ __('Gesamt') }} <x-note :wert="$s->auswertung->gesamtNote" :stellen="1" /></span></span>
                                            </div>
                                            @if($z->naechstePruefung)
                                                <div class="text-xs text-muted @4xl:hidden">{{ __('Prüfung am :datum', ['datum' => $z->naechstePruefung->datum->format('d.m.Y')]) }} · {{ $z->naechstePruefung->bezeichnung() }}</div>
                                            @endif
                                            <x-status :status="$s->status" class="mt-1 mb-1.5 @xl:hidden" />
                                        </td>
                                        <td class="hidden h-11 px-2 text-right @xl:table-cell">{{ $z->lehrjahr ?? '–' }}</td>
                                        <td class="hidden h-11 px-3 @2xl:table-cell"><x-sparkline :werte="$s->verlauf" :zahl="false" /></td>
                                        <td class="h-11 px-3 text-right whitespace-nowrap">
                                            <x-note :wert="$s->semesterNote" :stellen="1" />
                                            @if($d !== null && $d != 0)
                                                <span class="block text-2xs {{ $d > 0 ? 'text-text' : 'text-note-knapp' }}">{{ $d > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format(abs($d), 1) }}</span>
                                            @endif
                                        </td>
                                        <td class="hidden h-11 px-3 text-right @xl:table-cell"><x-note :wert="$s->auswertung->gesamtNote" :stellen="1" /></td>
                                        <td class="h-11 px-3 text-right">
                                            <a href="{{ route('trainer.learners.grades.index', $z->lernender->lernender_id) }}" class="{{ $z->neu ? 'font-semibold text-accent-text' : 'text-muted' }} hover:underline underline-offset-2">{{ $z->neu }}</a>
                                        </td>
                                        <td class="hidden h-11 px-5 @4xl:table-cell">
                                            @if($z->naechstePruefung)
                                                <div class="truncate text-text">{{ $z->naechstePruefung->bezeichnung() }}</div>
                                                <div class="text-xs text-muted">{{ $z->naechstePruefung->datum->format('d.m.Y') }}</div>
                                            @else
                                                <span class="text-muted">–</span>
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

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                {{-- Nächste 14 Tage --}}
                @if($sichtbar['agenda'] ?? true)
                <x-karte :titel="__('Nächste 14 Tage')" :link="route('trainer.exams.index')" :link-text="__('Alle Termine')" class="lg:col-span-8" :polster="false">
                    @if($agenda->isEmpty())
                        <p class="px-5 py-8 text-center text-sm text-muted">{{ __('Keine geplant') }}</p>
                    @else
                        <div class="divide-y divide-border/70">
                            @foreach($agenda as $tag => $pruefungenAmTag)
                                @php $datum = \Carbon\Carbon::parse($tag); @endphp
                                <div class="px-5 py-3">
                                    <div class="mb-2 text-xs font-medium text-muted">{{ \App\Support\Format::date($datum, 'wochentag_tag') }}</div>
                                    <div class="flex flex-col gap-2">
                                        @foreach($pruefungenAmTag as $p)
                                            <div class="flex items-center gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate text-sm text-text">{{ $p->bezeichnung() }}</div>
                                                    <div class="truncate text-xs text-muted">{{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }} · {{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-karte>
                @endif

                {{-- Lehrende bald --}}
                @if(($sichtbar['lehrende'] ?? true) && $lehrende->isNotEmpty())
                    <x-karte :titel="__('Lehrende bald')" class="lg:col-span-4" :polster="false">
                        <div class="divide-y divide-border/70">
                            @foreach($lehrende as $l)
                                <a href="{{ route('trainer.learners.show', $l->lernender_id) }}" class="flex items-center justify-between gap-3 px-5 py-2.5 transition-colors duration-100 hover:bg-surface-2/60">
                                    <span class="text-sm text-text">{{ $l->benutzer->vorname }} {{ $l->benutzer->nachname }}</span>
                                    <span class="text-xs tabular-nums text-muted">{{ $l->lehrende->format('d.m.Y') }} · {{ __('in :tage Tagen', ['tage' => (int) now()->startOfDay()->diffInDays($l->lehrende)]) }}</span>
                                </a>
                            @endforeach
                        </div>
                    </x-karte>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
