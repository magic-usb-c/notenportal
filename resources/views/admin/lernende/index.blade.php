<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Admin: Lernende</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="bg-bg text-muted">
                            <tr>
                                <th class="text-left p-3">Name / E-Mail</th>
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
                                @endphp
                                <tr class="hover:bg-bg">
                                    <td class="p-3">
                                        <a href="{{ route('admin.lernende.show', $l->lernender_id) }}"
                                           class="font-medium hover:text-accent">{{ $l->nachname }} {{ $l->vorname }}</a>
                                        <div class="text-xs text-muted">{{ $l->email }}</div>
                                    </td>
                                    <td class="p-3 text-muted">
                                        @if($s?->betreuer)
                                            {{ $s->betreuer->nachname }} {{ $s->betreuer->vorname }}
                                        @else
                                            <span class="italic">–</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        {{ $s?->noten_count ?? 0 }}
                                    </td>
                                    <td class="p-3 text-center text-muted whitespace-nowrap">
                                        @if($s?->last_note)
                                            {{ \Carbon\Carbon::parse($s->last_note)->format('d.m.Y') }}
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
                                        Keine Lernenden gefunden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
