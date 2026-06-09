<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Begrüssung --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                <p class="text-text font-medium text-lg">
                    Willkommen, {{ auth()->user()->vorname }}
                    {{ auth()->user()->nachname }}
                </p>
                <p class="text-muted text-sm mt-1">
                    {{ auth()->user()->email }}
                </p>
            </div>

            {{-- Kennzahlen --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                    <div class="text-xs uppercase tracking-wider text-muted">Noten gesamt</div>
                    <div class="mt-2 text-3xl font-bold text-text">{{ $noteCount }}</div>
                </div>

                <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                    <div class="text-xs uppercase tracking-wider text-muted">Aktuelles Semester</div>
                    <div class="mt-2 text-lg font-semibold text-text">
                        {{ $currentSemester?->bezeichnung ?? '–' }}
                    </div>
                </div>

                <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                    <div class="text-xs uppercase tracking-wider text-muted">Ø akt. Semester</div>
                    <div class="mt-2 text-3xl font-bold text-text">
                        {{ $currentAvg ?? '–' }}
                    </div>
                </div>
            </div>

            {{-- Aktions-Karte --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-5 flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="flex-1">
                    <div class="font-medium text-text">Noten verwalten</div>
                    <div class="text-sm text-muted mt-0.5">
                        Noten erfassen, bearbeiten und nach Semester filtern.
                    </div>
                </div>
                <a href="{{ route('lernender.noten.index') }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap shrink-0">
                    Zu den Noten
                </a>
            </div>

        </div>
    </div>
</x-app-layout>
