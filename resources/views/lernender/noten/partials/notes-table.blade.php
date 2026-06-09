{{-- resources/views/lernender/noten/partials/notes-table.blade.php --}}
@props([
    'fachGroups',
    'modulGroups',
    'weightedAvg',      // callable
    'canManage' => false,
    'editUrl' => null,  // callable(note_id): string
    'destroyUrl' => null, // callable(note_id): string
])

{{-- Fächer --}}
<div class="space-y-2">
    <div class="text-xs uppercase tracking-wider text-muted px-1">Fächer</div>

    @forelse($fachGroups as $fachId => $items)
        @php
            $fachName = $items->first()->fach->name ?? 'Fach';
            $avg = $weightedAvg($items);
        @endphp

        <details class="np-details bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
            <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between list-none hover:bg-card/60">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="np-chevron text-muted transition-transform duration-200 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <div class="font-semibold text-text truncate">{{ $fachName }}</div>
                        <div class="text-xs text-muted">{{ $items->count() }} Note(n)</div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-xs text-muted">Ø</span>
                    <span class="inline-flex items-center justify-center min-w-[4rem] px-3 py-1 rounded-xl bg-bg text-text border border-border">
                        {{ $avg ?? '-' }}
                    </span>
                </div>
            </summary>

            <div class="border-t border-border">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="bg-bg text-muted">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                <th class="text-left p-3">Titel</th>
                                <th class="text-right p-3 whitespace-nowrap">Gew. %</th>
                                <th class="text-right p-3 whitespace-nowrap">Note</th>
                                @if($canManage)
                                    <th class="text-right p-3 whitespace-nowrap">Aktionen</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($items as $n)
                                <tr class="hover:bg-card/60">
                                    <td class="p-3 whitespace-nowrap">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</td>
                                    <td class="p-3">{{ $n->titel ?? '-' }}</td>
                                    <td class="p-3 text-right whitespace-nowrap">{{ $n->gewichtung_prozent ?? 100 }}</td>
                                    <td class="p-3 text-right font-semibold whitespace-nowrap">{{ $n->note_wert }}</td>

                                    @if($canManage)
                                        <td class="p-3 text-right whitespace-nowrap">
                                            <a class="text-accent hover:underline" href="{{ $editUrl ? $editUrl($n->note_id) : '#' }}">Bearbeiten</a>

                                            <form method="POST" action="{{ $destroyUrl ? $destroyUrl($n->note_id) : '#' }}" class="inline"
                                                  onsubmit="return confirm('Note wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-500 hover:underline ms-3">Löschen</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    @empty
        <div class="text-sm text-muted px-1">Keine Fachnoten gefunden.</div>
    @endforelse
</div>

{{-- Module --}}
<div class="space-y-2 pt-2">
    <div class="text-xs uppercase tracking-wider text-muted px-1">Module</div>

    @forelse($modulGroups as $modulId => $items)
        @php
            $m = $items->first()->modulBelegung->modul ?? null;
            $modulTitle = $m ? ($m->modul_nummer . ' – ' . $m->titel) : 'Modul';
            $avg = $weightedAvg($items);
        @endphp

        <details class="np-details bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
            <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between list-none hover:bg-card/60">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="np-chevron text-muted transition-transform duration-200 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <div class="font-semibold text-text truncate">{{ $modulTitle }}</div>
                        <div class="text-xs text-muted">{{ $items->count() }} Note(n)</div>
                    </div>
                </div>

                <div class="flex items-center gap-2 shrink-0">
                    <span class="text-xs text-muted">Ø</span>
                    <span class="inline-flex items-center justify-center min-w-[4rem] px-3 py-1 rounded-xl bg-bg text-text border border-border">
                        {{ $avg ?? '-' }}
                    </span>
                </div>
            </summary>

            <div class="border-t border-border">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="bg-bg text-muted">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                <th class="text-left p-3">Titel</th>
                                <th class="text-right p-3 whitespace-nowrap">Gew. %</th>
                                <th class="text-right p-3 whitespace-nowrap">Note</th>
                                @if($canManage)
                                    <th class="text-right p-3 whitespace-nowrap">Aktionen</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @foreach($items as $n)
                                <tr class="hover:bg-card/60">
                                    <td class="p-3 whitespace-nowrap">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</td>
                                    <td class="p-3">{{ $n->titel ?? '-' }}</td>
                                    <td class="p-3 text-right whitespace-nowrap">{{ $n->gewichtung_prozent ?? 100 }}</td>
                                    <td class="p-3 text-right font-semibold whitespace-nowrap">{{ $n->note_wert }}</td>

                                    @if($canManage)
                                        <td class="p-3 text-right whitespace-nowrap">
                                            <a class="text-accent hover:underline" href="{{ $editUrl ? $editUrl($n->note_id) : '#' }}">Bearbeiten</a>

                                            <form method="POST" action="{{ $destroyUrl ? $destroyUrl($n->note_id) : '#' }}" class="inline"
                                                  onsubmit="return confirm('Note wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-500 hover:underline ms-3">Löschen</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </details>
    @empty
        <div class="text-sm text-muted px-1">Keine Modulnoten gefunden.</div>
    @endforelse
</div>

{{-- Details Marker ausblenden + Chevron zuverlässig drehen --}}
<style>
    summary::-webkit-details-marker { display: none; }
    summary { list-style: none; }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('details.np-details').forEach((d) => {
            const chevron = d.querySelector('.np-chevron');
            if (!chevron) return;

            const sync = () => {
                if (d.open) chevron.classList.add('rotate-90');
                else chevron.classList.remove('rotate-90');
            };

            sync();
            d.addEventListener('toggle', sync);
        });
    });
</script>