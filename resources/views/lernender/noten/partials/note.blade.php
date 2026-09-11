{{-- Eine Prüfung in der Notenliste des Lernenden: Kopfzeile, Notiz inline, Kommentare. Erwartet $n, $ich (benutzer_id) --}}
@php
    $eigeneSicht = $n->gesehen->firstWhere('viewer_benutzer_id', $ich);
    $bbHatGesehen = $n->gesehen->where('viewer_benutzer_id', '!=', $ich)->isNotEmpty();
    $neuerKommentar = $eigeneSicht
        ? $n->kommentare->contains(fn ($k) => $k->erstellt_am > $eigeneSicht->gesehen_am)
        : $n->kommentare->isNotEmpty();
    $letzter = $n->kommentare->last();
    $feld = 'h-9 rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30';
@endphp
<details class="np-note-detail" data-note-id="{{ $n->note_id }}"
         x-data="npTitelEdit({{ json_encode($n->titel) }}, '{{ route('learner.grades.title.update', $n->note_id) }}')">
    <summary class="flex min-h-12 cursor-pointer select-none list-none items-center justify-between gap-3 py-2 pl-4 pr-3 transition-colors duration-100 hover:bg-surface-2/60">
        <div class="flex min-w-0 items-center gap-2">
            <span class="np-chevron-note shrink-0 text-muted transition-transform duration-200" aria-hidden="true">
                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/></svg>
            </span>
            <div class="flex min-w-0 flex-col gap-0.5">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-sm">
                    <span class="tabular-nums text-muted">{{ $n->pruefungsdatum?->format('d.m.Y') }}</span>
                    <span class="truncate text-text" x-text="titel || ''">{{ $n->titel }}</span>
                    @if($bbHatGesehen)
                        <svg class="size-4 shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" role="img" aria-label="{{ __('Von der Berufsbildnerin oder dem Berufsbildner gesehen') }}"><title>{{ __('Von der Berufsbildnerin oder dem Berufsbildner gesehen') }}</title><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    @endif
                    @if($neuerKommentar)
                        <span class="inline-flex h-5 items-center rounded-md bg-accent/12 px-1.5 text-xs font-medium text-accent-text">{{ __('Neu') }}</span>
                    @endif
                </div>
                <x-note-geaendert :note="$n" :lernender-benutzer-id="$ich" />
                @if($letzter)
                    <div class="max-w-xs truncate text-xs text-muted">
                        <span class="font-medium">{{ $letzter->autor?->vorname }}</span>: {{ Str::limit($letzter->kommentar_text, 70) }}
                    </div>
                @endif
            </div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <span class="hidden text-xs tabular-nums text-muted sm:inline">{{ \App\Support\Zahl::prozent($n->gewichtung_prozent ?? 100) }}</span>
            <x-note :wert="$n->note_wert" variante="badge" />
            <div class="flex items-center" onclick="event.stopPropagation()">
                <a href="{{ route('learner.grades.edit', $n->note_id) }}" aria-label="{{ __('Note bearbeiten') }}" title="{{ __('Bearbeiten') }}"
                   @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Note bearbeiten')) })"
                   class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-2 hover:text-text">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L6.832 19.82a4.5 4.5 0 01-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 011.13-1.897L16.863 4.487z"/></svg>
                </a>
                <form method="POST" action="{{ route('learner.grades.destroy', $n->note_id) }}"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                      onsubmit="return confirm(@js(__('Note wirklich löschen?')));">
                    @csrf
                    @method('DELETE')
                    <button :disabled="loading" aria-label="{{ __('Note löschen') }}" title="{{ __('Löschen') }}"
                            class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-note-ungenuegend/10 hover:text-note-ungenuegend disabled:opacity-50">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                    </button>
                </form>
            </div>
        </div>
    </summary>

    <div class="border-t border-border bg-surface-2/40">
        <div class="flex items-center gap-2 px-5 pt-3 text-sm">
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
                    <button type="button" @click="saveTitel()" :disabled="savingTitel" class="inline-flex h-9 items-center rounded-lg glass-btn px-3 text-sm font-medium text-text disabled:opacity-50">OK</button>
                    <button type="button" @click="editingTitel = false" aria-label="{{ __('Abbrechen') }}" class="inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-2 hover:text-text">×</button>
                    <span x-show="titelError" x-cloak class="shrink-0 text-xs text-note-ungenuegend">{{ __('Nicht gespeichert') }}</span>
                </div>
            </template>
        </div>

        <div class="flex flex-col gap-2 px-5 py-4">
            <div class="text-xs font-medium text-muted">{{ __('Kommentare') }}</div>
            @forelse($n->kommentare as $k)
                <div class="rounded-lg border border-border bg-card p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="text-xs text-muted">
                            <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span> · {{ $k->erstellt_am->format('d.m.Y H:i') }}
                        </div>
                        @if((int) $k->autor_benutzer_id === $ich)
                            <form method="POST" action="{{ route('comments.destroy', $k->kommentar_id) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  onsubmit="return confirm(@js(__('Kommentar wirklich löschen?')));">
                                @csrf
                                @method('DELETE')
                                <button :disabled="loading" class="inline-flex h-8 items-center rounded-lg px-2 text-xs text-note-ungenuegend hover:bg-note-ungenuegend/10 disabled:opacity-50">{{ __('Löschen') }}</button>
                            </form>
                        @endif
                    </div>
                    <div class="whitespace-pre-line text-sm text-text">{{ $k->kommentar_text }}</div>
                </div>
            @empty
                <div class="text-sm text-muted">{{ __('Noch keine Kommentare') }}</div>
            @endforelse
        </div>

        <form method="POST" action="{{ route('comments.store', $n->note_id) }}" class="flex gap-2 border-t border-border px-5 py-4"
              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <input type="text" name="kommentar_text" placeholder="{{ __('Kommentar schreiben') }}" aria-label="{{ __('Kommentar schreiben') }}" maxlength="2000" required
                   class="min-w-0 flex-1 {{ $feld }}">
            <button type="submit" :disabled="loading" class="inline-flex h-9 items-center rounded-lg glass-btn px-3.5 text-sm font-medium text-text disabled:opacity-50">{{ __('Senden') }}</button>
        </form>
    </div>
</details>
