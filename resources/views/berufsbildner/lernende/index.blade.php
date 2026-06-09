<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Lernende (meine Betreuung)</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">

                @forelse($lernende as $l)
                    @php
                        $st = $stats->get((int) $l->lernender_id);
                        $lehrende     = $l->lehrende ? \Carbon\Carbon::parse($l->lehrende) : null;
                        $lehrDaysLeft = $lehrende ? (int) now()->diffInDays($lehrende, false) : null;
                        $showLehrBadge = $lehrDaysLeft !== null && $lehrDaysLeft >= 0 && $lehrDaysLeft <= 60;
                    @endphp
                    <div class="px-5 py-4 border-b border-border last:border-0 hover:bg-bg/60">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-text">{{ $l->nachname }} {{ $l->vorname }}</span>

                                    @if($showLehrBadge)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium whitespace-nowrap
                                            {{ $lehrDaysLeft <= 14
                                                ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                                                : ($lehrDaysLeft <= 30
                                                    ? 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300'
                                                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300') }}"
                                            title="Lehrende: {{ $lehrende->format('d.m.Y') }}">
                                            Lehrende in {{ $lehrDaysLeft }} Tagen
                                        </span>
                                    @endif

                                    @if($st?->warningGelb)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
                                            {{ $st->daysSince === null ? 'Noch keine Noten' : $st->daysSince . ' Tage kein Eintrag' }}
                                        </span>
                                    @endif

                                    @if($st?->warningRot)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                            Ø {{ $st->semAvg }} &lt; 4.0
                                        </span>
                                    @endif
                                </div>
                                <div class="text-xs text-muted mt-0.5">{{ $l->email }}</div>
                            </div>

                            @if($st)
                                <div class="flex items-center gap-4 shrink-0">
                                    <div class="text-center hidden sm:block">
                                        <div class="text-xs text-muted">Letzter Eintrag</div>
                                        <div class="text-sm font-medium text-text">
                                            {{ $st->lastEntry ? $st->lastEntry->format('d.m.Y') : '–' }}
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-xs text-muted">Ø Sem.</div>
                                        <div class="text-sm font-bold
                                            @if($st->semAvg === null) text-muted
                                            @elseif($st->semAvg >= 4.0) text-green-600 dark:text-green-400
                                            @elseif($st->semAvg >= 3.5) text-yellow-600 dark:text-yellow-400
                                            @else text-red-600 dark:text-red-400
                                            @endif">
                                            {{ $st->semAvg ?? '–' }}
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3">
                            <a href="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-accent text-white text-xs hover:opacity-90 whitespace-nowrap">
                                Noten öffnen
                                @if($st?->unread > 0)
                                    <span class="inline-flex items-center justify-center min-w-[1.25rem] px-1 py-0.5 rounded-full text-[10px] font-bold bg-white text-accent leading-none">
                                        {{ $st->unread }}
                                    </span>
                                @endif
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <svg class="mx-auto w-12 h-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="mt-3 text-sm text-muted">Keine Lernenden zugewiesen.</p>
                        <p class="mt-1 text-xs text-muted">Falls das ein Fehler ist, bitte beim Admin melden.</p>
                    </div>
                @endforelse

            </div>

        </div>
    </div>
</x-app-layout>
