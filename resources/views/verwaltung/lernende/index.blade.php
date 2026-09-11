<x-app-layout>
    <x-slot name="title">Lernende</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Lernende" :zaehler="$zeilen->count()">
            <x-slot:aktionen>
                <a href="{{ route("{$bereich}.grades.export_all") }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Alle Noten (CSV)
                </a>
                <a href="{{ route("{$bereich}.learners.create") }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span>
                    Lernender erfassen
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
            $pfeil = ! $aktiv ? '<span class="text-muted/50" aria-hidden="true">⇅</span>' : ($filter['dir'] === 'asc' ? '↑' : '↓');

            return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $spalte, 'dir' => $dir])).'" class="inline-flex items-center gap-1 hover:text-text '.($aktiv ? 'text-text font-semibold' : '').'">'.e($label).' '.$pfeil.'</a>';
        };

        $auswahl = 'h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-40';
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            <x-filterleiste :action="route($bereich.'.learners.index')" suche-name="suche" :suche-wert="$filter['suche']"
                             suche-platzhalter="Name, E-Mail, Benutzername" :zaehler="$zeilen->count()"
                             :zurueck="route($bereich.'.learners.index')" :aktive-filter="$aktiveFilter" :aktive-weitere="$aktiveWeitere">
                <x-slot:hidden>
                    <input type="hidden" name="sort" value="{{ $filter['sort'] }}">
                    <input type="hidden" name="dir" value="{{ $filter['dir'] }}">
                </x-slot:hidden>

                <label for="lehrberuf_id" class="sr-only">Lehrberuf</label>
                <select name="lehrberuf_id" id="lehrberuf_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">Alle Lehrberufe</option>
                    @foreach($lehrberufe as $lb)
                        <option value="{{ $lb->lehrberuf_id }}" @selected($filter['lehrberuf_id'] === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                    @endforeach
                </select>

                <label for="lehrjahr" class="sr-only">Lehrjahr</label>
                <select name="lehrjahr" id="lehrjahr" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">Alle Lehrjahre</option>
                    @foreach([1, 2, 3, 4] as $jahr)
                        <option value="{{ $jahr }}" @selected($filter['lehrjahr'] === $jahr)>{{ $jahr }}. Lehrjahr</option>
                    @endforeach
                </select>

                @if($bereich === 'admin')
                    <label for="berufsbildner_id" class="sr-only">Berufsbildner</label>
                    <select name="berufsbildner_id" id="berufsbildner_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">Alle Berufsbildner</option>
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
                        <option value="">BMS: alle</option>
                        <option value="ja" @selected($filter['bms'] === 'ja')>Mit BMS</option>
                        <option value="nein" @selected($filter['bms'] === 'nein')>Ohne BMS</option>
                    </select>

                    <label for="warnung" class="sr-only">Warnung</label>
                    <select name="warnung" id="warnung" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">Warnung: alle</option>
                        <option value="tief_avg" @selected($filter['warnung'] === 'tief_avg')>Ø unter 4.0</option>
                        <option value="keine_noten" @selected($filter['warnung'] === 'keine_noten')>Kein Eintrag seit 30 Tagen</option>
                        @if($bereich === 'admin')
                            <option value="ohne_betreuung" @selected($filter['warnung'] === 'ohne_betreuung')>Ohne Berufsbildner</option>
                            <option value="ohne_track" @selected($filter['warnung'] === 'ohne_track')>Ohne aktiven Track</option>
                        @endif
                    </select>

                    <label for="inaktive" class="sr-only">Status</label>
                    <select name="inaktive" id="inaktive" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="0" @selected(! $filter['inaktive'])>Nur aktive</option>
                        <option value="1" @selected($filter['inaktive'])>Inkl. inaktive</option>
                    </select>
                </x-slot:weitere>
            </x-filterleiste>

            {{-- Kartenansicht mobil: Tabelle mit rechtsbündigen Aktionen liesse sie ausserhalb des Sichtbereichs --}}
            <div class="md:hidden divide-y divide-border rounded-xl border border-border bg-card overflow-hidden">
                @forelse($zeilen as $z)
                    @php
                        $l = $z->lernender;
                        $initialen = strtoupper(mb_substr($z->vorname ?? '', 0, 1).mb_substr($z->nachname ?? '', 0, 1));
                    @endphp
                    <div class="p-4 {{ $l->benutzer->aktiv ? '' : 'opacity-60' }}">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                                {{ $initialen ?: '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-text">{{ $z->nachname }} {{ $z->vorname }}</div>
                                <div class="text-xs text-muted">{{ $l->lehrberuf?->name ?? '–' }}{{ $z->lehrjahr ? ' · '.$z->lehrjahr.'. Lehrjahr' : '' }}</div>
                                @if($bereich === 'admin')
                                    <div class="text-xs text-muted mt-0.5">Berufsbildner: {{ $z->betreuer ? $z->betreuer->nachname.' '.$z->betreuer->vorname : '–' }}</div>
                                @endif
                                <div class="flex items-center gap-3 mt-1.5 text-xs text-muted tabular-nums">
                                    <span>{{ $z->anzahl }} Noten</span>
                                    <span class="font-semibold">Ø <x-note :wert="$z->avg" :stellen="2" /></span>
                                    @if($z->ungelesen > 0)
                                        <span class="inline-flex px-1.5 py-0.5 rounded-full font-semibold bg-accent text-accent-contrast">{{ $z->ungelesen }} neu</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 mt-3">
                            <a href="{{ route("{$bereich}.learners.show", $l->lernender_id) }}"
                               class="flex-1 inline-flex items-center justify-center px-3 min-h-[36px] rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">Profil</a>
                            <a href="{{ route("{$bereich}.learners.grades.index", $l->lernender_id) }}"
                               class="flex-1 inline-flex items-center justify-center px-3 min-h-[36px] rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">Noten</a>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-sm text-muted">Keine Lernenden gefunden.</div>
                @endforelse
            </div>

            <div class="hidden md:block rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="sticky top-0 z-10 bg-surface-2">
                            <tr>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{!! $sortLink('name', 'Name') !!}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{!! $sortLink('lehrjahr', 'Lehrberuf / Lj') !!}</th>
                                @if($bereich === 'admin')
                                    <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">Berufsbildner</th>
                                @endif
                                <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">Noten</th>
                                <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">{!! $sortLink('last_note', 'Letzte Note') !!}</th>
                                <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">{!! $sortLink('avg', 'Ø gesamt') !!}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">Status</th>
                                <th scope="col" class="h-9 px-3 text-right"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($zeilen as $z)
                                @php
                                    $l = $z->lernender;
                                    $initialen = strtoupper(mb_substr($z->vorname ?? '', 0, 1).mb_substr($z->nachname ?? '', 0, 1));
                                    $tagSeit = $z->lastNote ? (int) \Carbon\Carbon::parse($z->lastNote)->diffInDays(now()) : null;
                                    $zielUrl = route("{$bereich}.learners.show", $l->lernender_id);
                                @endphp
                                <tr class="group h-11 cursor-pointer border-b border-border last:border-0 hover:bg-surface-2/60 {{ $l->benutzer->aktiv ? '' : 'opacity-60' }}"
                                    onclick="window.location='{{ $zielUrl }}'">
                                    <td class="px-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-8 h-8 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0" aria-hidden="true">
                                                {{ $initialen ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ $zielUrl }}" title="{{ $l->benutzer->email }}" class="font-medium text-text hover:text-accent">{{ $z->nachname }} {{ $z->vorname }}</a>
                                                @if($filter['suche'] !== '')
                                                    <div class="text-xs text-muted truncate">{{ $l->benutzer->email }}</div>
                                                @endif
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
                                    <td class="px-3 text-right">{{ $z->anzahl }}</td>
                                    <td class="px-3 text-right text-muted whitespace-nowrap">
                                        {{ $z->lastNote ? ((int) \Carbon\Carbon::parse($z->lastNote)->diffInDays(now()) === 0 ? 'heute' : 'vor '.$tagSeit.'d') : '–' }}
                                    </td>
                                    <td class="px-3 text-right">
                                        <x-note :wert="$z->avg" :stellen="2" />
                                    </td>
                                    <td class="px-3 text-left">
                                        <div class="flex flex-wrap gap-1">
                                            @if(! $l->benutzer->aktiv)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-surface-2 text-muted border border-border">Inaktiv</span>
                                            @endif
                                            @if($z->bms)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-accent/10 text-accent">BMS</span>
                                            @endif
                                            @if($bereich === 'admin' && ! $z->betreuer && $l->benutzer->aktiv)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-note-knapp/14 text-note-knapp">Ohne BB</span>
                                            @endif
                                            @if($tagSeit === null || $tagSeit > 30)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-note-knapp/14 text-note-knapp">
                                                    {{ $tagSeit === null ? 'Keine Noten' : $tagSeit.'d kein Eintrag' }}
                                                </span>
                                            @endif
                                            @if($z->avg !== null && $z->avg < 4.0)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-note-ungenuegend/10 text-note-ungenuegend">Ø unter 4.0</span>
                                            @endif
                                            @if($z->ungelesen > 0)
                                                <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs font-semibold bg-accent text-accent-contrast">{{ $z->ungelesen }} neu</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 text-right" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                            <a href="{{ $zielUrl }}"
                                               class="inline-flex items-center px-3 min-h-9 rounded-lg border border-border text-xs hover:bg-surface-2 whitespace-nowrap">Profil</a>
                                            <a href="{{ route("{$bereich}.learners.grades.index", $l->lernender_id) }}"
                                               class="inline-flex items-center px-3 min-h-9 rounded-lg bg-accent text-accent-contrast text-xs np-btn-primary whitespace-nowrap">Noten</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $bereich === 'admin' ? 8 : 7 }}" class="p-6 text-center text-muted">
                                        Keine Lernenden gefunden.
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
