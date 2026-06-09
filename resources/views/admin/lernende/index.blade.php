<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Admin: Lernende</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Suche --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <form method="GET" action="{{ route('admin.lernende.index') }}"
                      class="flex gap-2">
                    <input type="text" name="suche" value="{{ $suche }}"
                           placeholder="Name oder E-Mail suchen…"
                           class="flex-1 rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                    <button type="submit"
                            class="px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap text-sm shrink-0">
                        Suchen
                    </button>
                    @if($suche)
                        <a href="{{ route('admin.lernende.index') }}"
                           class="px-3 py-2 h-10 rounded-xl bg-card border border-border text-text hover:bg-bg flex items-center text-sm shrink-0">
                            ×
                        </a>
                    @endif
                </form>
            </div>

            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="bg-bg text-muted">
                            <tr>
                                <th class="text-left p-3">Name / Hinweise</th>
                                <th class="text-left p-3 whitespace-nowrap">Berufsbildner</th>
                                <th class="text-center p-3 whitespace-nowrap">Noten</th>
                                <th class="text-center p-3 whitespace-nowrap">Letzte Note</th>
                                <th class="text-center p-3 whitespace-nowrap">Ø gesamt</th>
                                <th class="text-right p-3">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($lernende as $l)
                                @php
                                    $s = $stats[(int)$l->lernender_id] ?? null;
                                    $avg = $s?->avg_all ? (float)$s->avg_all : null;
                                    $nc = $avg !== null
                                        ? ($avg >= 4.0 ? 'text-green-600 dark:text-green-400' : ($avg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'))
                                        : 'text-muted';

                                    // Warnungen berechnen
                                    $lastNote   = $s?->last_note ? \Carbon\Carbon::parse($s->last_note) : null;
                                    $daysSince  = $lastNote ? (int) $lastNote->diffInDays(now()) : null;
                                    $warnGelb   = $daysSince === null || $daysSince > 30;
                                    $warnRot    = $avg !== null && $avg < 4.0;
                                @endphp
                                <tr class="hover:bg-bg">
                                    <td class="p-3">
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <a href="{{ route('admin.lernende.show', $l->lernender_id) }}"
                                               class="font-medium hover:text-accent">{{ $l->nachname }} {{ $l->vorname }}</a>

                                            @if($warnGelb)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300 whitespace-nowrap" title="Kein aktueller Noteneintrag">
                                                    {{ $daysSince === null ? 'Keine Noten' : $daysSince . 'd kein Eintrag' }}
                                                </span>
                                            @endif

                                            @if($warnRot)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 whitespace-nowrap" title="Durchschnitt unter 4.0">
                                                    Ø {{ number_format($avg, 1) }}
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-xs text-muted mt-0.5">{{ $l->email }}</div>
                                    </td>
                                    <td class="p-3 text-muted text-sm">
                                        @if($s?->betreuer)
                                            {{ $s->betreuer->nachname }} {{ $s->betreuer->vorname }}
                                        @else
                                            <span class="italic text-muted">–</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        {{ $s?->noten_count ?? 0 }}
                                    </td>
                                    <td class="p-3 text-center text-muted whitespace-nowrap">
                                        @if($lastNote)
                                            {{ $lastNote->format('d.m.Y') }}
                                        @else
                                            –
                                        @endif
                                    </td>
                                    <td class="p-3 text-center font-semibold {{ $nc }}">
                                        {{ $avg !== null ? number_format($avg, 2) : '–' }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <a href="{{ route('admin.lernende.betreuung', $l->lernender_id) }}"
                                               class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">
                                                Betreuung
                                            </a>
                                            <a href="{{ route('admin.lernende.tracks', $l->lernender_id) }}"
                                               class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">
                                                Tracks
                                            </a>
                                            <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                                               class="px-3 py-1.5 rounded-xl bg-accent text-white text-xs hover:opacity-90 whitespace-nowrap">
                                                Noten
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-muted">
                                        @if($suche)
                                            Keine Lernenden für «{{ $suche }}» gefunden.
                                        @else
                                            Keine Lernenden gefunden.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($lernende->isNotEmpty())
                    <div class="px-4 py-2 border-t border-border text-xs text-muted">
                        {{ $lernende->count() }} Lernende
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
