<x-app-layout>
    <x-slot name="title">Noten</x-slot>
    @php
        $a = $auswertung;
        $semLabel = $semester->firstWhere('semester_id', (int) $selectedSemesterId)?->bezeichnung ?? 'Semester';
        $mit = fn (array $extra) => array_merge(request()->except(['page', '_open']), $extra);
        $semSchnitt = $selectedSemesterId ? $a->semester((int) $selectedSemesterId)['note'] : null;
        $ich = (int) auth()->user()->benutzer_id;
    @endphp

    <x-slot name="header">
        <div class="w-full flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Noten</h2>

            <div class="inline-flex items-center glass rounded-full p-1 gap-1" role="group" aria-label="Semester">
                <a @class(['inline-flex items-center justify-center w-9 h-9 rounded-full text-text hover:bg-accent/10', 'pointer-events-none opacity-30' => ! $prevSemesterId])
                   href="{{ $prevSemesterId ? route('lernender.noten.index', $mit(['semester_id' => $prevSemesterId])) : '#' }}" aria-label="Vorheriges Semester">‹</a>
                <span class="px-4 h-8 flex items-center rounded-full bg-accent/10 text-accent text-sm font-semibold whitespace-nowrap">{{ $semLabel }}</span>
                <a @class(['inline-flex items-center justify-center w-9 h-9 rounded-full text-text hover:bg-accent/10', 'pointer-events-none opacity-30' => ! $nextSemesterId])
                   href="{{ $nextSemesterId ? route('lernender.noten.index', $mit(['semester_id' => $nextSemesterId])) : '#' }}" aria-label="Nächstes Semester">›</a>
            </div>

            <div class="flex gap-2">
                <a href="{{ route('lernender.noten.drucken') }}" target="_blank" class="inline-flex items-center px-3 h-10 rounded-xl glass-btn text-text text-sm">Drucken</a>
                <a href="{{ route('lernender.noten.export') }}" class="inline-flex items-center px-3 h-10 rounded-xl glass-btn text-text text-sm">CSV</a>
                <a href="{{ route('lernender.noten.create') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">
                    <span class="text-lg leading-none">+</span> Note
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{ ansicht: @js(request('ansicht') === 'alle' ? 'alle' : 'semester') }">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            {{-- Stand im Semester --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-kachel label="Semester {{ $semLabel }}" :note="$semSchnitt" />
                <x-kachel label="Gesamtschnitt" :note="$a->gesamtNote" />
                @foreach($a->kategorien as $kid => $kat)
                    @php $kNote = $selectedSemesterId ? $a->semester((int) $selectedSemesterId, $kid)['note'] : null; @endphp
                    @if($kNote !== null)
                        <x-kachel :label="$a->konfiguration->kategorieName($kid)" :note="$kNote"
                                  :sub="'Lehrzeit '.\App\Support\NotenSkala::format($kat['note'], 1)"
                                  :href="route('lernender.noten.index', $mit(['kategorie_id' => $kid]))" />
                    @endif
                @endforeach
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1 p-0.5 rounded-xl bg-bg/60 border border-border text-sm" role="tablist">
                    <button type="button" role="tab" @click="ansicht = 'semester'" :aria-selected="ansicht === 'semester'" class="px-3 min-h-9 rounded-lg whitespace-nowrap" :class="ansicht === 'semester' ? 'bg-card text-accent shadow-sm' : 'text-muted'">Semester</button>
                    <button type="button" role="tab" @click="ansicht = 'alle'" :aria-selected="ansicht === 'alle'" class="px-3 min-h-9 rounded-lg whitespace-nowrap" :class="ansicht === 'alle' ? 'bg-card text-accent shadow-sm' : 'text-muted'">Zeugnisübersicht</button>
                </div>
                <div class="flex flex-wrap gap-1.5" x-show="ansicht === 'semester'">
                    <a href="{{ route('lernender.noten.index', $mit(['kategorie_id' => null])) }}"
                       @class(['px-3 min-h-9 inline-flex items-center rounded-full text-sm border transition-colors', 'border-accent/50 bg-accent/10 text-accent' => ! $kategorieId, 'border-border text-muted hover:text-text' => $kategorieId])>Alle</a>
                    @foreach($kategorien as $k)
                        <a href="{{ route('lernender.noten.index', $mit(['kategorie_id' => $k->kategorie_id])) }}"
                           @class(['px-3 min-h-9 inline-flex items-center rounded-full text-sm border transition-colors', 'border-accent/50 bg-accent/10 text-accent' => $kategorieId === $k->kategorie_id, 'border-border text-muted hover:text-text' => $kategorieId !== $k->kategorie_id])>{{ $k->name }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Zeugnisübersicht --}}
            <x-karte titel="Zeugnisnoten über die Lehrzeit" :polster="false" x-show="ansicht === 'alle'" x-cloak>
                <x-heatmap :daten="$heatmap" />
            </x-karte>

            {{-- Semester --}}
            <div class="flex flex-col gap-6" x-show="ansicht === 'semester'">
                @forelse($gruppen as $g)
                    <section class="flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-3 px-1">
                            <h3 class="text-xs uppercase tracking-widest text-muted font-semibold">{{ $g->name }}</h3>
                            <div class="flex items-center gap-2">
                                @if($g->promotion)
                                    <x-status :status="$g->promotion['erfuellt'] ? 'gruen' : 'rot'" :text="$g->promotion['erfuellt'] ? 'Promotion ok' : 'Promotion gefährdet'" />
                                @endif
                                <span class="text-xs text-muted">Schnitt</span>
                                <x-note :wert="$g->semester" :stellen="1" />
                            </div>
                        </div>

                        @foreach($g->elemente as $el)
                            @php
                                $e = $el->element;
                                $offen = $e?->offenGewicht();
                                $fortschritt = $e && $e->zielGewicht ? (int) min(100, round($e->gewichtSumme / $e->zielGewicht * 100)) : null;
                            @endphp
                            <details class="np-details glass rounded-2xl overflow-hidden">
                                <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between gap-3 list-none hover:bg-accent/5 transition-colors">
                                    <div class="flex items-center gap-3 min-w-0 flex-1">
                                        <span class="np-chevron text-muted transition-transform duration-200 shrink-0" aria-hidden="true">
                                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/></svg>
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="font-semibold text-text truncate">{{ $el->label }}</div>
                                            <div class="flex items-center gap-2 text-xs text-muted">
                                                <span>{{ $el->noten->count() === 1 ? '1 Prüfung' : $el->noten->count().' Prüfungen' }}</span>
                                                @if($fortschritt !== null)
                                                    <span aria-hidden="true">·</span>
                                                    <span class="flex items-center gap-1.5">
                                                        <span class="w-20 h-1 rounded-full bg-accent/15 overflow-hidden"><span class="block h-full {{ $fortschritt >= 100 ? 'bg-green-500' : 'bg-accent' }}" style="width: {{ $fortschritt }}%"></span></span>
                                                        {{ $fortschritt >= 100 ? 'abgeschlossen' : \App\Support\Zahl::prozent($offen).' offen' }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3 shrink-0">
                                        @if($e && $e->schnitt !== null && abs($e->schnitt - $e->note) > 0.001)
                                            <span class="hidden sm:inline text-xs text-muted tabular-nums" title="Schnitt vor Rundung">{{ \App\Support\NotenSkala::format($e->schnitt, 2) }}</span>
                                        @endif
                                        <x-note :wert="$e?->note" variante="hero" class="text-2xl min-w-12 text-right" />
                                    </div>
                                </summary>
                                <div class="border-t border-border divide-y divide-border">
                                    @foreach($el->noten as $n)
                                        @include('lernender.noten.partials.note', ['n' => $n, 'ich' => $ich])
                                    @endforeach
                                </div>
                            </details>
                        @endforeach
                    </section>
                @empty
                    <div class="glass rounded-2xl px-5 py-12 text-center">
                        <p class="text-sm text-muted">Keine Noten in {{ $semLabel }}</p>
                        <a href="{{ route('lernender.noten.create') }}" class="mt-4 inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">
                            <span class="text-lg leading-none">+</span> Note erfassen
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <script>
        function npTitelEdit(initial, url) {
            return {
                titel: initial,
                titelDraft: initial ?? '',
                editingTitel: false,
                savingTitel: false,
                titelError: false,
                startTitelEdit() {
                    this.titelDraft = this.titel ?? '';
                    this.titelError = false;
                    this.editingTitel = true;
                },
                async saveTitel() {
                    this.savingTitel = true;
                    this.titelError = false;
                    try {
                        const res = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                            },
                            body: JSON.stringify({ titel: this.titelDraft }),
                        });
                        if (res.ok) {
                            this.titel = (await res.json()).titel;
                            this.editingTitel = false;
                        } else {
                            this.titelError = true;
                        }
                    } catch (e) {
                        this.titelError = true;
                    } finally {
                        this.savingTitel = false;
                    }
                },
            };
        }

        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const offen = {{ session('opened_note') ? (int) session('opened_note') : ((int) request()->input('_open', 0) ?: 'null') }};

            document.querySelectorAll('details.np-details').forEach((d) => {
                const c = d.querySelector(':scope > summary .np-chevron');
                const sync = () => c?.classList.toggle('rotate-90', d.open);
                sync();
                d.addEventListener('toggle', sync);
            });

            document.querySelectorAll('details.np-note-detail').forEach((d) => {
                const c = d.querySelector('.np-chevron-note');
                d.addEventListener('toggle', () => {
                    c?.classList.toggle('rotate-90', d.open);
                    if (d.open) {
                        fetch(`/noten/${d.dataset.noteId}/gesehen`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } }).catch(() => {});
                    }
                });
            });

            const ziel = offen && document.querySelector(`details.np-note-detail[data-note-id="${offen}"]`);
            if (ziel) {
                ziel.closest('details.np-details').open = true;
                ziel.open = true;
                setTimeout(() => ziel.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60);
            }
        });
    </script>
</x-app-layout>
