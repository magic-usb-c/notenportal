<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Admin: Lernende</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <p class="text-sm text-muted">Wähle einen Lernenden aus, um dessen Noten zu sehen.</p>
                </div>

                @forelse($lernende as $l)
                    <div class="flex items-center justify-between px-5 py-3 border-b border-border last:border-0 hover:bg-bg">
                        <div>
                            <div class="font-medium text-text">
                                {{ $l->nachname }} {{ $l->vorname }}
                            </div>
                            <div class="text-xs text-muted">{{ $l->email }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('admin.lernende.betreuung', $l->lernender_id) }}"
                               class="px-3 py-2 rounded-xl bg-card text-text border border-border text-sm hover:bg-bg whitespace-nowrap">
                                Betreuung
                            </a>
                            <a href="{{ route('admin.lernende.tracks', $l->lernender_id) }}"
                               class="px-3 py-2 rounded-xl bg-card text-text border border-border text-sm hover:bg-bg whitespace-nowrap">
                                Tracks
                            </a>
                            <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                               class="px-3 py-2 rounded-xl bg-accent text-white text-sm hover:opacity-90 whitespace-nowrap">
                                Noten
                            </a>
                        </div>
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
