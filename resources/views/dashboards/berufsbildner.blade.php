@php
    $totalUnread = $stats?->sum('unread') ?? 0;
    $warningsCount = $stats?->filter(fn($s) => $s->warningGelb || $s->warningRot)->count() ?? 0;
@endphp
<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-6">
            <div>
                <h2 class="font-semibold text-xl text-text">Dashboard</h2>
                <p class="text-xs text-muted mt-0.5">{{ \Carbon\Carbon::now()->locale('de_CH')->isoFormat('dddd, D. MMMM YYYY') }}</p>
            </div>
            <div class="hidden sm:flex items-center gap-4 text-right">
                <div>
                    <div class="text-[10px] uppercase tracking-widest text-muted">Lernende</div>
                    <div class="text-lg font-bold text-text tabular-nums">{{ $lernende->count() }}</div>
                </div>
                <div class="w-px h-8 bg-border"></div>
                <div>
                    <div class="text-[10px] uppercase tracking-widest text-muted">Neue Noten</div>
                    <div class="text-lg font-bold tabular-nums {{ $totalUnread > 0 ? 'text-accent' : 'text-muted' }}">{{ $totalUnread }}</div>
                </div>
                <div class="w-px h-8 bg-border"></div>
                <div>
                    <div class="text-[10px] uppercase tracking-widest text-muted">Warnungen</div>
                    <div class="text-lg font-bold tabular-nums {{ $warningsCount > 0 ? 'text-orange-500' : 'text-muted' }}">{{ $warningsCount }}</div>
                </div>
            </div>
        </div>
    </x-slot>

    @php
        $avgColor = function ($v) {
            if ($v === null) return 'text-muted';
            if ($v >= 5.0) return 'text-green-700 dark:text-green-400';
            if ($v >= 4.0) return 'text-emerald-700 dark:text-emerald-400';
            if ($v >= 3.5) return 'text-yellow-700 dark:text-yellow-400';
            return 'text-red-600 dark:text-red-400';
        };
    @endphp

    <div class="py-6">
            {{-- Dekorative Accent-Orbs im Hintergrund (Tiefe) --}}
            <div class="fixed inset-0 overflow-hidden pointer-events-none -z-10" aria-hidden="true">
                <div class="absolute -top-40 -right-40 w-96 h-96 rounded-full"
                     style="background:radial-gradient(circle,rgb(var(--accent-rgb) / 0.08) 0%,transparent 70%)"></div>
                <div class="absolute -bottom-20 -left-20 w-80 h-80 rounded-full"
                     style="background:radial-gradient(circle,rgb(var(--accent-rgb) / 0.05) 0%,transparent 70%)"></div>
            </div>

        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Begrüssung (kompakt, ohne Panel) --}}
            <div class="flex items-baseline gap-2">
                <h3 class="text-base font-semibold text-text">Willkommen, {{ auth()->user()->vorname }}</h3>
                <span class="text-xs text-muted">Berufsbildner</span>
            </div>

            {{-- "Zu tun": Lernende die Aufmerksamkeit brauchen --}}
            @php
                $todos = collect();
                foreach ($lernende as $l) {
                    $lid = (int) $l->lernender_id;
                    $st  = $stats->get($lid);
                    $lp  = $lernendeProfile->get($lid);

                    if ($st?->warningGelb) {
                        $mailBody = rawurlencode("Hallo {$l->vorname}\n\nMir ist aufgefallen, dass du seit längerem keine Noten mehr im Notenportal erfasst hast. Bitte trage deine aktuellen Noten nach.\n\nDanke und Gruss\n" . auth()->user()->vorname);
                        $todos->push((object) [
                            'lernender' => $l,
                            'text'      => $st->daysSince === null ? 'Noch keine Noten erfasst' : 'Kein Eintrag seit ' . $st->daysSince . ' Tagen',
                            'farbe'     => 'yellow',
                            'aktion'    => 'mailto:' . $l->email . '?subject=' . rawurlencode('Notenportal: Bitte Noten nachtragen') . '&body=' . $mailBody,
                            'aktionText' => 'Erinnerung senden',
                            'extern'    => true,
                        ]);
                    }
                    if ($st?->warningRot) {
                        $todos->push((object) [
                            'lernender' => $l,
                            'text'      => 'Semester-Ø ' . number_format($st->semAvg, 2) . ' — unter 4.0',
                            'farbe'     => 'red',
                            'aktion'    => route('berufsbildner.lernende.noten.index', ['lernender_id' => $lid]),
                            'aktionText' => 'Noten ansehen',
                            'extern'    => false,
                        ]);
                    }
                    if ($lp && \Carbon\Carbon::parse($lp->lehrende)->diffInDays(now(), false) >= -30) {
                        $todos->push((object) [
                            'lernender' => $l,
                            'text'      => 'Lehrende am ' . \Carbon\Carbon::parse($lp->lehrende)->format('d.m.Y'),
                            'farbe'     => 'blue',
                            'aktion'    => route('berufsbildner.lernende.show', ['lernender_id' => $lid]),
                            'aktionText' => 'Profil öffnen',
                            'extern'    => false,
                        ]);
                    }
                }
            @endphp
            @if($todos->isNotEmpty())
                <div class="glass rounded-2xl overflow-hidden">
                    <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                        <h3 class="font-semibold text-text text-sm">Zu tun</h3>
                        <span class="inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 rounded-full text-[11px] font-bold bg-accent/15 text-accent">
                            {{ $todos->count() }}
                        </span>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach($todos as $todo)
                            @php
                                $dot = match ($todo->farbe) {
                                    'red'    => 'bg-red-500',
                                    'yellow' => 'bg-yellow-500',
                                    default  => 'bg-blue-500',
                                };
                            @endphp
                            <div class="px-5 py-2.5 flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $dot }}"></span>
                                <div class="flex-1 min-w-0 text-sm">
                                    <span class="font-medium text-text">{{ $todo->lernender->nachname }} {{ $todo->lernender->vorname }}</span>
                                    <span class="text-muted"> — {{ $todo->text }}</span>
                                </div>
                                <a href="{{ $todo->aktion }}"
                                   class="shrink-0 text-xs text-accent hover:underline whitespace-nowrap">
                                    {{ $todo->aktionText }} →
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Betreute Lernende als Card-Grid --}}
            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="font-semibold text-text">Meine Lernenden</h3>
                </div>

                @if($lernende->isEmpty())
                    <div class="glass rounded-2xl px-5 py-12 text-center">
                        <svg class="mx-auto w-12 h-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <p class="mt-3 text-sm text-muted">Keine aktuell betreuten Lernenden gefunden.</p>
                        <p class="mt-1 text-xs text-muted">Falls das ein Fehler ist, bitte beim Admin melden.</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($lernende as $l)
                            @php
                                $lid = (int) $l->lernender_id;
                                $st = $stats->get($lid);
                                $lehrProfil = $lernendeProfile->get($lid);
                                $daysLeft = $lehrProfil ? (int) \Carbon\Carbon::parse($lehrProfil->lehrende)->diffInDays(now()) : null;
                                $initials = strtoupper(mb_substr($l->vorname, 0, 1) . mb_substr($l->nachname, 0, 1));
                            @endphp

                            <a href="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $lid]) }}"
                               class="group relative overflow-hidden block glass glass-lift rounded-2xl p-4 hover:border-accent/40">
                                {{-- Unread-Badge oben rechts --}}
                                @if($st?->unread > 0)
                                    <span class="absolute top-3 right-3 inline-flex items-center justify-center min-w-[1.5rem] h-6 px-1.5 rounded-full text-[11px] font-bold bg-accent text-white shadow-sm">
                                        <span class="absolute inset-0 rounded-full bg-accent animate-ping opacity-30"></span>
                                        <span class="relative">{{ $st->unread }} neu</span>
                                    </span>
                                @endif

                                {{-- Avatar + Name --}}
                                <div class="flex items-center gap-3 mb-3">
                                    <div class="w-10 h-10 rounded-full bg-accent/10 text-accent text-sm font-bold flex items-center justify-center shrink-0">
                                        {{ $initials }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-semibold text-text truncate">{{ $l->nachname }} {{ $l->vorname }}</div>
                                        <div class="text-xs text-muted truncate">{{ $l->lehrberuf ?? 'kein Lehrberuf' }}</div>
                                    </div>
                                </div>

                                {{-- Stats-Row --}}
                                <div class="flex divide-x divide-border text-center">
                                    <div class="flex-1 px-1">
                                        <div class="text-[10px] text-muted uppercase tracking-wide">Ø Sem.</div>
                                        <div class="text-lg font-bold tabular-nums {{ $avgColor($st?->semAvg) }}">
                                            {{ $st?->semAvg !== null ? $st->semAvg : '–' }}
                                        </div>
                                    </div>
                                    <div class="flex-1 px-1">
                                        <div class="text-[10px] text-muted uppercase tracking-wide">Noten</div>
                                        <div class="text-lg font-bold text-text tabular-nums">{{ $st?->semCount ?? 0 }}</div>
                                    </div>
                                    @if($st && ($st->daysSince === null || $st->daysSince > 30))
                                        <div class="flex-1 px-1">
                                            <div class="text-[10px] text-orange-500 uppercase tracking-wide">Inaktiv</div>
                                            <div class="text-xs font-semibold text-orange-600 dark:text-orange-400 leading-6">
                                                {{ $st->daysSince === null ? '∞' : $st->daysSince.'d' }}
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                {{-- Badges-Footer --}}
                                @if(($st?->warningRot) || $daysLeft !== null)
                                    <div class="mt-3 pt-3 border-t border-border flex flex-wrap gap-1.5">
                                        @if($st?->warningRot)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                                Ø unter 4.0
                                            </span>
                                        @endif
                                        @if($daysLeft !== null)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium
                                                {{ $daysLeft <= 14 ? 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' }}">
                                                Lehrende in {{ $daysLeft }}d
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Quick-Actions --}}
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('berufsbildner.lernende.index') }}"
                   class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Alle Lernenden anzeigen
                </a>
                @if(Route::has('berufsbildner.export'))
                    <a href="{{ route('berufsbildner.export') }}"
                       class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm inline-flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Alle Noten exportieren (CSV)
                    </a>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
