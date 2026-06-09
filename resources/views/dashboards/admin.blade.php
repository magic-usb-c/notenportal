<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Admin Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Begrüssung --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                <p class="text-text font-medium text-lg">
                    Willkommen, {{ auth()->user()->vorname }}
                    {{ auth()->user()->nachname }}
                </p>
                <p class="text-muted text-sm mt-1">Administrator</p>
            </div>

            {{-- Kennzahlen --}}
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                    <div class="text-xs uppercase tracking-wider text-muted">Lernende</div>
                    <div class="mt-2 text-3xl font-bold text-text">{{ $lernendCount }}</div>
                </div>
                <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                    <div class="text-xs uppercase tracking-wider text-muted">Noten gesamt</div>
                    <div class="mt-2 text-3xl font-bold text-text">{{ $noteCount }}</div>
                </div>
            </div>

            {{-- Alle Lernenden --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <h3 class="font-semibold text-text">Alle Lernenden</h3>
                </div>

                @forelse($lernende as $l)
                    <div class="flex items-center justify-between px-5 py-3 border-b border-border last:border-0 hover:bg-bg">
                        <div>
                            <div class="font-medium text-text">
                                {{ $l->nachname }} {{ $l->vorname }}
                            </div>
                            <div class="text-xs text-muted">{{ $l->email }}</div>
                        </div>
                        <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                           class="px-4 py-2 rounded-xl bg-accent text-white text-sm hover:opacity-90 whitespace-nowrap">
                            Noten öffnen
                        </a>
                    </div>
                @empty
                    <div class="px-5 py-6 text-sm text-muted text-center">
                        Keine Lernenden gefunden.
                    </div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
