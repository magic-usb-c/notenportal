<x-app-layout>
    <x-slot name="title">Lernende</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <div class="flex items-center gap-4">
                <h2 class="font-semibold text-xl text-text">Lernende</h2>
                <div class="text-sm text-muted pl-4 border-l border-border">
                    <span class="text-lg font-bold text-text tabular-nums">{{ $zeilen->count() }}</span>
                    {{ $zeilen->count() === 1 ? 'Lernender' : 'Lernende' }}
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route("{$bereich}.noten.export_alle") }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Alle Noten (CSV)
                </a>
                <a href="{{ route("{$bereich}.lernende.create") }}"
                   class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary whitespace-nowrap text-sm">
                    <span class="text-lg leading-none">+</span>
                    Lernender erfassen
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $aktiveFilter = collect([
            $filter['suche'], $filter['lehrberuf_id'], $filter['lehrjahr'], $filter['bms'],
            $filter['warnung'], $filter['inaktive'], $filter['berufsbildner_id'],
        ])->filter()->count();

        $sortLink = function (string $spalte, string $label) use ($filter) {
            $aktiv = $filter['sort'] === $spalte;
            $dir = $aktiv && $filter['dir'] === 'asc' ? 'desc' : 'asc';
            $pfeil = ! $aktiv ? '<span class="text-muted/50" aria-hidden="true">⇅</span>' : ($filter['dir'] === 'asc' ? '↑' : '↓');

            return '<a href="'.e(request()->fullUrlWithQuery(['sort' => $spalte, 'dir' => $dir])).'" class="inline-flex items-center gap-1 hover:text-text '.($aktiv ? 'text-text font-semibold' : '').'">'.e($label).' '.$pfeil.'</a>';
        };

        $notenfarbe = fn (?float $v) => $v === null ? 'text-muted'
            : ($v >= 5.0 ? 'text-green-700 dark:text-green-400'
            : ($v >= 4.0 ? 'text-emerald-700 dark:text-emerald-400'
            : ($v >= 3.5 ? 'text-yellow-700 dark:text-yellow-400'
            : 'text-red-600 dark:text-red-400')));

        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:border-ring';
    @endphp

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Suche + Filter --}}
            <div class="glass rounded-2xl p-4">
                <form method="GET" action="{{ route("{$bereich}.lernende.index") }}"
                      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                    <input type="hidden" name="sort" value="{{ $filter['sort'] }}">
                    <input type="hidden" name="dir" value="{{ $filter['dir'] }}">

                    <div>
                        <label for="suche" class="text-xs uppercase tracking-widest text-muted font-medium">Suche</label>
                        <input type="search" name="suche" id="suche" value="{{ $filter['suche'] }}"
                               placeholder="Name, E-Mail, Benutzername" class="{{ $feld }}">
                    </div>

                    <div>
                        <label for="lehrberuf_id" class="text-xs uppercase tracking-widest text-muted font-medium">Lehrberuf</label>
                        <select name="lehrberuf_id" id="lehrberuf_id" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}" @selected($filter['lehrberuf_id'] === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="lehrjahr" class="text-xs uppercase tracking-widest text-muted font-medium">Lehrjahr</label>
                        <select name="lehrjahr" id="lehrjahr" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            @foreach([1, 2, 3, 4] as $jahr)
                                <option value="{{ $jahr }}" @selected($filter['lehrjahr'] === $jahr)>{{ $jahr }}. Lehrjahr</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="bms" class="text-xs uppercase tracking-widest text-muted font-medium">BMS</label>
                        <select name="bms" id="bms" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            <option value="ja" @selected($filter['bms'] === 'ja')>Mit BMS</option>
                            <option value="nein" @selected($filter['bms'] === 'nein')>Ohne BMS</option>
                        </select>
                    </div>

                    <div>
                        <label for="warnung" class="text-xs uppercase tracking-widest text-muted font-medium">Warnung</label>
                        <select name="warnung" id="warnung" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            <option value="tief_avg" @selected($filter['warnung'] === 'tief_avg')>Ø unter 4.0</option>
                            <option value="keine_noten" @selected($filter['warnung'] === 'keine_noten')>Kein Eintrag seit 30 Tagen</option>
                            @if($bereich === 'admin')
                                <option value="ohne_betreuung" @selected($filter['warnung'] === 'ohne_betreuung')>Ohne Berufsbildner</option>
                            @endif
                        </select>
                    </div>

                    @if($bereich === 'admin')
                        <div>
                            <label for="berufsbildner_id" class="text-xs uppercase tracking-widest text-muted font-medium">Berufsbildner</label>
                            <select name="berufsbildner_id" id="berufsbildner_id" onchange="this.form.submit()" class="{{ $feld }}">
                                <option value="">Alle</option>
                                @foreach($berufsbildnerListe as $bb)
                                    <option value="{{ $bb->berufsbildner_id }}" @selected($filter['berufsbildner_id'] === (int) $bb->berufsbildner_id)>
                                        {{ $bb->benutzer->nachname }} {{ $bb->benutzer->vorname }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div>
                        <label for="inaktive" class="text-xs uppercase tracking-widest text-muted font-medium">Status</label>
                        <select name="inaktive" id="inaktive" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="0" @selected(! $filter['inaktive'])>Nur aktive</option>
                            <option value="1" @selected($filter['inaktive'])>Inkl. inaktive</option>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm">Filtern</button>
                        @if($aktiveFilter > 0)
                            <a href="{{ route("{$bereich}.lernende.index") }}"
                               class="inline-flex items-center gap-2 px-3 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent text-white text-[10px] font-bold">{{ $aktiveFilter }}</span>
                                Zurücksetzen
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="glass rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-bg text-muted shadow-xs">
                            <tr>
                                <th class="text-left p-3">{!! $sortLink('name', 'Name') !!}</th>
                                <th class="text-left p-3 whitespace-nowrap">{!! $sortLink('lehrjahr', 'Lehrberuf / Lehrjahr') !!}</th>
                                @if($bereich === 'admin')
                                    <th class="text-left p-3 whitespace-nowrap">Berufsbildner</th>
                                @endif
                                <th class="text-center p-3 whitespace-nowrap">Noten</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('last_note', 'Letzte Note') !!}</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('avg', 'Ø gesamt') !!}</th>
                                <th class="text-right p-3"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($zeilen as $z)
                                @php
                                    $l = $z->lernender;
                                    $initialen = strtoupper(mb_substr($z->vorname ?? '', 0, 1).mb_substr($z->nachname ?? '', 0, 1));
                                    $tagSeit = $z->lastNote ? (int) \Carbon\Carbon::parse($z->lastNote)->diffInDays(now()) : null;
                                @endphp
                                <tr class="even:bg-bg/30 hover:bg-accent/5 transition-colors duration-100 {{ $l->benutzer->aktiv ? '' : 'opacity-60' }}">
                                    <td class="p-3">
                                        <div class="flex items-start gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0 mt-0.5" aria-hidden="true">
                                                {{ $initialen ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <a href="{{ route("{$bereich}.lernende.show", $l->lernender_id) }}"
                                                   class="font-medium hover:text-accent">{{ $z->nachname }} {{ $z->vorname }}</a>
                                                <div class="text-xs text-muted mt-0.5">{{ $l->benutzer->email }}</div>
                                                <div class="flex flex-wrap gap-1 mt-1.5">
                                                    @if(! $l->benutzer->aktiv)
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-bg text-muted border border-border">Inaktiv</span>
                                                    @endif
                                                    @if($z->bms)
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-accent/10 text-accent">BMS</span>
                                                    @endif
                                                    @if($bereich === 'admin' && ! $z->betreuer && $l->benutzer->aktiv)
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300">Ohne BB</span>
                                                    @endif
                                                    @if($tagSeit === null || $tagSeit > 30)
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300">
                                                            {{ $tagSeit === null ? 'Keine Noten' : $tagSeit.'d kein Eintrag' }}
                                                        </span>
                                                    @endif
                                                    @if($z->avg !== null && $z->avg < 4.0)
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Ø unter 4.0</span>
                                                    @endif
                                                    @if($z->ungelesen > 0)
                                                        <span class="inline-flex px-1.5 py-0.5 rounded-full text-xs font-semibold bg-accent text-white">{{ $z->ungelesen }} neu</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 text-sm">
                                        <div class="text-text">{{ $l->lehrberuf?->name ?? '–' }}</div>
                                        <div class="text-xs text-muted">{{ $z->lehrjahr ? $z->lehrjahr.'. Lehrjahr' : '–' }}</div>
                                    </td>
                                    @if($bereich === 'admin')
                                        <td class="p-3 text-sm text-muted">
                                            {{ $z->betreuer ? $z->betreuer->nachname.' '.$z->betreuer->vorname : '–' }}
                                        </td>
                                    @endif
                                    <td class="p-3 text-center tabular-nums">{{ $z->anzahl }}</td>
                                    <td class="p-3 text-center text-muted whitespace-nowrap">
                                        {{ $z->lastNote ? \Carbon\Carbon::parse($z->lastNote)->format('d.m.Y') : '–' }}
                                    </td>
                                    <td class="p-3 text-center font-semibold tabular-nums {{ $notenfarbe($z->avg) }}">
                                        {{ $z->avg !== null ? number_format($z->avg, 2) : '–' }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route("{$bereich}.lernende.show", $l->lernender_id) }}"
                                               class="inline-flex items-center px-3 min-h-[36px] rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">Profil</a>
                                            <a href="{{ route("{$bereich}.lernende.noten.index", $l->lernender_id) }}"
                                               class="inline-flex items-center px-3 min-h-[36px] rounded-xl bg-accent text-white text-xs np-btn-primary whitespace-nowrap">Noten</a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $bereich === 'admin' ? 7 : 6 }}" class="p-6 text-center text-muted">
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
