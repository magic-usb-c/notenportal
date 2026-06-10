<x-app-layout>
    <x-slot name="title">Dashboard</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Begrüssung --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-5 flex items-center justify-between gap-4">
                <div>
                    <p class="text-text font-medium text-lg">
                        Willkommen, {{ auth()->user()->vorname }}
                        {{ auth()->user()->nachname }}
                    </p>
                    <p class="text-muted text-sm mt-0.5">{{ auth()->user()->email }}</p>
                </div>
                @if($ungeleseneKommentarNoten > 0)
                    <a href="{{ route('lernender.noten.index') }}"
                       class="flex items-center gap-2 px-3 py-2 rounded-xl bg-accent/10 text-accent border border-accent/20 text-sm hover:bg-accent/20">
                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-accent text-white text-xs font-bold">
                            {{ $ungeleseneKommentarNoten }}
                        </span>
                        neue Kommentare
                    </a>
                @endif
            </div>

            {{-- Kennzahlen --}}
            @php
                $kennColor = function ($v) {
                    if ($v === null) return 'text-text';
                    if ($v >= 5.0) return 'text-green-600 dark:text-green-400';
                    if ($v >= 4.0) return 'text-emerald-600 dark:text-emerald-400';
                    if ($v >= 3.5) return 'text-yellow-600 dark:text-yellow-400';
                    return 'text-red-600 dark:text-red-400';
                };
                $kennGlow = function ($v) {
                    if ($v === null) return '';
                    if ($v >= 5.0) return 'np-glow-green';
                    if ($v >= 4.0) return 'np-glow-emerald';
                    if ($v >= 3.5) return 'np-glow-yellow';
                    return 'np-glow-red';
                };
            @endphp

            @if($globalAvg !== null)
                {{-- Score-Card: der Gesamtdurchschnitt als Held der Seite --}}
                <div class="glass rounded-3xl p-8 text-center relative overflow-hidden"
                     x-data="{ shown: 0, target: {{ $globalAvg }} }"
                     x-init="
                        const start = performance.now();
                        const tick = (t) => {
                            const p = Math.min(1, (t - start) / 700);
                            shown = (target * (1 - Math.pow(1 - p, 3))).toFixed(2);
                            if (p < 1) requestAnimationFrame(tick);
                        };
                        requestAnimationFrame(tick);
                     ">
                    <div class="absolute -top-16 left-1/2 -translate-x-1/2 w-64 h-64 rounded-full bg-accent/10 blur-3xl pointer-events-none"></div>
                    <div class="text-[11px] uppercase tracking-widest text-muted font-medium relative">Gesamtdurchschnitt</div>
                    <div class="mt-2 text-6xl font-extrabold tracking-tight tabular-nums relative {{ $kennColor($globalAvg) }}"
                         x-text="shown">{{ number_format($globalAvg, 2) }}</div>
                    <div class="mt-1.5 text-xs text-muted relative">von 6.0 möglichen Punkten</div>

                    {{-- Supporting Stats --}}
                    <div class="mt-6 pt-5 border-t border-border/60 grid grid-cols-3 gap-4 relative">
                        <div>
                            <div class="text-[10px] uppercase tracking-widest text-muted">Noten gesamt</div>
                            <div class="mt-1 text-xl font-bold text-text tabular-nums">{{ $noteCount }}</div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-widest text-muted">Ø akt. Semester</div>
                            <div class="mt-1 text-xl font-bold tabular-nums {{ $kennColor($currentAvg) }}">
                                {{ $currentAvg ?? '–' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[10px] uppercase tracking-widest text-muted">Semester</div>
                            <div class="mt-1 text-sm font-semibold text-text leading-tight pt-1">{{ $currentSemester?->bezeichnung ?? '–' }}</div>
                        </div>
                    </div>
                </div>
            @else
                {{-- Noch keine Noten: Empty-State mit Charakter --}}
                <div class="glass rounded-3xl px-8 py-12 text-center">
                    <svg class="mx-auto w-20 h-20 text-muted/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <h3 class="mt-4 font-semibold text-text">Dein Notenspiegel wartet auf dich</h3>
                    <p class="mt-1 text-sm text-muted">Erfasse deine erste Note und sieh zu, wie dein Durchschnitt Gestalt annimmt.</p>
                    <a href="{{ route('lernender.noten.create') }}"
                       class="mt-5 inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">
                        <span class="text-lg leading-none">+</span>
                        Erste Note erfassen
                    </a>
                </div>
            @endif

            {{-- Lehrausbildung: Restlaufzeit --}}
            @if($lehrProfil && $lehrProfil->lehrende)
                @php
                    $lehrEnde = \Carbon\Carbon::parse($lehrProfil->lehrende);
                    $daysLeft = (int) now()->diffInDays($lehrEnde, false);
                    $isExpired = $daysLeft < 0;
                    $progressPct = null;
                    if ($lehrProfil->lehrbeginn) {
                        $start = \Carbon\Carbon::parse($lehrProfil->lehrbeginn);
                        $total = $start->diffInDays($lehrEnde);
                        $elapsed = $start->diffInDays(now());
                        $progressPct = $total > 0 ? min(100, round($elapsed / $total * 100)) : null;
                    }
                @endphp
                <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <div class="text-xs text-muted">Lehrausbildung</div>
                            <div class="text-sm font-medium text-text mt-0.5">{{ $lehrProfil->lehrberuf_name ?? '–' }}</div>
                        </div>
                        <div class="text-right shrink-0">
                            @if($isExpired)
                                <div class="text-sm font-semibold text-muted">Abgeschlossen</div>
                                <div class="text-xs text-muted">{{ $lehrEnde->format('d.m.Y') }}</div>
                            @else
                                <div class="text-sm font-semibold
                                    {{ $daysLeft <= 30 ? 'text-red-600 dark:text-red-400' : ($daysLeft <= 90 ? 'text-yellow-600 dark:text-yellow-400' : 'text-text') }}">
                                    noch {{ $daysLeft }} Tage
                                </div>
                                <div class="text-xs text-muted">Lehrende: {{ $lehrEnde->format('d.m.Y') }}</div>
                            @endif
                        </div>
                    </div>
                    @if($progressPct !== null && !$isExpired)
                        <div class="mt-3">
                            <div class="flex items-center justify-between text-xs text-muted mb-1">
                                <span>{{ \Carbon\Carbon::parse($lehrProfil->lehrbeginn)->format('d.m.Y') }}</span>
                                <span>{{ $progressPct }}% absolviert</span>
                                <span>{{ $lehrEnde->format('d.m.Y') }}</span>
                            </div>
                            <div class="h-1.5 bg-bg rounded-full overflow-hidden border border-border">
                                <div class="h-full bg-accent rounded-full transition-all"
                                     style="width: {{ $progressPct }}%"></div>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Notenverlauf (Liniendiagramm der letzten 20 Noten) --}}
            <x-noten-verlauf :points="$notenVerlauf" title="Notenverlauf" subtitle="letzte {{ $notenVerlauf->count() }} Noten" />

            {{-- Letzte 3 Noten --}}
            @if($letzteDreiNoten->isNotEmpty())
                <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                        <h3 class="font-semibold text-text">Letzte Noten</h3>
                        <a href="{{ route('lernender.noten.index') }}" class="text-xs text-accent hover:underline">
                            Alle anzeigen
                        </a>
                    </div>
                    <div class="divide-y divide-border">
                        @foreach($letzteDreiNoten as $n)
                            @php
                                $label = $n->fach_name
                                    ?? ($n->modul_nummer ? ($n->modul_nummer . ' ' . $n->modul_titel) : null)
                                    ?? $n->titel
                                    ?? '–';
                                $noteWert = (float) $n->note_wert;
                                $datum = \Carbon\Carbon::parse($n->pruefungsdatum);
                                $daysAgo = (int) $datum->diffInDays(now());
                                $relativeDate = $daysAgo === 0 ? 'heute'
                                    : ($daysAgo === 1 ? 'gestern'
                                    : ($daysAgo < 7 ? 'vor '.$daysAgo.' Tagen'
                                    : $datum->format('d.m.Y')));
                                $noteColor = $noteWert >= 5.0
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : ($noteWert >= 4.0
                                        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                        : ($noteWert >= 3.5
                                            ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                                            : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'));
                            @endphp
                            <a href="{{ route('lernender.noten.index') }}?_open={{ $n->note_id }}"
                               class="block px-5 py-3 flex items-center justify-between gap-4 hover:bg-accent/5 transition-colors duration-100">
                                <div>
                                    <div class="text-sm font-medium text-text">{{ $label }}</div>
                                    <div class="text-xs text-muted">{{ $relativeDate }}</div>
                                </div>
                                <span class="inline-flex items-center justify-center min-w-[3rem] px-3 py-1 rounded-xl font-bold text-sm {{ $noteColor }}">
                                    {{ number_format($noteWert, 1) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Quick-Actions --}}
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('lernender.noten.create') }}"
                   class="px-4 py-2 h-10 rounded-xl bg-accent text-white text-sm hover:opacity-90 inline-flex items-center gap-2">
                    <span class="text-lg leading-none">+</span>
                    Neue Note erfassen
                </a>
                <a href="{{ route('lernender.noten.rechner') }}"
                   class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4m5 4H5a2 2 0 01-2-2V5a2 2 0 012-2h10l4 4v11a2 2 0 01-2 2z"/>
                    </svg>
                    Noten-Rechner
                </a>
                <a href="{{ route('lernender.noten.export') }}"
                   class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    CSV exportieren
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
