{{-- resources/views/lernender/noten/partials/notes-table.blade.php --}}
@props([
    'fachGroups',
    'modulGroups',
    'weightedAvg',       // callable($items): string|null
    'canManage' => false,
    'editUrl'   => null,  // callable(note_id): string
    'destroyUrl' => null, // callable(note_id): string
])

@php
    $currentBenutzerId = (int) auth()->user()->benutzer_id;

    /**
     * Badge-Berechnung pro Note:
     * - $bbHatGesehen:  mind. ein gesehen-Eintrag von jemand anderem als dem Lernenden
     * - $neuerKommentar: Kommentar existiert, der nach dem letzten gesehen-Eintrag des Lernenden kam
     */
    $noteInfo = function(\App\Models\Note $n) use ($currentBenutzerId): array {
        $lernenderGesehen = $n->gesehen
            ->firstWhere('viewer_benutzer_id', $currentBenutzerId);
        $bbHatGesehen = $n->gesehen
            ->where('viewer_benutzer_id', '!=', $currentBenutzerId)
            ->isNotEmpty();
        $neuerKommentar = $lernenderGesehen
            ? $n->kommentare->filter(fn($k) => $k->erstellt_am > $lernenderGesehen->gesehen_am)->isNotEmpty()
            : $n->kommentare->isNotEmpty();
        $newestKommentar = $n->kommentare->last();
        return compact('bbHatGesehen', 'neuerKommentar', 'newestKommentar');
    };

    // Farblogik (text-only): Note/Durchschnitt
    $noteColorClass = function ($val): string {
        if ($val === null || $val === '') return 'text-muted';
        $v = (float) $val;
        if ($v >= 5.0) return 'text-green-600 dark:text-green-400';
        if ($v >= 4.0) return 'text-emerald-600 dark:text-emerald-400';
        if ($v >= 3.5) return 'text-yellow-600 dark:text-yellow-400';
        return 'text-red-600 dark:text-red-400';
    };
@endphp

{{-- Fächer --}}
<div class="space-y-2">
    <div class="text-xs uppercase tracking-wider text-muted px-1">Fächer</div>

    @forelse($fachGroups as $fachId => $items)
        @php
            $fachName = $items->first()->fach->name ?? 'Fach';
            $avg = $weightedAvg($items);
        @endphp

        <details class="np-details glass rounded-2xl overflow-hidden">
            <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between list-none hover:bg-accent/5 transition-colors duration-100">
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
                <div class="flex items-baseline gap-2 shrink-0">
                    <span class="text-xs text-muted">Ø</span>
                    <span class="text-xl font-bold tabular-nums {{ $noteColorClass($avg) }}">
                        {{ $avg ?? '–' }}
                    </span>
                </div>
            </summary>

            <div class="border-t border-border divide-y divide-border">
                @foreach($items as $n)
                    @php
                        ['bbHatGesehen' => $bbHatGesehen, 'neuerKommentar' => $neuerKommentar, 'newestKommentar' => $newestKommentar] = $noteInfo($n);
                        $noteWert = (float) $n->note_wert;
                    @endphp

                    <details class="np-note-detail group" data-note-id="{{ $n->note_id }}">
                        <summary class="cursor-pointer select-none list-none px-4 py-3 flex items-start justify-between gap-3 hover:bg-accent/5 transition-colors duration-100">
                            {{-- Linke Seite --}}
                            <div class="flex items-start gap-2 min-w-0">
                                <span class="np-chevron-note text-muted transition-transform duration-200 shrink-0 mt-1">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                                <div class="min-w-0 space-y-0.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm text-muted tabular-nums">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</span>
                                        <span class="text-sm text-muted truncate">{{ $n->titel ?? '–' }}</span>

                                        {{-- BB-gesehen Icon --}}
                                        @if($bbHatGesehen)
                                            <span title="Berufsbildner hat diese Note gesehen" class="text-green-500">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </span>
                                        @endif

                                        {{-- "Neu" Badge: neuer Kommentar --}}
                                        @if($neuerKommentar)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-accent text-white">
                                                Neu
                                            </span>
                                        @endif
                                    </div>

                                    {{-- Kommentar-Vorschau --}}
                                    @if($newestKommentar)
                                        <div class="text-xs text-muted italic truncate max-w-xs">
                                            <span class="font-medium not-italic">{{ $newestKommentar->autor?->vorname }}</span>:
                                            {{ Str::limit($newestKommentar->kommentar_text, 70) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Rechte Seite --}}
                            <div class="shrink-0 flex items-center gap-3">
                                <span class="text-xs text-muted tabular-nums">{{ $n->gewichtung_prozent ?? 100 }}%</span>
                                <span class="text-xl font-bold tabular-nums min-w-[2.75rem] text-right {{ $noteColorClass($noteWert) }}">
                                    {{ number_format($noteWert, 1) }}
                                </span>
                                @if($canManage)
                                    <div class="flex gap-2 text-xs" onclick="event.stopPropagation()">
                                        <a class="text-accent hover:underline" href="{{ $editUrl ? $editUrl($n->note_id) : '#' }}">Bearbeiten</a>
                                        <form method="POST" action="{{ $destroyUrl ? $destroyUrl($n->note_id) : '#' }}" class="inline"
                                              onsubmit="return confirm('Note wirklich löschen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-500 hover:underline">Löschen</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </summary>

                        {{-- Ausgeklappter Bereich: Kommentar-Thread --}}
                        <div class="border-t border-border bg-bg">
                            {{-- Kommentare --}}
                            <div class="px-5 py-4 space-y-2">
                                <div class="text-xs font-semibold uppercase tracking-wider text-muted">Kommentare</div>

                                @forelse($n->kommentare as $k)
                                    @php
                                        $canDeleteKommentar = (int)$k->autor_benutzer_id === $currentBenutzerId
                                            || auth()->user()->hasRole('Admin');
                                    @endphp
                                    <div class="bg-card rounded-xl p-3 space-y-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="text-xs text-muted">
                                                <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                &middot;
                                                {{ $k->erstellt_am->format('d.m.Y H:i') }} Uhr
                                            </div>
                                            @if($canDeleteKommentar)
                                                <form method="POST"
                                                      action="{{ route('noten.kommentare.destroy', $k->kommentar_id) }}"
                                                      class="shrink-0"
                                                      onsubmit="return confirm('Kommentar wirklich löschen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs text-red-400 hover:text-red-600">Löschen</button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="text-sm text-text whitespace-pre-line">{{ $k->kommentar_text }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-muted">Noch keine Kommentare.</div>
                                @endforelse
                            </div>

                            {{-- Neuer Kommentar (Lernender kann kommentieren) --}}
                            <div class="border-t border-border px-5 py-4">
                                <form method="POST"
                                      action="{{ route('noten.kommentare.store', $n->note_id) }}">
                                    @csrf
                                    <div class="flex gap-2">
                                        <input type="text"
                                               name="kommentar_text"
                                               placeholder="Kommentar schreiben…"
                                               class="flex-1 rounded-xl border border-border bg-input text-text placeholder-muted text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring"
                                               maxlength="2000"
                                               required>
                                        <button type="submit"
                                                class="px-4 py-2 rounded-xl bg-accent text-white text-sm hover:opacity-90 whitespace-nowrap">
                                            Senden
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </details>
                @endforeach
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

            // Fortschritt: aktuelle Gewichtungs-Summe vs. Ziel-Summe aus module-Tabelle
            $gewSumme = 0.0;
            foreach ($items as $n) {
                $gewSumme += ($n->gewichtung_prozent === null || $n->gewichtung_prozent === '') ? 100.0 : (float)$n->gewichtung_prozent;
            }
            $zielSumme = $m && $m->ziel_gewicht_summe_default ? (float)$m->ziel_gewicht_summe_default : null;
            // Fallback: wenn kein Ziel-Gewicht konfiguriert ist, nutze "10 Noten = 100%" als groben Indikator.
            $progressPct = $zielSumme && $zielSumme > 0
                ? min(100, (int) round($gewSumme / $zielSumme * 100))
                : min(100, (int) round($items->count() / 10 * 100));
        @endphp

        <details class="np-details glass rounded-2xl overflow-hidden">
            <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between list-none hover:bg-accent/5 transition-colors duration-100">
                <div class="flex items-center gap-3 min-w-0 flex-1">
                    <span class="np-chevron text-muted transition-transform duration-200 shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-text truncate">{{ $modulTitle }}</span>
                            @if($progressPct !== null && $progressPct >= 100)
                                <span title="Modul vollständig abgeschlossen" class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                    ✓ Abgeschlossen
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <span class="text-xs text-muted whitespace-nowrap">
                                @if($progressPct !== null)
                                    {{ $progressPct }}% &middot; {{ $items->count() }} Note(n)
                                @else
                                    {{ $items->count() }} Note(n)
                                @endif
                            </span>
                            @if($progressPct !== null)
                                <div class="flex-1 max-w-[160px] h-1 rounded-full bg-accent/20 overflow-hidden">
                                    <div class="h-full {{ $progressPct >= 100 ? 'bg-green-500' : 'bg-accent' }}" style="width: {{ $progressPct }}%"></div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-baseline gap-2 shrink-0">
                    <span class="text-xs text-muted">Ø</span>
                    <span class="text-xl font-bold tabular-nums {{ $noteColorClass($avg) }}">
                        {{ $avg ?? '–' }}
                    </span>
                </div>
            </summary>

            <div class="border-t border-border divide-y divide-border">
                @foreach($items as $n)
                    @php
                        ['bbHatGesehen' => $bbHatGesehen, 'neuerKommentar' => $neuerKommentar, 'newestKommentar' => $newestKommentar] = $noteInfo($n);
                        $noteWert = (float) $n->note_wert;
                    @endphp

                    <details class="np-note-detail" data-note-id="{{ $n->note_id }}">
                        <summary class="cursor-pointer select-none list-none px-4 py-3 flex items-start justify-between gap-3 hover:bg-accent/5 transition-colors duration-100">
                            <div class="flex items-start gap-2 min-w-0">
                                <span class="np-chevron-note text-muted transition-transform duration-200 shrink-0 mt-1">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                                <div class="min-w-0 space-y-0.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm text-muted tabular-nums">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</span>
                                        <span class="text-sm text-muted truncate">{{ $n->titel ?? '–' }}</span>

                                        @if($bbHatGesehen)
                                            <span title="Berufsbildner hat diese Note gesehen" class="text-green-500">
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </span>
                                        @endif

                                        @if($neuerKommentar)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-accent text-white">
                                                Neu
                                            </span>
                                        @endif
                                    </div>

                                    @if($newestKommentar)
                                        <div class="text-xs text-muted italic truncate max-w-xs">
                                            <span class="font-medium not-italic">{{ $newestKommentar->autor?->vorname }}</span>:
                                            {{ Str::limit($newestKommentar->kommentar_text, 70) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="shrink-0 flex items-center gap-3">
                                <span class="text-xs text-muted tabular-nums">{{ $n->gewichtung_prozent ?? 100 }}%</span>
                                <span class="text-xl font-bold tabular-nums min-w-[2.75rem] text-right {{ $noteColorClass($noteWert) }}">
                                    {{ number_format($noteWert, 1) }}
                                </span>
                                @if($canManage)
                                    <div class="flex gap-2 text-xs" onclick="event.stopPropagation()">
                                        <a class="text-accent hover:underline" href="{{ $editUrl ? $editUrl($n->note_id) : '#' }}">Bearbeiten</a>
                                        <form method="POST" action="{{ $destroyUrl ? $destroyUrl($n->note_id) : '#' }}" class="inline"
                                              onsubmit="return confirm('Note wirklich löschen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-500 hover:underline">Löschen</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </summary>

                        <div class="border-t border-border bg-bg">
                            <div class="px-5 py-4 space-y-2">
                                <div class="text-xs font-semibold uppercase tracking-wider text-muted">Kommentare</div>

                                @forelse($n->kommentare as $k)
                                    @php
                                        $canDeleteKommentar = (int)$k->autor_benutzer_id === $currentBenutzerId
                                            || auth()->user()->hasRole('Admin');
                                    @endphp
                                    <div class="bg-card rounded-xl p-3 space-y-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="text-xs text-muted">
                                                <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                &middot;
                                                {{ $k->erstellt_am->format('d.m.Y H:i') }} Uhr
                                            </div>
                                            @if($canDeleteKommentar)
                                                <form method="POST"
                                                      action="{{ route('noten.kommentare.destroy', $k->kommentar_id) }}"
                                                      class="shrink-0"
                                                      onsubmit="return confirm('Kommentar wirklich löschen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs text-red-400 hover:text-red-600">Löschen</button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="text-sm text-text whitespace-pre-line">{{ $k->kommentar_text }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-muted">Noch keine Kommentare.</div>
                                @endforelse
                            </div>

                            <div class="border-t border-border px-5 py-4">
                                <form method="POST"
                                      action="{{ route('noten.kommentare.store', $n->note_id) }}">
                                    @csrf
                                    <div class="flex gap-2">
                                        <input type="text"
                                               name="kommentar_text"
                                               placeholder="Kommentar schreiben…"
                                               class="flex-1 rounded-xl border border-border bg-input text-text placeholder-muted text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring"
                                               maxlength="2000"
                                               required>
                                        <button type="submit"
                                                class="px-4 py-2 rounded-xl bg-accent text-white text-sm hover:opacity-90 whitespace-nowrap">
                                            Senden
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </details>
                @endforeach
            </div>
        </details>
    @empty
        <div class="text-sm text-muted px-1">Keine Modulnoten gefunden.</div>
    @endforelse
</div>

<style>
    summary::-webkit-details-marker { display: none; }
    summary { list-style: none; }
</style>

<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const openedNoteId = {{ session('opened_note')
        ? (int) session('opened_note')
        : ((int) request()->input('_open', 0) > 0 ? (int) request()->input('_open') : 'null') }};

    const markGesehen = (noteId) => {
        fetch('/noten/' + noteId + '/gesehen', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        }).catch(() => {});
    };

    const openNoteDetail = (noteDetail) => {
        if (!noteDetail) return;
        // Eltern-Accordion (Fach/Modul) öffnen
        const parent = noteDetail.closest('details.np-details');
        if (parent && !parent.open) parent.open = true;
        // Note selbst öffnen
        if (!noteDetail.open) noteDetail.open = true;
        // Smooth scrollen
        setTimeout(() => noteDetail.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
    };

    document.addEventListener('DOMContentLoaded', () => {
        // Chevron-Animation für Fach/Modul-Gruppen
        document.querySelectorAll('details.np-details').forEach((d) => {
            const chevron = d.querySelector(':scope > summary .np-chevron');
            if (!chevron) return;
            const sync = () => d.open
                ? chevron.classList.add('rotate-90')
                : chevron.classList.remove('rotate-90');
            sync();
            d.addEventListener('toggle', sync);
        });

        // Chevron-Animation für einzelne Noten + "gesehen"-Markierung beim Öffnen
        document.querySelectorAll('details.np-note-detail').forEach((d) => {
            const chevron = d.querySelector('.np-chevron-note');
            const noteId  = d.dataset.noteId;

            const sync = () => {
                if (d.open) {
                    chevron?.classList.add('rotate-90');
                    if (noteId) markGesehen(noteId);
                } else {
                    chevron?.classList.remove('rotate-90');
                }
            };
            sync();
            d.addEventListener('toggle', sync);
        });

        // Kommentar-Accordion: nach Kommentar-Absenden automatisch öffnen
        if (openedNoteId) {
            const target = document.querySelector(`details.np-note-detail[data-note-id="${openedNoteId}"]`);
            openNoteDetail(target);
        }
    });
}());
</script>
