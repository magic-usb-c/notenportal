<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Admin Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Begrüssung --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                <p class="text-text font-medium text-lg">
                    Willkommen, {{ auth()->user()->vorname }} {{ auth()->user()->nachname }}
                </p>
                <p class="text-muted text-sm mt-0.5">Administrator</p>
            </div>

            {{-- Kennzahlen --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                    <div class="text-xs text-muted">Lernende</div>
                    <div class="mt-1 text-2xl font-bold text-text">{{ $lernendCount }}</div>
                    <a href="{{ route('admin.lernende.index') }}" class="text-xs text-accent hover:underline mt-1 block">Übersicht</a>
                </div>
                <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                    <div class="text-xs text-muted">Berufsbildner</div>
                    <div class="mt-1 text-2xl font-bold text-text">{{ $berufsbildnerCount }}</div>
                    <a href="{{ route('admin.benutzer.index') }}" class="text-xs text-accent hover:underline mt-1 block">Benutzer</a>
                </div>
                <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                    <div class="text-xs text-muted">Noten gesamt</div>
                    <div class="mt-1 text-2xl font-bold text-text">{{ $noteCount }}</div>
                </div>
                <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                    <div class="text-xs text-muted">Noten {{ $currentSemester?->bezeichnung ?? 'akt. Semester' }}</div>
                    <div class="mt-1 text-2xl font-bold text-text">{{ $notesThisSemester }}</div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                {{-- Schnellzugriff --}}
                <div class="bg-card border border-border rounded-2xl shadow-sm p-5 space-y-3">
                    <h3 class="font-semibold text-text">Verwaltung</h3>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="{{ route('admin.benutzer.create') }}"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-border hover:bg-bg text-sm text-text">
                            <span class="text-accent font-bold">+</span> Benutzer anlegen
                        </a>
                        <a href="{{ route('admin.benutzer.index') }}"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-border hover:bg-bg text-sm text-text">
                            Alle Benutzer
                        </a>
                        <a href="{{ route('admin.stammdaten.lehrberufe.index') }}"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-border hover:bg-bg text-sm text-text">
                            Lehrberufe
                        </a>
                        <a href="{{ route('admin.stammdaten.semester.index') }}"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-border hover:bg-bg text-sm text-text">
                            Semester
                        </a>
                        <a href="{{ route('admin.stammdaten.module.index') }}"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-border hover:bg-bg text-sm text-text">
                            Module
                        </a>
                        <a href="{{ route('admin.stammdaten.faecher.index') }}"
                           class="flex items-center gap-2 px-3 py-2.5 rounded-xl border border-border hover:bg-bg text-sm text-text">
                            Fächer
                        </a>
                    </div>
                </div>

                {{-- Letzte Noten --}}
                <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-border">
                        <h3 class="font-semibold text-text">Zuletzt erfasste Noten</h3>
                    </div>
                    @forelse($letzteNoten as $n)
                        <div class="px-5 py-3 border-b border-border last:border-0 flex items-center justify-between gap-3">
                            <div>
                                <div class="text-sm text-text">{{ $n->nachname }} {{ $n->vorname }}</div>
                                <div class="text-xs text-muted">{{ \Carbon\Carbon::parse($n->pruefungsdatum)->format('d.m.Y') }}</div>
                            </div>
                            @php
                                $nw = (float) $n->note_wert;
                                $nc = $nw >= 4.0 ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : ($nw >= 3.5 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300');
                            @endphp
                            <span class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1 rounded-xl font-bold text-sm {{ $nc }}">
                                {{ number_format($nw, 1) }}
                            </span>
                        </div>
                    @empty
                        <div class="px-5 py-6 text-sm text-muted text-center">Noch keine Noten erfasst.</div>
                    @endforelse
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
