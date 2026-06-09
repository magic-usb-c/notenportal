<x-app-layout>
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
            <div class="flex flex-wrap gap-3">
                <div class="bg-card border border-border rounded-2xl shadow-md p-4 min-w-[120px]">
                    <div class="text-xs text-muted">Noten gesamt</div>
                    <div class="mt-1 text-2xl font-bold text-text">{{ $noteCount }}</div>
                </div>
                <div class="bg-card border border-border rounded-2xl shadow-md p-4 min-w-[120px]">
                    <div class="text-xs text-muted">Ø gesamt</div>
                    <div class="mt-1 text-2xl font-bold text-text">{{ $globalAvg ?? '–' }}</div>
                </div>
                <div class="bg-card border border-border rounded-2xl shadow-md p-4 min-w-[140px]">
                    <div class="text-xs text-muted">Aktuelles Semester</div>
                    <div class="mt-1 text-sm font-semibold text-text">{{ $currentSemester?->bezeichnung ?? '–' }}</div>
                </div>
                <div class="bg-card border border-border rounded-2xl shadow-md p-4 min-w-[120px]">
                    <div class="text-xs text-muted">Ø akt. Semester</div>
                    <div class="mt-1 text-2xl font-bold
                        @if($currentAvg !== null && $currentAvg >= 4.0) text-green-600 dark:text-green-400
                        @elseif($currentAvg !== null && $currentAvg >= 3.5) text-yellow-600 dark:text-yellow-400
                        @elseif($currentAvg !== null) text-red-600 dark:text-red-400
                        @else text-text
                        @endif">
                        {{ $currentAvg ?? '–' }}
                    </div>
                </div>
            </div>

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
                                $noteColor = $noteWert >= 4.0
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : ($noteWert >= 3.5
                                        ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300');
                            @endphp
                            <div class="px-5 py-3 flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-sm font-medium text-text">{{ $label }}</div>
                                    <div class="text-xs text-muted">{{ \Carbon\Carbon::parse($n->pruefungsdatum)->format('d.m.Y') }}</div>
                                </div>
                                <span class="inline-flex items-center justify-center min-w-[3rem] px-3 py-1 rounded-xl font-bold text-sm {{ $noteColor }}">
                                    {{ number_format($noteWert, 1) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
