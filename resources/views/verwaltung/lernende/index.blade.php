{{-- Lernendenliste für Admin und Berufsbildner: Symbolleiste mit Suche und Filtern, darunter eine sortierbare
     Tabelle. Die ganze Zeile führt zum Profil, «Noten» direkt zur Notenliste. --}}
@php
    $aktiveFilter = collect([$filter['suche'], $filter['lehrberuf_id'], $filter['lehrjahr'], $filter['berufsbildner_id'],
        $filter['bms'], $filter['warnung'], $filter['inaktive']])->filter()->count();

    $sortLink = function (string $spalte, string $label) use ($filter) {
        $aktiv = $filter['sort'] === $spalte;
        $dir = $aktiv && $filter['dir'] === 'asc' ? 'desc' : 'asc';
        $pfeil = ! $aktiv ? '<span class="invisible text-muted group-hover/sort:visible group-focus-visible/sort:visible" aria-hidden="true">↑</span>' : '<span aria-hidden="true">'.($filter['dir'] === 'asc' ? '↑' : '↓').'</span>';

        return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $spalte, 'dir' => $dir])).'" class="group/sort inline-flex min-h-6 items-center gap-1 hover:text-text '.($aktiv ? 'text-text font-semibold' : '').'">'.e($label).' '.$pfeil.'</a>';
    };
    // Die Richtung sagt aria-sort am Spaltenkopf an, der Pfeil ist nur fürs Auge
    $ariaSort = fn (string $spalte) => $filter['sort'] === $spalte ? ($filter['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';

    $auswahl = 'np-feld np-feld-klein w-auto max-w-64';
    $tageSeit = fn ($z) => $z->lastNote ? (int) \Carbon\Carbon::parse($z->lastNote)->startOfDay()->diffInDays(now()->startOfDay()) : null;
    $wann = fn (?int $tage) => match (true) {
        $tage === null => '–',
        $tage === 0 => __('heute'),
        $tage === 1 => __('gestern'),
        default => __('vor :tage Tagen', ['tage' => $tage]),
    };
    $spalten = $bereich === 'admin' ? 8 : 7;
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Lernende') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Lernende')" :zaehler="$zeilen->count()">
            <x-slot:aktionen>
                <a href="{{ route("{$bereich}.grades.export_all") }}" class="np-knopf np-knopf-sekundaer">
                    <x-symbol name="arrow-down-tray" />{{ __('Alle Noten (CSV)') }}
                </a>
                <a href="{{ route("{$bereich}.learners.create") }}" class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Lernender erfassen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-4">

            <x-filterleiste :action="route($bereich.'.learners.index')" suche-name="suche" :suche-wert="$filter['suche']"
                             :suche-platzhalter="__('Name, E-Mail, Benutzername')"
                             :zurueck="route($bereich.'.learners.index')" :aktive-filter="$aktiveFilter">
                <x-slot:hidden>
                    <input type="hidden" name="sort" value="{{ $filter['sort'] }}">
                    <input type="hidden" name="dir" value="{{ $filter['dir'] }}">
                </x-slot:hidden>

                <label for="lehrberuf_id" class="sr-only">{{ __('Lehrberuf') }}</label>
                <select name="lehrberuf_id" id="lehrberuf_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">{{ __('Alle Lehrberufe') }}</option>
                    @foreach($lehrberufe as $lb)
                        <option value="{{ $lb->lehrberuf_id }}" @selected($filter['lehrberuf_id'] === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                    @endforeach
                </select>

                <label for="lehrjahr" class="sr-only">{{ __('Lehrjahr') }}</label>
                <select name="lehrjahr" id="lehrjahr" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">{{ __('Alle Lehrjahre') }}</option>
                    @foreach([1, 2, 3, 4] as $jahr)
                        <option value="{{ $jahr }}" @selected($filter['lehrjahr'] === $jahr)>{{ __(':jahr. Lehrjahr', ['jahr' => $jahr]) }}</option>
                    @endforeach
                </select>

                @if($bereich === 'admin')
                    <label for="berufsbildner_id" class="sr-only">{{ __('Berufsbildner') }}</label>
                    <select name="berufsbildner_id" id="berufsbildner_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Alle Berufsbildner') }}</option>
                        @foreach($berufsbildnerListe as $bb)
                            <option value="{{ $bb->berufsbildner_id }}" @selected($filter['berufsbildner_id'] === (int) $bb->berufsbildner_id)>
                                {{ $bb->benutzer->nachname }} {{ $bb->benutzer->vorname }}
                            </option>
                        @endforeach
                    </select>
                @endif

                <x-slot:weitere>
                    <label for="warnung" class="sr-only">{{ __('Warnung') }}</label>
                    <select name="warnung" id="warnung" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Warnung: alle') }}</option>
                        <option value="tief_avg" @selected($filter['warnung'] === 'tief_avg')>{{ __('Ø unter :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}</option>
                        <option value="keine_noten" @selected($filter['warnung'] === 'keine_noten')>{{ __('Kein Eintrag seit :tage Tagen', ['tage' => $frist]) }}</option>
                        @if($bereich === 'admin')
                            <option value="ohne_betreuung" @selected($filter['warnung'] === 'ohne_betreuung')>{{ __('Ohne Berufsbildner') }}</option>
                            <option value="ohne_track" @selected($filter['warnung'] === 'ohne_track')>{{ __('Ohne aktiven Track') }}</option>
                        @endif
                    </select>

                    <label for="bms" class="sr-only">BMS</label>
                    <select name="bms" id="bms" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('BMS: alle') }}</option>
                        <option value="ja" @selected($filter['bms'] === 'ja')>{{ __('Mit BMS') }}</option>
                        <option value="nein" @selected($filter['bms'] === 'nein')>{{ __('Ohne BMS') }}</option>
                    </select>

                    <label for="inaktive" class="sr-only">{{ __('Status') }}</label>
                    <select name="inaktive" id="inaktive" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="0" @selected(! $filter['inaktive'])>{{ __('Nur aktive') }}</option>
                        <option value="1" @selected($filter['inaktive'])>{{ __('Inkl. inaktive') }}</option>
                    </select>
                </x-slot:weitere>
            </x-filterleiste>

            <div class="np-karte overflow-hidden">
                <div class="p-2">
                    <table class="np-tabelle text-sm">
                        <thead>
                            <tr>
                                <th scope="col" aria-sort="{{ $ariaSort('name') }}">{!! $sortLink('name', __('Name')) !!}</th>
                                <th scope="col" class="whitespace-nowrap" aria-sort="{{ $ariaSort('lehrjahr') }}">{!! $sortLink('lehrjahr', __('Lehrberuf / Lj')) !!}</th>
                                @if($bereich === 'admin')
                                    <th scope="col" class="whitespace-nowrap">{{ __('Berufsbildner') }}</th>
                                @endif
                                <th scope="col" class="w-20 text-right whitespace-nowrap">{{ __('Noten') }}</th>
                                <th scope="col" class="w-36 text-right whitespace-nowrap" aria-sort="{{ $ariaSort('last_note') }}">{!! $sortLink('last_note', __('Letzte Note')) !!}</th>
                                <th scope="col" class="w-28 text-right whitespace-nowrap" aria-sort="{{ $ariaSort('avg') }}">{!! $sortLink('avg', __('Ø gesamt')) !!}</th>
                                <th scope="col">{{ __('Status') }}</th>
                                <th scope="col" class="w-24 text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($zeilen as $z)
                                @php
                                    $l = $z->lernender;
                                    $tagSeit = $tageSeit($z);
                                    $zielUrl = route("{$bereich}.learners.show", $l->lernender_id);
                                @endphp
                                <tr class="cursor-pointer {{ $l->benutzer->aktiv ? '' : 'opacity-60' }}" onclick="window.location='{{ $zielUrl }}'">
                                    <td>
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="np-monogramm size-8 shrink-0 text-2xs" aria-hidden="true">{{ mb_strtoupper(mb_substr($z->vorname ?? '', 0, 1).mb_substr($z->nachname ?? '', 0, 1)) ?: '?' }}</span>
                                            <div class="min-w-0">
                                                <a href="{{ $zielUrl }}" title="{{ $l->benutzer->email }}" class="font-medium text-text hover:text-accent-text">{{ $z->nachname }} {{ $z->vorname }}</a>
                                                {{-- Bei einer Suche zeigt die Adresse, warum die Person trifft --}}
                                                @if($filter['suche'] !== '')
                                                    <div class="truncate text-xs text-muted">{{ $l->benutzer->email }}</div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap">
                                        <span title="{{ $l->lehrberuf?->name }}">{{ $l->lehrberuf?->kuerzel ?? '–' }}</span>
                                        <span class="text-muted">· {{ $z->lehrjahr ?: '–' }}</span>
                                    </td>
                                    @if($bereich === 'admin')
                                        <td class="whitespace-nowrap text-muted">
                                            {{ $z->betreuer ? $z->betreuer->nachname.' '.$z->betreuer->vorname : '–' }}
                                        </td>
                                    @endif
                                    <td class="text-right tabular-nums">{{ $z->anzahl }}</td>
                                    <td class="text-right whitespace-nowrap text-muted">{{ $wann($tagSeit) }}</td>
                                    <td class="text-right"><x-note :wert="$z->avg" :stellen="2" /></td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">@include('verwaltung.lernende._status')</div>
                                    </td>
                                    <td class="text-right" onclick="event.stopPropagation()">
                                        <a href="{{ route("{$bereich}.learners.grades.index", $l->lernender_id) }}"
                                           aria-label="{{ __('Noten von :name', ['name' => $z->vorname.' '.$z->nachname]) }}"
                                           class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Noten') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $spalten }}" class="px-3 py-6 text-center text-muted">
                                        {{ $aktiveFilter > 0 ? __('Keine Lernenden passen zu den Filtern.') : __('Noch keine Lernenden erfasst.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
