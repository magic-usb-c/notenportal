<x-app-layout>
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

            {{-- Letzte Noten --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
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
                            $nc = $nw >= 5.0 ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                : ($nw >= 4.0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : ($nw >= 3.5 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                                : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'));
                        @endphp
                        <span class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1 rounded-xl font-bold text-sm {{ $nc }}">
                            {{ number_format($nw, 1) }}
                        </span>
                    </div>
                @empty
                    <div class="px-5 py-6 text-sm text-muted text-center">Noch keine Noten erfasst.</div>
                @endforelse
            </div>

            {{-- Hinweis: Lehrende in den nächsten 60 Tagen --}}
            @if($lehrEndeBald->isNotEmpty())
                <div class="bg-card border border-blue-300 dark:border-blue-700 rounded-2xl shadow-sm overflow-hidden">
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
                                    : ($daysLeft <= 30 ? 'text-yellow-600 dark:text-yellow-400' : 'text-muted');
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
                <div class="bg-card border border-yellow-300 dark:border-yellow-700 rounded-2xl shadow-sm overflow-hidden">
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
