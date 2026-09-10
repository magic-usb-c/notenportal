{{-- Eine Prüfung in der Notenliste des Lernenden: Kopfzeile, Notiz inline, Kommentare. Erwartet $n, $ich (benutzer_id) --}}
@php
    $eigeneSicht = $n->gesehen->firstWhere('viewer_benutzer_id', $ich);
    $bbHatGesehen = $n->gesehen->where('viewer_benutzer_id', '!=', $ich)->isNotEmpty();
    $neuerKommentar = $eigeneSicht
        ? $n->kommentare->contains(fn ($k) => $k->erstellt_am > $eigeneSicht->gesehen_am)
        : $n->kommentare->isNotEmpty();
    $letzter = $n->kommentare->last();
@endphp
<details class="np-note-detail group" data-note-id="{{ $n->note_id }}"
         x-data="npTitelEdit({{ json_encode($n->titel) }}, '{{ route('lernender.noten.titel.update', $n->note_id) }}')">
    <summary class="cursor-pointer select-none list-none px-4 py-3 flex items-start justify-between gap-3 hover:bg-accent/5 transition-colors">
        <div class="flex items-start gap-2 min-w-0">
            <span class="np-chevron-note text-muted transition-transform duration-200 shrink-0 mt-1" aria-hidden="true">
                <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/></svg>
            </span>
            <div class="min-w-0 flex flex-col gap-0.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-sm text-muted tabular-nums">{{ $n->pruefungsdatum?->format('d.m.Y') }}</span>
                    <span class="text-sm text-text truncate" x-text="titel || ''">{{ $n->titel }}</span>
                    @if($bbHatGesehen)
                        <span title="Von der Berufsbildnerin oder dem Berufsbildner gesehen" class="text-green-600 dark:text-green-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-label="gesehen"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </span>
                    @endif
                    @if($neuerKommentar)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-accent text-white">Neu</span>
                    @endif
                </div>
                <x-note-geaendert :note="$n" :lernender-benutzer-id="$ich" />
                @if($letzter)
                    <div class="text-xs text-muted italic truncate max-w-xs">
                        <span class="font-medium not-italic">{{ $letzter->autor?->vorname }}</span>: {{ Str::limit($letzter->kommentar_text, 70) }}
                    </div>
                @endif
            </div>
        </div>
        <div class="shrink-0 flex items-center gap-3">
            <span class="text-xs text-muted tabular-nums">{{ \App\Support\Zahl::prozent($n->gewichtung_prozent ?? 100) }}</span>
            <x-note :wert="$n->note_wert" class="text-xl font-bold min-w-11 text-right" />
            <div class="flex gap-1 text-xs" onclick="event.stopPropagation()">
                <a class="inline-flex items-center px-2.5 min-h-9 rounded-lg text-accent hover:bg-accent/10" href="{{ route('lernender.noten.edit', $n->note_id) }}">Bearbeiten</a>
                <form method="POST" action="{{ route('lernender.noten.destroy', $n->note_id) }}"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                      onsubmit="return confirm('Note wirklich löschen?');">
                    @csrf
                    @method('DELETE')
                    <button :disabled="loading" class="inline-flex items-center px-2.5 min-h-9 rounded-lg text-red-600 dark:text-red-400 hover:bg-red-500/10 disabled:opacity-60">Löschen</button>
                </form>
            </div>
        </div>
    </summary>

    <div class="border-t border-border bg-bg/60">
        <div class="px-5 pt-3 flex items-center gap-2 text-sm">
            <span class="text-xs font-semibold uppercase tracking-wider text-muted shrink-0">Notiz</span>
            <template x-if="!editingTitel">
                <button type="button" @click="startTitelEdit()" class="inline-flex items-center gap-1.5 text-left hover:text-accent min-w-0"
                        :class="titel ? 'text-text' : 'text-muted italic'" aria-label="Notiz bearbeiten">
                    <span class="truncate" x-text="titel || 'Ergänzen'"></span>
                    <svg class="w-3.5 h-3.5 shrink-0 opacity-40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
            </template>
            <template x-if="editingTitel">
                <div class="flex-1 flex items-center gap-2">
                    <input type="text" x-model="titelDraft" maxlength="150" aria-label="Notiz"
                           @keydown.enter.prevent="saveTitel()" @keydown.escape.stop="editingTitel = false" x-init="$el.focus()"
                           class="flex-1 rounded-lg border border-border bg-input text-text text-sm px-2 py-1 focus:ring-2 focus:ring-ring focus:border-ring">
                    <button type="button" @click="saveTitel()" :disabled="savingTitel" class="px-2.5 min-h-9 rounded-lg bg-accent text-white text-xs np-btn-primary disabled:opacity-60">OK</button>
                    <button type="button" @click="editingTitel = false" aria-label="Abbrechen" class="min-h-9 min-w-9 inline-flex items-center justify-center rounded-lg border border-border text-sm text-muted hover:text-text">×</button>
                    <span x-show="titelError" x-cloak class="text-xs text-red-600 dark:text-red-400 shrink-0">Nicht gespeichert</span>
                </div>
            </template>
        </div>

        <div class="px-5 py-4 flex flex-col gap-2">
            <div class="text-xs font-semibold uppercase tracking-wider text-muted">Kommentare</div>
            @forelse($n->kommentare as $k)
                <div class="bg-card rounded-xl p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-xs text-muted">
                            <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span> · {{ $k->erstellt_am->format('d.m.Y H:i') }}
                        </div>
                        @if((int) $k->autor_benutzer_id === $ich)
                            <form method="POST" action="{{ route('noten.kommentare.destroy', $k->kommentar_id) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  onsubmit="return confirm('Kommentar wirklich löschen?');">
                                @csrf
                                @method('DELETE')
                                <button :disabled="loading" class="inline-flex items-center px-2 min-h-9 rounded-lg text-xs text-red-600 dark:text-red-400 hover:bg-red-500/10 disabled:opacity-60">Löschen</button>
                            </form>
                        @endif
                    </div>
                    <div class="text-sm text-text whitespace-pre-line">{{ $k->kommentar_text }}</div>
                </div>
            @empty
                <div class="text-sm text-muted">Noch keine Kommentare</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('noten.kommentare.store', $n->note_id) }}" class="border-t border-border px-5 py-4 flex gap-2"
              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <input type="text" name="kommentar_text" placeholder="Kommentar schreiben" aria-label="Kommentar schreiben" maxlength="2000" required
                   class="flex-1 rounded-xl border border-border bg-input text-text placeholder:text-muted text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
            <button type="submit" :disabled="loading" class="px-4 min-h-10 rounded-xl bg-accent text-white text-sm np-btn-primary disabled:opacity-60">Senden</button>
        </form>
    </div>
</details>
