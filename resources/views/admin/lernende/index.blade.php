<x-app-layout>
    <x-slot name="title">Lernende</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <h2 class="font-semibold text-xl text-text">Admin: Lernende</h2>
                <div class="text-sm text-muted pl-4 border-l border-border">
                    <span class="text-lg font-bold text-text tabular-nums">{{ $lernende->count() }}</span>
                    {{ $lernende->count() === 1 ? 'Lernender' : 'Lernende' }}
                </div>
            </div>
            <a href="{{ route('admin.benutzer.create', ['rolle' => 'lernender']) }}"
               class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary whitespace-nowrap text-sm">
                <span class="text-lg leading-none">+</span>
                Neuer Lernender
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Suche + Filter --}}
            @php
                $activeFilterCount = collect([$suche, $bbFilterId, $warnung, $inaktive === '1' ? '1' : ''])->filter()->count();
            @endphp
            <div class="glass rounded-2xl p-4">
                @if($activeFilterCount > 0)
                    <div class="mb-3 flex items-center gap-2 text-xs text-muted">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent text-white text-[10px] font-bold">{{ $activeFilterCount }}</span>
                        {{ $activeFilterCount === 1 ? 'aktiver Filter' : 'aktive Filter' }}
                        <a href="{{ route('admin.lernende.index') }}" class="text-accent hover:underline ml-2">alle zurücksetzen</a>
                    </div>
                @endif
                <form method="GET" action="{{ route('admin.lernende.index') }}"
                      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
                    <div class="lg:col-span-1">
                        <label for="suche" class="text-sm font-medium text-muted">Suche</label>
                        <input type="text" name="suche" id="suche" value="{{ $suche }}"
                               placeholder="Name oder E-Mail…"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                    </div>
                    <div>
                        <label for="berufsbildner_id" class="text-sm font-medium text-muted">Berufsbildner</label>
                        <select name="berufsbildner_id" id="berufsbildner_id" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="" @selected($bbFilterId === '')>Alle</option>
                            @foreach($berufsbildnerListe as $bb)
                                <option value="{{ $bb->berufsbildner_id }}" @selected($bbFilterId == $bb->berufsbildner_id)>
                                    {{ $bb->nachname }} {{ $bb->vorname }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="warnung" class="text-sm font-medium text-muted">Warnung</label>
                        <select name="warnung" id="warnung" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="" @selected($warnung === '')>Alle anzeigen</option>
                            <option value="keine_noten" @selected($warnung === 'keine_noten')>Kein Eintrag (30 Tage)</option>
                            <option value="tief_avg" @selected($warnung === 'tief_avg')>Ø unter 4.0</option>
                            <option value="ohne_betreuung" @selected($warnung === 'ohne_betreuung')>Ohne Berufsbildner</option>
                        </select>
                    </div>
                    <div>
                        <label for="inaktive" class="text-sm font-medium text-muted">Status</label>
                        <select name="inaktive" id="inaktive" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="0" @selected($inaktive !== '1')>Nur aktive</option>
                            <option value="1" @selected($inaktive === '1')>Inkl. inaktive</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit"
                                class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary whitespace-nowrap text-sm shrink-0">
                            Filtern
                        </button>
                        @if($suche || $warnung || $inaktive === '1' || $bbFilterId !== '')
                            <a href="{{ route('admin.lernende.index') }}"
                               class="px-3 py-2 h-10 rounded-xl glass-btn text-text flex items-center text-sm shrink-0">
                                ×
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
                                @php
                                    $sortLink = function ($col, $label) use ($sortBy, $sortDir) {
                                        $isActive = $sortBy === $col;
                                        $nextDir = $isActive && $sortDir === 'asc' ? 'desc' : 'asc';
                                        $arrow = !$isActive ? '<span class="text-muted/50">⇅</span>' : ($sortDir === 'asc' ? '↑' : '↓');
                                        $url = request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir]);
                                        return '<a href="'.$url.'" class="inline-flex items-center gap-1 hover:text-text '.($isActive ? 'text-text font-semibold' : '').'">'.$label.' '.$arrow.'</a>';
                                    };
                                @endphp
                                <th class="text-left p-3">{!! $sortLink('name', 'Name / Hinweise') !!}</th>
                                <th class="text-left p-3 whitespace-nowrap">Berufsbildner</th>
                                <th class="text-center p-3 whitespace-nowrap">Noten</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('last_note', 'Letzte Note') !!}</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('avg', 'Ø gesamt') !!}</th>
                                <th class="text-right p-3">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($lernende as $l)
                                @php
                                    $s = $stats[(int)$l->lernender_id] ?? null;
                                    $avg = $s?->avg_all ? (float)$s->avg_all : null;
                                    $nc = $avg !== null
                                        ? ($avg >= 5.0 ? 'text-green-700 dark:text-green-400'
                                            : ($avg >= 4.0 ? 'text-emerald-700 dark:text-emerald-400'
                                            : ($avg >= 3.5 ? 'text-yellow-700 dark:text-yellow-400'
                                            : 'text-red-600 dark:text-red-400')))
                                        : 'text-muted';

                                    // Warnungen berechnen
                                    $lastNote      = $s?->last_note ? \Carbon\Carbon::parse($s->last_note) : null;
                                    $daysSince     = $lastNote ? (int) $lastNote->diffInDays(now()) : null;
                                    $warnGelb      = $daysSince === null || $daysSince > 30;
                                    $warnRot       = $avg !== null && $avg < 4.0;
                                    $ohneBetreuer  = !$s?->betreuer;

                                    // Lehrende-Badge
                                    $lehrende      = $l->lehrende ? \Carbon\Carbon::parse($l->lehrende) : null;
                                    $lehrDaysLeft  = $lehrende ? (int) now()->diffInDays($lehrende, false) : null;
                                    $showLehrBadge = $lehrDaysLeft !== null && $lehrDaysLeft >= 0 && $lehrDaysLeft <= 60;
                                @endphp
                                @php $initials = strtoupper(mb_substr($l->vorname ?? '', 0, 1) . mb_substr($l->nachname ?? '', 0, 1)); @endphp
                                <tr class="group even:bg-bg/30 hover:bg-accent/5 transition-colors duration-100 {{ !$l->aktiv ? 'opacity-60' : '' }}">
                                    <td class="p-3">
                                        <div class="flex items-start gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0 mt-0.5">
                                                {{ $initials ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                        <a href="{{ route('admin.lernende.show', $l->lernender_id) }}"
                                           class="font-medium hover:text-accent">{{ $l->nachname }} {{ $l->vorname }}</a>
                                        <div class="text-xs text-muted mt-0.5">{{ $l->email }}</div>
                                        {{-- Badges: eigene Zeile --}}
                                        @if(!$l->aktiv || $ohneBetreuer || $showLehrBadge || $warnGelb || $warnRot)
                                            <div class="flex flex-wrap gap-1 mt-1.5">
                                                @if(!$l->aktiv)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-bg text-muted border border-border whitespace-nowrap">Inaktiv</span>
                                                @endif
                                                @if($ohneBetreuer && $l->aktiv)
                                                    <span title="Kein aktiver Berufsbildner zugewiesen. Unter «Betreuung» einen Berufsbildner zuteilen." class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300 whitespace-nowrap cursor-help">Ohne BB</span>
                                                @endif
                                                @if($showLehrBadge)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs whitespace-nowrap
                                                        {{ $lehrDaysLeft <= 14 ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                                                            : ($lehrDaysLeft <= 30 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-300'
                                                            : 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300') }}">
                                                        Lehrende in {{ $lehrDaysLeft }}d
                                                    </span>
                                                @endif
                                                @if($warnGelb)
                                                    <span title="{{ $daysSince === null ? 'Diese Lernende hat noch keine Noten erfasst.' : 'Letzter Noteneintrag vor '.$daysSince.' Tagen. Lernender bitte zur Erfassung anhalten.' }}" class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300 whitespace-nowrap cursor-help">
                                                        {{ $daysSince === null ? 'Keine Noten' : $daysSince . 'd kein Eintrag' }}
                                                    </span>
                                                @endif
                                                @if($warnRot)
                                                    <span title="Gesamtdurchschnitt liegt unter 4.0 — kritisch für Lehrabschluss." class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 whitespace-nowrap cursor-help">
                                                        Ø {{ number_format($avg, 1) }}
                                                    </span>
                                                @endif
                                            </div>
                                        @endif
                                            </div>{{-- /min-w-0 --}}
                                        </div>{{-- /flex --}}
                                    </td>
                                    <td class="p-3 text-muted text-sm">
                                        @if($s?->betreuer)
                                            {{ $s->betreuer->nachname }} {{ $s->betreuer->vorname }}
                                        @else
                                            <span class="italic text-muted">–</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        {{ $s?->noten_count ?? 0 }}
                                    </td>
                                    <td class="p-3 text-center text-muted whitespace-nowrap">
                                        @if($lastNote)
                                            {{ $lastNote->format('d.m.Y') }}
                                        @else
                                            –
                                        @endif
                                    </td>
                                    <td class="p-3 text-center font-semibold {{ $nc }}">
                                        {{ $avg !== null ? number_format($avg, 2) : '–' }}
                                    </td>
                                    <td class="p-3 text-right">
                                        {{-- Aktionen permanent sichtbar (Touch-Geräte!), dezent bis hover --}}
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.lernende.show', $l->lernender_id) }}"
                                               class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap"
                                               title="Profil mit Notenverlauf, Betreuung und allen Aktionen">
                                                Profil
                                            </a>
                                            <a href="{{ route('admin.lernende.betreuung', $l->lernender_id) }}"
                                               class="inline-flex px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">
                                                Betreuung
                                            </a>
                                            <a href="{{ route('admin.lernende.tracks', $l->lernender_id) }}"
                                               class="inline-flex px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">
                                                Tracks
                                            </a>
                                            <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                                               class="px-3 py-1.5 rounded-xl bg-accent text-white text-xs np-btn-primary whitespace-nowrap">
                                                Noten
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-muted">
                                        @if($suche)
                                            Keine Lernenden für «{{ $suche }}» gefunden.
                                        @else
                                            Keine Lernenden gefunden.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($lernende->isNotEmpty())
                    <div class="px-4 py-2 border-t border-border text-xs text-muted">
                        {{ $lernende->count() }} Lernende
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
