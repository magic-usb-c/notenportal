{{-- Eine Prüfung in der Notenliste des Lernenden: Kopfzeile, Notiz inline, Kommentare. Erwartet $n, $ich (benutzer_id) --}}
@php
    $eigeneSicht = $n->gesehen->firstWhere('viewer_benutzer_id', $ich);
    $bbHatGesehen = $n->gesehen->where('viewer_benutzer_id', '!=', $ich)->isNotEmpty();
    // Wie bei Berufsbildnern: nur Kommentare anderer, die nach dem letzten Öffnen kamen
    $neuerKommentar = $n->kommentare->contains(fn ($k) => (int) $k->autor_benutzer_id !== (int) $ich
        && (! $eigeneSicht || $k->erstellt_am > $eigeneSicht->gesehen_am));
    $letzter = $n->kommentare->last();
    $feld = 'np-feld';
@endphp
<details class="np-note-detail" data-note-id="{{ $n->note_id }}"
         x-data="npTitelEdit({{ json_encode($n->titel) }}, '{{ route('learner.grades.title.update', $n->note_id) }}')">
    {{-- Raster wie die Spalten der Notentabelle (lernender/noten/index): Gewicht unter «Prüfungen», Note unter «Schnitt» --}}
    <summary class="grid min-h-11 cursor-pointer select-none list-none grid-cols-[minmax(0,1fr)_18rem_6rem_7rem] items-center py-1.5 transition-colors duration-100 hover:bg-surface-2/60">
        <div class="flex min-w-0 items-center gap-2 pl-10 pr-3">
            <span class="np-chevron shrink-0 text-muted" aria-hidden="true"><x-symbol name="chevron-right" strich="2" class="size-3" /></span>
            <div class="flex min-w-0 flex-col gap-0.5">
                <div class="flex min-w-0 items-center gap-2 text-sm">
                    <time datetime="{{ $n->pruefungsdatum?->toDateString() }}" class="shrink-0 tabular-nums text-muted">{{ $n->pruefungsdatum?->format('d.m.Y') }}</time>
                    <span class="truncate text-text" x-text="titel || ''">{{ $n->titel }}</span>
                    @if($bbHatGesehen)
                        <span class="inline-flex shrink-0 text-muted" role="img" aria-label="{{ __('Von der Berufsbildnerin oder dem Berufsbildner gesehen') }}" title="{{ __('Von der Berufsbildnerin oder dem Berufsbildner gesehen') }}"><x-symbol name="eye" class="size-4" /></span>
                    @endif
                    @if($neuerKommentar)
                        <span class="np-marke shrink-0 bg-accent/12 text-accent-text">{{ __('Neu') }}</span>
                    @endif
                </div>
                <x-note-geaendert :note="$n" :lernender-benutzer-id="$ich" />
                @if($letzter)
                    <div class="truncate text-xs text-muted">
                        <span class="font-medium">{{ $letzter->autor?->vorname }}</span>: {{ Str::limit($letzter->kommentar_text, 120) }}
                    </div>
                @endif
            </div>
        </div>
        <span class="px-3 text-xs tabular-nums text-muted">{{ \App\Support\Zahl::prozent($n->gewichtung_prozent ?? 100) }}</span>
        <span class="px-3 text-right"><x-note :wert="$n->note_wert" :stufe="$n->note_stufe" /></span>
        <div class="flex items-center justify-end pr-2.5" x-on:click.stop>
            <a href="{{ route('learner.grades.edit', $n->note_id) }}" aria-label="{{ __('Note bearbeiten') }}" title="{{ __('Bearbeiten') }}"
               @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Note bearbeiten')) })"
               class="np-knopf np-knopf-symbol"><x-symbol name="pencil-square" />
            </a>
            <form method="POST" action="{{ route('learner.grades.destroy', $n->note_id) }}"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                  data-bestaetigen="{{ __('Note löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}">
                @csrf
                @method('DELETE')
                <button :disabled="loading" aria-label="{{ __('Note löschen') }}" title="{{ __('Löschen') }}"
                        class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr"><x-symbol name="trash" />
                </button>
            </form>
        </div>
    </summary>

    <div class="border-t border-border bg-fill-2">
        <div class="flex items-center gap-2 pl-15 pr-5 pt-3 text-sm">
            <span class="shrink-0 text-xs font-medium text-muted">{{ __('Notiz') }}</span>
            <template x-if="!editingTitel">
                <button type="button" @click="startTitelEdit()" class="inline-flex min-h-9 min-w-0 items-center gap-1.5 text-left hover:text-accent-text"
                        :class="titel ? 'text-text' : 'text-muted'" aria-label="{{ __('Notiz bearbeiten') }}">
                    <span class="truncate" x-text="titel || @js(__('Ergänzen'))"></span>
                    <svg class="size-3.5 shrink-0 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </button>
            </template>
            <template x-if="editingTitel">
                <div class="flex flex-1 items-center gap-2">
                    <input type="text" x-model="titelDraft" maxlength="150" aria-label="{{ __('Notiz') }}"
                           @keydown.enter.prevent="saveTitel()" @keydown.escape.stop="editingTitel = false" x-init="$el.focus()"
                           class="flex-1 {{ $feld }}">
                    <button type="button" @click="saveTitel()" :disabled="savingTitel" class="np-knopf np-knopf-sekundaer">OK</button>
                    <button type="button" @click="editingTitel = false" aria-label="{{ __('Abbrechen') }}" class="np-knopf np-knopf-symbol"><x-symbol name="x-mark" strich="2" /></button>
                    <span x-show="titelError" x-cloak class="shrink-0 text-xs text-note-ungenuegend">{{ __('Nicht gespeichert') }}</span>
                </div>
            </template>
        </div>

        <div class="flex max-w-4xl flex-col gap-2 pl-15 pr-5 py-4">
            <div class="text-xs font-medium text-muted">{{ __('Kommentare') }}</div>
            @forelse($n->kommentare as $k)
                <div class="rounded-xl bg-card px-3.5 py-2.5 shadow-e1">
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-xs text-muted">
                            <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span> · {{ $k->erstellt_am->format('d.m.Y H:i') }}
                        </div>
                        @if((int) $k->autor_benutzer_id === $ich)
                            <form method="POST" action="{{ route('comments.destroy', $k->kommentar_id) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  data-bestaetigen="{{ __('Kommentar löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}">
                                @csrf
                                @method('DELETE')
                                <button :disabled="loading" aria-label="{{ __('Kommentar löschen') }}" title="{{ __('Löschen') }}" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr np-knopf-klein"><x-symbol name="trash" /></button>
                            </form>
                        @endif
                    </div>
                    <div class="whitespace-pre-line text-sm text-text">{{ $k->kommentar_text }}</div>
                </div>
            @empty
                <div class="text-sm text-muted">{{ __('Noch keine Kommentare') }}</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('comments.store', $n->note_id) }}" class="flex gap-2 border-t border-border py-3 pl-15 pr-4"
              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <input type="text" name="kommentar_text" placeholder="{{ __('Kommentar schreiben') }}" aria-label="{{ __('Kommentar schreiben') }}" maxlength="2000" required
                   class="min-w-0 max-w-4xl flex-1 {{ $feld }}">
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Senden') }}</button>
        </form>
    </div>
</details>
