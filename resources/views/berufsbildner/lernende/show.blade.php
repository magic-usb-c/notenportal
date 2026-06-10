<x-app-layout>
    <x-slot name="title">Lernenden-Profil</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <div>
                <nav class="text-xs text-muted flex items-center gap-1 mb-1">
                    <a href="{{ route('berufsbildner.lernende.index') }}" class="hover:text-text transition-colors">Lernende</a>
                    <span class="text-muted/40">›</span>
                    <span class="text-text">{{ $profil->nachname }} {{ $profil->vorname }}</span>
                </nav>
                <h2 class="font-semibold text-xl text-text">
                    {{ $profil->nachname }} {{ $profil->vorname }}
                </h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $profil->lernender_id]) }}"
                   class="px-4 py-2 h-10 rounded-xl bg-accent text-white text-sm hover:opacity-90 whitespace-nowrap">
                    Noten ansehen
                </a>
                <a href="{{ route('berufsbildner.lernende.noten.drucken', ['lernender_id' => $profil->lernender_id]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm whitespace-nowrap"
                   title="Notenblatt drucken">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Drucken
                </a>
                <a href="{{ route('berufsbildner.lernende.index') }}"
                   class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm whitespace-nowrap">
                    Zurück
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $avgColor = function ($v) {
            if ($v === null) return 'text-muted';
            if ($v >= 5.0) return 'text-green-600 dark:text-green-400';
            if ($v >= 4.0) return 'text-emerald-600 dark:text-emerald-400';
            if ($v >= 3.5) return 'text-yellow-600 dark:text-yellow-400';
            return 'text-red-600 dark:text-red-400';
        };
    @endphp

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Stammdaten + Gesamt-Ø --}}
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 bg-card border border-border rounded-2xl shadow-sm p-5">
                    <div class="text-xs font-semibold uppercase tracking-wider text-muted mb-3">Stammdaten</div>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted">E-Mail</dt>
                            <dd class="text-text">{{ $profil->email }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Lehrberuf</dt>
                            <dd class="text-text">{{ $profil->lehrberuf ?? '–' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Lehrbeginn</dt>
                            <dd class="text-text">
                                {{ $profil->lehrbeginn ? \Carbon\Carbon::parse($profil->lehrbeginn)->format('d.m.Y') : '–' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Lehrende</dt>
                            <dd class="text-text">
                                {{ $profil->lehrende ? \Carbon\Carbon::parse($profil->lehrende)->format('d.m.Y') : '–' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Letzte Note</dt>
                            <dd class="text-text">
                                {{ $lastEntry ? $lastEntry->format('d.m.Y') : '–' }}
                            </dd>
                        </div>
                        @if($tracks->isNotEmpty())
                            <div>
                                <dt class="text-xs text-muted">Tracks</dt>
                                <dd class="text-text">
                                    @foreach($tracks as $t)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs bg-bg border border-border text-text mr-1">
                                            {{ $t->track_typ }}
                                        </span>
                                    @endforeach
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>

                <div class="bg-card border border-border rounded-2xl shadow-sm p-5 flex flex-col items-center justify-center text-center np-card-lift">
                    <div class="text-[11px] uppercase tracking-widest text-muted font-medium">Ø gesamt</div>
                    <div class="text-4xl font-extrabold tracking-tight tabular-nums mt-1 {{ $avgColor($globalAvg) }}">
                        {{ $globalAvg !== null ? number_format($globalAvg, 2) : '–' }}
                    </div>
                    <div class="text-xs text-muted mt-1">
                        {{ $noteCount }} {{ $noteCount === 1 ? 'Note' : 'Noten' }}
                    </div>
                    @if($profil->lehrende)
                        @php
                            $daysLeft = (int) \Carbon\Carbon::parse($profil->lehrende)->diffInDays(now(), false);
                        @endphp
                        @if($daysLeft >= 0)
                            <div class="mt-3 pt-3 border-t border-border w-full">
                                <div class="text-[10px] uppercase tracking-widest text-muted">Lehrende</div>
                                <div class="text-sm font-semibold mt-0.5
                                    {{ $daysLeft <= 30 ? 'text-red-600 dark:text-red-400' : ($daysLeft <= 90 ? 'text-yellow-600 dark:text-yellow-400' : 'text-text') }}">
                                    in {{ $daysLeft }} Tagen
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- Notenverlauf (Liniendiagramm der letzten 20 Noten) --}}
            <x-noten-verlauf :points="$notenVerlauf"
                             title="Notenverlauf"
                             subtitle="letzte {{ $notenVerlauf->count() }} Noten" />

            {{-- Semester-Statistiken --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <h3 class="font-semibold text-text text-sm">Ø pro Semester</h3>
                </div>
                @if($semStats->isEmpty())
                    <div class="px-5 py-8 text-center text-muted text-sm">
                        Noch keine Noten erfasst.
                    </div>
                @else
                    <div class="divide-y divide-border">
                        @foreach($semStats as $s)
                            @php $a = $s->avg !== null ? (float)$s->avg : null; @endphp
                            <div class="px-5 py-3 flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-medium text-text">{{ $s->sem_label }}</div>
                                    <div class="text-xs text-muted">{{ $s->count }} {{ $s->count === 1 ? 'Note' : 'Noten' }}</div>
                                </div>
                                <div class="text-xl font-bold tabular-nums {{ $avgColor($a) }}">
                                    {{ $a !== null ? number_format($a, 2) : '–' }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
