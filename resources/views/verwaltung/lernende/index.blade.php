<x-app-layout>
    <x-slot name="title">{{ __('Lernende') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Lernende')" :zaehler="$zeilen->count()">
            <x-slot:aktionen>
                <a href="{{ route("{$bereich}.grades.export_all") }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    {{ __('Alle Noten (CSV)') }}
                </a>
                <a href="{{ route("{$bereich}.learners.create") }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span>
                    {{ __('Lernender erfassen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $aktivePrimaer = collect([$filter['suche'], $filter['lehrberuf_id'], $filter['lehrjahr'], $filter['berufsbildner_id']])->filter()->count();
        $aktiveWeitere = collect([$filter['bms'], $filter['warnung'], $filter['inaktive']])->filter()->count();
        $aktiveFilter = $aktivePrimaer + $aktiveWeitere;

        $sortLink = function (string $spalte, string $label) use ($filter) {
            $aktiv = $filter['sort'] === $spalte;
            $dir = $aktiv && $filter['dir'] === 'asc' ? 'desc' : 'asc';
            $pfeil = ! $aktiv ? '<span class="invisible text-muted group-hover/sort:visible group-focus-visible/sort:visible" aria-hidden="true">↑</span>' : '<span aria-hidden="true">'.($filter['dir'] === 'asc' ? '↑' : '↓').'</span>';

            return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $spalte, 'dir' => $dir])).'" class="group/sort inline-flex items-center gap-1 hover:text-text '.($aktiv ? 'text-text font-semibold' : '').'">'.e($label).' '.$pfeil.'</a>';
        };
        // Die Richtung sagt aria-sort am Spaltenkopf an, der Pfeil ist nur fürs Auge
        $ariaSort = fn (string $spalte) => $filter['sort'] === $spalte ? ($filter['dir'] === 'desc' ? 'descending' : 'ascending') : 'none';

        $auswahl = 'h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-40';

        $tageSeit = fn ($z) => $z->lastNote ? (int) \Carbon\Carbon::parse($z->lastNote)->diffInDays(now()) : null;
        $wann = fn (?int $tage) => match (true) {
            $tage === null => '–',
            $tage === 0 => __('heute'),
            default => __('vor :tage', ['tage' => $tage.'d']),
        };
        // Die Karten haben keine Spaltenköpfe: dieselben Sortierungen als Auswahl
        $sortierungen = [
            ['name', 'asc', __('Name A–Z')],
            ['name', 'desc', __('Name Z–A')],
            ['lehrjahr', 'asc', __('Lehrjahr aufsteigend')],
            ['lehrjahr', 'desc', __('Lehrjahr absteigend')],
            ['last_note', 'desc', __('Neueste Note zuerst')],
            ['last_note', 'asc', __('Älteste Note zuerst')],
            ['avg', 'asc', __('Tiefster Ø zuerst')],
            ['avg', 'desc', __('Höchster Ø zuerst')],
        ];
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <x-filterleiste :action="route($bereich.'.learners.index')" suche-name="suche" :suche-wert="$filter['suche']"
                             :suche-platzhalter="__('Name, E-Mail, Benutzername')" :zaehler="$zeilen->count()"
                             :zurueck="route($bereich.'.learners.index')" :aktive-filter="$aktiveFilter" :aktive-weitere="$aktiveWeitere">
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
                    <label for="bms" class="sr-only">BMS</label>
                    <select name="bms" id="bms" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('BMS: alle') }}</option>
                        <option value="ja" @selected($filter['bms'] === 'ja')>{{ __('Mit BMS') }}</option>
                        <option value="nein" @selected($filter['bms'] === 'nein')>{{ __('Ohne BMS') }}</option>
                    </select>

                    <label for="warnung" class="sr-only">{{ __('Warnung') }}</label>
                    <select name="warnung" id="warnung" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Warnung: alle') }}</option>
                        <option value="tief_avg" @selected($filter['warnung'] === 'tief_avg')>{{ __('Ø unter :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}</option>
                        <option value="keine_noten" @selected($filter['warnung'] === 'keine_noten')>{{ __('Kein Eintrag seit 30 Tagen') }}</option>
                        @if($bereich === 'admin')
                            <option value="ohne_betreuung" @selected($filter['warnung'] === 'ohne_betreuung')>{{ __('Ohne Berufsbildner') }}</option>
                            <option value="ohne_track" @selected($filter['warnung'] === 'ohne_track')>{{ __('Ohne aktiven Track') }}</option>
                        @endif
                    </select>

                    <label for="inaktive" class="sr-only">{{ __('Status') }}</label>
                    <select name="inaktive" id="inaktive" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="0" @selected(! $filter['inaktive'])>{{ __('Nur aktive') }}</option>
                        <option value="1" @selected($filter['inaktive'])>{{ __('Inkl. inaktive') }}</option>
                    </select>
                </x-slot:weitere>
            </x-filterleiste>

            {{-- Karten oder Tabelle je nach Breite des Inhalts, nicht des Fensters (Seitenleiste). Die Tabelle
                 braucht mit Aktionen rund 900 px, darunter lägen die Aktionen ausserhalb des Sichtbereichs. --}}
            <div class="@container flex flex-col gap-3">
            @if($zeilen->count() > 1)
                <div class="flex items-center justify-end gap-2 @4xl:hidden">
                    <label for="sortierung" class="text-sm text-muted">{{ __('Sortieren') }}</label>
                    <select id="sortierung" x-data x-on:change="window.location.href = $el.value" class="{{ $auswahl }} min-w-0">
                        @foreach($sortierungen as [$sort, $dir, $label])
                            <option value="{{ request()->fullUrlWithQuery(['sort' => $sort, 'dir' => $dir]) }}" @selected($filter['sort'] === $sort && $filter['dir'] === $dir)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div data-ansicht="karten" class="@4xl:hidden divide-y divide-border rounded-xl border border-border bg-card overflow-hidden">
                @forelse($zeilen as $z)
                    @php
                        $l = $z->lernender;
                        $initialen = strtoupper(mb_substr($z->vorname ?? '', 0, 1).mb_substr($z->nachname ?? '', 0, 1));
                        $tagSeit = $tageSeit($z);
                    @endphp
                    <div class="p-4 {{ $l->benutzer->aktiv ? '' : 'opacity-60' }}">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-accent/10 text-accent-text text-xs font-bold flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                                {{ $initialen ?: '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-text">{{ $z->nachname }} {{ $z->vorname }}</div>
                                <div class="text-xs text-muted">{{ $l->lehrberuf?->name ?? '–' }}{{ $z->lehrjahr ? ' · '.__(':jahr. Lehrjahr', ['jahr' => $z->lehrjahr]) : '' }}</div>
                                @if($bereich === 'admin')
                                    <div class="text-xs text-muted mt-0.5">{{ __('Berufsbildner: :name', ['name' => $z->betreuer ? $z->betreuer->nachname.' '.$z->betreuer->vorname : '–']) }}</div>
                                @endif
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1.5 text-xs text-muted tabular-nums">
                                    <span>{{ __(':anzahl Noten', ['anzahl' => $z->anzahl]) }}</span>
                                    <span class="font-semibold">Ø <x-note :wert="$z->avg" :stellen="2" /></span>
                                    @if($tagSeit !== null)
                                        <span>{{ __('Letzte Note :wann', ['wann' => $wann($tagSeit)]) }}</span>
                                    @endif
                                </div>
                                @php
                                    $status = trim(view('verwaltung.lernende._status', compact('l', 'z', 'tagSeit', 'grenze', 'bereich'))->render());
                                @endphp
                                @if($status !== '')
                                    <div class="flex flex-wrap gap-1 mt-2">{!! $status !!}</div>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-3">
                            <a href="{{ route("{$bereich}.learners.show", $l->lernender_id) }}"
                               class="flex-1 inline-flex items-center justify-center px-3 min-h-[36px] rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">{{ __('Profil') }}</a>
                            <a href="{{ route("{$bereich}.learners.grades.index", $l->lernender_id) }}"
                               class="flex-1 inline-flex items-center justify-center px-3 min-h-[36px] rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">{{ __('Noten') }}</a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-muted">{{ __('Keine Lernenden gefunden.') }}</div>
                @endforelse
            </div>

            <div data-ansicht="tabelle" class="hidden @4xl:block rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="sticky top-0 z-10 bg-surface-2">
                            <tr>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted" aria-sort="{{ $ariaSort('name') }}">{!! $sortLink('name', __('Name')) !!}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap" aria-sort="{{ $ariaSort('lehrjahr') }}">{!! $sortLink('lehrjahr', __('Lehrberuf / Lj')) !!}</th>
                                @if($bereich === 'admin')
                                    <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Berufsbildner') }}</th>
                                @endif
                                <th scope="col" class="hidden h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap @6xl:table-cell">{{ __('Noten') }}</th>
                                <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap" aria-sort="{{ $ariaSort('last_note') }}">{!! $sortLink('last_note', __('Letzte Note')) !!}</th>
                                <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap" aria-sort="{{ $ariaSort('avg') }}">{!! $sortLink('avg', __('Ø gesamt')) !!}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{{ __('Status') }}</th>
                                <th scope="col" class="h-9 px-3 text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($zeilen as $z)
                                @php
                                    $l = $z->lernender;
                                    $initialen = strtoupper(mb_substr($z->vorname ?? '', 0, 1).mb_substr($z->nachname ?? '', 0, 1));
                                    $tagSeit = $tageSeit($z);
                                    $zielUrl = route("{$bereich}.learners.show", $l->lernender_id);
                                @endphp
                                <tr class="group h-11 cursor-pointer border-b border-border last:border-0 hover:bg-surface-2/60 {{ $l->benutzer->aktiv ? '' : 'opacity-60' }}"
                                    onclick="window.location='{{ $zielUrl }}'">
                                    <td class="px-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-8 h-8 rounded-full bg-accent/10 text-accent-text text-xs font-bold flex items-center justify-center shrink-0" aria-hidden="true">
                                                {{ $initialen ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ $zielUrl }}" title="{{ $l->benutzer->email }}" class="font-medium text-text hover:text-accent-text">{{ $z->nachname }} {{ $z->vorname }}</a>
                                                @if($filter['suche'] !== '')
                                                    <div class="text-xs text-muted truncate">{{ $l->benutzer->email }}</div>
                                                @endif
                                                <div class="text-xs text-muted tabular-nums @6xl:hidden">{{ __(':anzahl Noten', ['anzahl' => $z->anzahl]) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 text-left whitespace-nowrap">
                                        <span title="{{ $l->lehrberuf?->name }}">{{ $l->lehrberuf?->kuerzel ?? '–' }}</span>
                                        <span class="text-muted">· {{ $z->lehrjahr ?: '–' }}</span>
                                    </td>
                                    @if($bereich === 'admin')
                                        <td class="px-3 text-left text-muted whitespace-nowrap">
                                            {{ $z->betreuer ? $z->betreuer->nachname.' '.$z->betreuer->vorname : '–' }}
                                        </td>
                                    @endif
                                    <td class="hidden px-3 text-right @6xl:table-cell">{{ $z->anzahl }}</td>
                                    <td class="px-3 text-right text-muted whitespace-nowrap">{{ $wann($tagSeit) }}</td>
                                    <td class="px-3 text-right">
                                        <x-note :wert="$z->avg" :stellen="2" />
                                    </td>
                                    <td class="px-3 text-left">
                                        <div class="flex flex-wrap gap-1">@include('verwaltung.lernende._status')</div>
                                    </td>
                                    <td class="px-3 text-right" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 pointer-coarse:opacity-100">
                                            {{-- Zum Profil führen schon Zeile und Name --}}
                                            <a href="{{ route("{$bereich}.learners.grades.index", $l->lernender_id) }}"
                                               class="np-ziel inline-flex h-8 items-center rounded-lg glass-btn px-3 text-xs font-medium text-text whitespace-nowrap">{{ __('Noten') }}</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $bereich === 'admin' ? 8 : 7 }}" class="p-6 text-center text-muted">
                                        {{ __('Keine Lernenden gefunden.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            </div>

        </div>
    </div>
</x-app-layout>
