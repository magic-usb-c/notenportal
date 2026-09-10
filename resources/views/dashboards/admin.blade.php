<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-6">
            <div>
                <h2 class="font-semibold text-xl text-text">Admin Dashboard</h2>
                <p class="text-xs text-muted mt-0.5">{{ \Carbon\Carbon::now()->locale('de_CH')->isoFormat('dddd, D. MMMM YYYY') }}</p>
            </div>
            <div class="hidden sm:flex items-center gap-4 text-right">
                <a href="{{ route('admin.lernende.index') }}" class="hover:text-accent group">
                    <div class="text-[10px] uppercase tracking-widest text-muted">Lernende</div>
                    <div class="text-lg font-bold text-text tabular-nums group-hover:text-accent">{{ $lernendCount }}</div>
                </a>
                <div class="w-px h-8 bg-border"></div>
                <a href="{{ route('admin.berufsbildner.index') }}" class="hover:text-accent group">
                    <div class="text-[10px] uppercase tracking-widest text-muted">Berufsbildner</div>
                    <div class="text-lg font-bold text-text tabular-nums group-hover:text-accent">{{ $berufsbildnerCount }}</div>
                </a>
                <div class="w-px h-8 bg-border"></div>
                <div>
                    <div class="text-[10px] uppercase tracking-widest text-muted">Noten gesamt</div>
                    <div class="text-lg font-bold text-text tabular-nums">{{ $noteCount }}</div>
                </div>
                <div class="w-px h-8 bg-border"></div>
                <div>
                    <div class="text-[10px] uppercase tracking-widest text-muted">{{ $currentSemester?->bezeichnung ?? 'Akt. Sem.' }}</div>
                    <div class="text-lg font-bold text-text tabular-nums">{{ $notesThisSemester }}</div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Begrüssung kompakt --}}
            <div class="flex items-baseline gap-2">
                <h3 class="text-base font-semibold text-text">Willkommen, {{ auth()->user()->vorname }}</h3>
                <span class="text-xs text-muted">Administrator</span>
            </div>

            {{-- Quick-Actions: die wichtigsten Admin-Workflows --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <a href="{{ route('admin.benutzer.create') }}"
                   class="glass glass-lift rounded-2xl p-4 border-accent/40 hover:border-accent group">
                    <div class="w-9 h-9 rounded-xl bg-accent text-white flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>
                    <div class="font-semibold text-sm text-text">Neuer Benutzer</div>
                    <div class="text-xs text-muted mt-0.5">Lernender / BB / Admin</div>
                </a>
                <a href="{{ route('admin.lernende.index') }}"
                   class="glass glass-lift rounded-2xl p-4 group">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                    </div>
                    <div class="font-semibold text-sm text-text">Betreuungen</div>
                    <div class="text-xs text-muted mt-0.5">Lernende & BB zuordnen</div>
                </a>
                <a href="{{ route('admin.stammdaten.semester.create') }}"
                   class="glass glass-lift rounded-2xl p-4 group">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="font-semibold text-sm text-text">Neues Semester</div>
                    <div class="text-xs text-muted mt-0.5">Stammdaten erfassen</div>
                </a>
                <a href="{{ route('admin.berichte.noten') }}"
                   class="glass glass-lift rounded-2xl p-4 group">
                    <div class="w-9 h-9 rounded-xl bg-accent/10 text-accent flex items-center justify-center mb-2 group-hover:scale-110 transition-transform">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="font-semibold text-sm text-text">Berichte</div>
                    <div class="text-xs text-muted mt-0.5">Schulweite Auswertung</div>
                </a>
            </div>

            {{-- Erfassungs-Aktivität: Noten pro Woche --}}
            @php $maxAkt = $aktivitaet->max('count'); @endphp
            <div class="glass rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h3 class="font-semibold text-text">Erfassungs-Aktivität</h3>
                    <span class="text-[11px] text-muted">neue Noten pro Woche, letzte 8 Wochen</span>
                </div>
                <div class="p-5">
                    <div class="flex items-end gap-2 h-24">
                        @foreach($aktivitaet as $w)
                            @php $hPct = $maxAkt > 0 ? round($w->count / $maxAkt * 100) : 0; @endphp
                            <div class="flex-1 flex flex-col items-center justify-end h-full gap-1"
                                 title="{{ $w->label }}: {{ $w->count }} {{ $w->count === 1 ? 'Note' : 'Noten' }}">
                                <span class="text-[10px] text-muted tabular-nums leading-none">{{ $w->count > 0 ? $w->count : '' }}</span>
                                <div class="w-full rounded-t bg-accent {{ $w->count === 0 ? 'opacity-15' : 'opacity-80' }}"
                                     style="height: {{ max($hPct, $w->count > 0 ? 4 : 2) }}%"></div>
                                <span class="text-[10px] text-muted leading-none whitespace-nowrap">{{ $w->label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Letzte Noten --}}
            <div class="glass rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <h3 class="font-semibold text-text">Zuletzt erfasste Noten</h3>
                </div>
                @forelse($letzteNoten as $n)
                    <div class="px-5 py-3 border-b border-border last:border-0 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors duration-100">
                        <div>
                            <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $n->lernender_id]) }}"
                               class="text-sm text-text hover:text-accent font-medium">
                                {{ $n->nachname }} {{ $n->vorname }}
                            </a>
                            <div class="text-xs text-muted">{{ \Carbon\Carbon::parse($n->pruefungsdatum)->format('d.m.Y') }}</div>
                        </div>
                        @php
                            $nw = (float) $n->note_wert;
                            $nc = $nw >= 5.0 ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 np-glow-green'
                                : ($nw >= 4.0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 np-glow-emerald'
                                : ($nw >= 3.5 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 np-glow-yellow'
                                : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 np-glow-red'));
                        @endphp
                        <span class="inline-flex items-center justify-center min-w-12 px-2 py-1 rounded-xl font-bold text-sm np-fade-in {{ $nc }}">
                            {{ number_format($nw, 1) }}
                        </span>
                    </div>
                @empty
                    <div class="px-5 py-6 text-sm text-muted text-center">Noch keine Noten erfasst.</div>
                @endforelse
            </div>

            {{-- Hinweis: Lehrende in den nächsten 60 Tagen --}}
            @if($lehrEndeBald->isNotEmpty())
                <div class="glass border border-blue-300 dark:border-blue-700 rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20">
                        <h3 class="font-semibold text-blue-800 dark:text-blue-300 text-sm">
                            Lehrende in den nächsten 60 Tagen
                        </h3>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach($lehrEndeBald as $l)
                            @php
                                $daysLeft = (int) \Carbon\Carbon::parse($l->lehrende)->diffInDays(now());
                                $urgency  = $daysLeft <= 14
                                    ? 'text-red-600 dark:text-red-400'
                                    : ($daysLeft <= 30 ? 'text-yellow-700 dark:text-yellow-400' : 'text-muted');
                            @endphp
                            <div class="px-5 py-3 flex items-center justify-between gap-3">
                                <div>
                                    <a href="{{ route('admin.lernende.show', $l->lernender_id) }}"
                                       class="text-sm text-text hover:text-accent font-medium">
                                        {{ $l->nachname }} {{ $l->vorname }}
                                    </a>
                                    @if($l->lehrberuf)
                                        <div class="text-xs text-muted">{{ $l->lehrberuf }}</div>
                                    @endif
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="text-sm font-medium {{ $urgency }}">
                                        {{ \Carbon\Carbon::parse($l->lehrende)->format('d.m.Y') }}
                                    </div>
                                    <div class="text-xs {{ $urgency }}">in {{ $daysLeft }} Tagen</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Warnung: Lernende ohne aktuellen Noteneintrag --}}
            @if($lernendeOhneNoten->isNotEmpty())
                <div class="glass border border-yellow-300 dark:border-yellow-700 rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-900/20">
                        <h3 class="font-semibold text-yellow-800 dark:text-yellow-300 text-sm">
                            Kein Noteneintrag in den letzten 30 Tagen
                        </h3>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach($lernendeOhneNoten as $l)
                            <div class="px-5 py-3 flex items-center justify-between gap-3">
                                <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                                   class="text-sm text-text hover:text-accent font-medium">
                                    {{ $l->nachname }} {{ $l->vorname }}
                                </a>
                                <span class="text-xs text-muted">
                                    @if($l->last_entry)
                                        Letzte Note: {{ \Carbon\Carbon::parse($l->last_entry)->format('d.m.Y') }}
                                    @else
                                        Noch keine Noten
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
