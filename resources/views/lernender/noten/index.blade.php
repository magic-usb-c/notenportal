<x-app-layout>
    <x-slot name="title">Noten</x-slot>
    @php
        $a = $auswertung;
        $semLabel = $semester->firstWhere('semester_id', (int) $selectedSemesterId)?->bezeichnung ?? 'Semester';
        $mit = fn (array $extra) => array_merge(request()->except(['page', '_open']), $extra);
        $semSchnitt = $selectedSemesterId ? $a->semester((int) $selectedSemesterId)['note'] : null;
        $ich = (int) auth()->user()->benutzer_id;
        $offeneNote = (int) (session('opened_note') ?: request()->input('_open', 0));

        // Statuszeile: Semester und Gesamt immer (leer = «–»), Kategorien nur mit Note im Semester
        $status = [['Semester', $semSchnitt], ['Gesamt', $a->gesamtNote]];
        foreach ($a->kategorien as $kid => $kat) {
            $kNote = $selectedSemesterId ? $a->semester((int) $selectedSemesterId, $kid)['note'] : null;
            if ($kNote !== null) {
                $status[] = [$a->konfiguration->kategorieName($kid), $kNote];
            }
        }
        $chip = 'inline-flex h-8 items-center rounded-full border px-3 text-sm transition-colors duration-100';
        $segment = 'h-8 whitespace-nowrap rounded-md px-3 text-muted transition-colors duration-150 aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs';
        $pfeil = 'inline-flex size-8 items-center justify-center rounded-md text-muted hover:bg-surface-2 hover:text-text';
    @endphp

    <x-slot name="header">
        <x-seitenkopf titel="Noten">
            <div class="inline-flex h-9 items-center gap-0.5 rounded-lg border border-border-strong/60 bg-card p-0.5" role="group" aria-label="Semester">
                <a @class([$pfeil, 'pointer-events-none opacity-30' => ! $prevSemesterId])
                   href="{{ $prevSemesterId ? route('learner.grades.index', $mit(['semester_id' => $prevSemesterId])) : '#' }}" aria-label="Vorheriges Semester">‹</a>
                <span class="whitespace-nowrap px-2 text-sm font-medium tabular-nums text-text">{{ $semLabel }}</span>
                <a @class([$pfeil, 'pointer-events-none opacity-30' => ! $nextSemesterId])
                   href="{{ $nextSemesterId ? route('learner.grades.index', $mit(['semester_id' => $nextSemesterId])) : '#' }}" aria-label="Nächstes Semester">›</a>
            </div>
            <x-slot:aktionen>
                <a href="{{ route('learner.grades.import.index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">Import</a>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" aria-label="Weitere Aktionen" class="inline-flex size-9 items-center justify-center rounded-lg glass-btn text-text">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM8.5 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM15.5 8.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <a href="{{ route('learner.grades.print') }}" target="_blank" class="flex h-9 items-center rounded-lg px-3 text-sm text-text hover:bg-surface-2">Drucken</a>
                        <a href="{{ route('learner.grades.export') }}" class="flex h-9 items-center rounded-lg px-3 text-sm text-text hover:bg-surface-2">CSV exportieren</a>
                    </x-slot>
                </x-dropdown>
                <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: 'Neue Note' })"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none" aria-hidden="true">+</span> Note
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6" x-data="{ ansicht: @js(request('ansicht') === 'alle' ? 'alle' : 'semester') }">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 sm:px-6 lg:px-8">

            {{-- Statuszeile --}}
            <dl class="flex flex-wrap items-baseline gap-x-3 gap-y-2">
                @foreach($status as [$label, $wert])
                    @unless($loop->first)<span class="text-muted" aria-hidden="true">·</span>@endunless
                    <div class="flex items-baseline gap-2">
                        <dt class="text-xs text-muted">{{ $label }}</dt>
                        <dd><x-note :wert="$wert" :stellen="1" class="text-2xl" /></dd>
                    </div>
                @endforeach
            </dl>

            {{-- Werkzeugzeile: Ansicht links, Kategorien rechts --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="inline-flex rounded-lg bg-surface-2 p-0.5 text-sm" role="radiogroup" aria-label="Ansicht">
                    <button type="button" role="radio" :aria-checked="ansicht === 'semester'" @click="ansicht = 'semester'" class="{{ $segment }}">Semester</button>
                    <button type="button" role="radio" :aria-checked="ansicht === 'alle'" @click="ansicht = 'alle'" class="{{ $segment }}">Zeugnisübersicht</button>
                </div>
                <nav class="flex flex-wrap gap-1.5" x-show="ansicht === 'semester'" aria-label="Kategorie">
                    <a href="{{ route('learner.grades.index', $mit(['kategorie_id' => null])) }}" @if(! $kategorieId) aria-current="page" @endif
                       @class([$chip, 'border-accent/40 bg-accent/10 text-accent-text' => ! $kategorieId, 'border-border text-muted hover:bg-surface-2/60 hover:text-text' => $kategorieId])>Alle</a>
                    @foreach($kategorien as $k)
                        <a href="{{ route('learner.grades.index', $mit(['kategorie_id' => $k->kategorie_id])) }}" @if($kategorieId === $k->kategorie_id) aria-current="page" @endif
                           @class([$chip, 'border-accent/40 bg-accent/10 text-accent-text' => $kategorieId === $k->kategorie_id, 'border-border text-muted hover:bg-surface-2/60 hover:text-text' => $kategorieId !== $k->kategorie_id])>{{ $k->name }}</a>
                    @endforeach
                </nav>
            </div>

            {{-- Zeugnisübersicht --}}
            <x-karte titel="Zeugnisnoten über die Lehrzeit" :polster="false" x-show="ansicht === 'alle'" x-cloak>
                <x-heatmap :daten="$heatmap" />
            </x-karte>

            {{-- Semester: Tabelle je Kategorie --}}
            <div class="flex flex-col gap-6" x-show="ansicht === 'semester'">
                @forelse($gruppen as $g)
                    <section class="flex flex-col gap-2" aria-labelledby="kategorie-{{ $g->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 px-1">
                            <h2 id="kategorie-{{ $g->id }}" class="text-sm font-semibold text-text">{{ $g->name }}</h2>
                            <div class="flex items-center gap-3 text-sm">
                                @if($g->promotion)
                                    <x-status :status="$g->promotion['erfuellt'] ? 'gruen' : 'rot'" :text="$g->promotion['erfuellt'] ? 'Promotion ok' : 'Promotion gefährdet'" />
                                @endif
                                <span class="flex items-baseline gap-1.5"><span class="text-xs text-muted">Schnitt</span><x-note :wert="$g->semester" :stellen="1" /></span>
                            </div>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-border bg-card">
                            <table class="w-full text-sm tabular-nums">
                                <thead>
                                    <tr class="border-b border-border">
                                        <th scope="col" class="h-9 w-full max-w-0 bg-surface-2 px-4 text-left text-2xs font-medium text-muted">Fach / Modul</th>
                                        <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">Prüfungen</th>
                                        <th scope="col" class="hidden h-9 whitespace-nowrap bg-surface-2 px-3 text-right text-2xs font-medium text-muted sm:table-cell">Schnitt</th>
                                        <th scope="col" class="h-9 bg-surface-2 px-3 text-right text-2xs font-medium text-muted">Zeugnis</th>
                                        <th scope="col" class="h-9 w-10 bg-surface-2"><span class="sr-only">Einzelnoten</span></th>
                                    </tr>
                                </thead>
                                @foreach($g->elemente as $el)
                                    @php
                                        $e = $el->element;
                                        $beleg = $e?->typ === \App\Services\Auswertung\Element::MODUL ? ($belegungen[$e->modulId] ?? null) : null;
                                        $offenGewicht = $e?->offenGewicht();
                                        $fortschritt = $e && $e->zielGewicht ? (int) min(100, round($e->gewichtSumme / $e->zielGewicht * 100)) : null;
                                        $zeileId = 'noten-'.$g->id.'-'.$loop->index;
                                        $anzahl = $el->noten->count();
                                    @endphp
                                    <tbody x-data="{ offen: @js($offeneNote > 0 && $el->noten->contains('note_id', $offeneNote)) }" class="border-b border-border last:border-0">
                                        <tr class="h-12 cursor-pointer transition-colors duration-100 hover:bg-surface-2/60" @click="offen = ! offen">
                                            <th scope="row" class="w-full max-w-0 px-4 text-left font-normal">
                                                <button type="button" @click.stop="offen = ! offen" :aria-expanded="offen" aria-controls="{{ $zeileId }}"
                                                        class="flex w-full min-w-0 items-baseline gap-2 rounded-md text-left focus-visible:outline-2 focus-visible:outline-ring">
                                                    <span class="truncate font-medium text-text">{{ $el->label }}</span>
                                                    @if($beleg && $beleg['versuche'] > 1)
                                                        <span class="shrink-0 text-xs text-muted">{{ $beleg['versuche'] }}. Versuch</span>
                                                    @endif
                                                </button>
                                            </th>
                                            <td class="whitespace-nowrap px-3 text-muted">
                                                <span class="inline-flex items-center gap-2" @if($fortschritt !== null) title="{{ $fortschritt >= 100 ? 'abgeschlossen' : \App\Support\Zahl::prozent($offenGewicht).' offen' }}" @endif>
                                                    <span class="sr-only">{{ $anzahl === 1 ? '1 Prüfung' : $anzahl.' Prüfungen' }}</span>
                                                    <span aria-hidden="true">{{ $anzahl }}</span>
                                                    @if($fortschritt !== null)
                                                        <span class="hidden h-1 w-16 overflow-hidden rounded-full bg-surface-2 sm:block" aria-hidden="true">
                                                            <span class="block h-full bg-chart-6" style="width: {{ $fortschritt }}%"></span>
                                                        </span>
                                                        <span class="hidden text-xs md:inline">{{ $fortschritt >= 100 ? 'abgeschlossen' : \App\Support\Zahl::prozent($offenGewicht).' offen' }}</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="hidden px-3 text-right text-muted sm:table-cell" title="Schnitt vor Rundung">{{ \App\Support\NotenSkala::format($e?->schnitt, 2) }}</td>
                                            <td class="px-3 text-right"><x-note :wert="$e?->note" variante="badge" /></td>
                                            <td class="w-10 pr-3 text-right text-muted">
                                                <svg class="ml-auto size-4 transition-transform duration-200" :class="offen && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.27a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd"/></svg>
                                            </td>
                                        </tr>
                                        <tr id="{{ $zeileId }}" x-show="offen" x-cloak>
                                            <td colspan="5" class="border-t border-border p-0">
                                                <div class="divide-y divide-border">
                                                    @foreach($el->noten as $n)
                                                        @include('lernender.noten.partials.note', ['n' => $n, 'ich' => $ich])
                                                    @endforeach
                                                    @if($beleg)
                                                        <div class="flex justify-end px-3 py-1.5">
                                                            <form method="POST" action="{{ route($beleg['offen'] ? 'learner.grades.module.repeat' : 'learner.grades.module.resume', $e->modulId) }}"
                                                                  @if($beleg['offen']) onsubmit="return confirm('Modul wiederholen? Ab der nächsten Note zählt nur der neue Versuch.')" @endif
                                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                                @csrf
                                                                <button :disabled="loading" class="inline-flex h-8 items-center rounded-lg px-2.5 text-sm text-muted hover:bg-surface-2 hover:text-text disabled:opacity-50">
                                                                    {{ $beleg['offen'] ? 'Modul wiederholen' : 'Wiederholung zurücknehmen' }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                    </section>
                @empty
                    <p class="flex items-center gap-3 rounded-xl border border-border bg-card px-5 py-4 text-sm text-muted">
                        Keine Noten in {{ $semLabel }}
                        <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: 'Neue Note' })"
                           class="text-accent-text underline-offset-2 hover:underline">Note erfassen</a>
                    </p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Erfassen/Bearbeiten im Drawer; create/edit bleiben als Seiten für Direktlinks und ohne JS --}}
    <div x-data="npNoteDrawer(@js(['titel' => $drawerFehler['titel'] ?? 'Neue Note', 'server' => (bool) $drawerFehler]))"
         x-on:np-note.window="oeffnen($event.detail.url, $event.detail.titel)">
        <x-drawer name="note" titel="Note">
            <x-slot:kopf><span x-text="titel">{{ $drawerFehler['titel'] ?? 'Neue Note' }}</span></x-slot:kopf>
            @if($drawerFehler)
                <template x-if="server">
                    <div>@include('lernender.noten.partials.formular', $drawerFehler['daten'])</div>
                </template>
            @endif
            <div x-show="! server" x-html="html"></div>
            <div x-show="laedt" x-cloak class="flex flex-col gap-4" aria-hidden="true">
                <div class="mx-auto h-20 w-36 rounded-xl bg-surface-2"></div>
                <div class="h-10 rounded-lg bg-surface-2"></div>
                <div class="h-10 rounded-lg bg-surface-2"></div>
            </div>
        </x-drawer>
    </div>

    <script>
        function npNoteDrawer(start) {
            return {
                titel: start.titel,
                server: start.server,
                html: '',
                laedt: false,
                init() {
                    if (this.server) this.$nextTick(() => this.$dispatch('open-drawer', 'note'));
                },
                async oeffnen(url, titel) {
                    this.titel = titel;
                    this.server = false;
                    this.html = '';
                    this.laedt = true;
                    this.$dispatch('open-drawer', 'note');
                    try {
                        const ziel = new URL(url, window.location.origin);
                        ziel.searchParams.set('drawer', '1');
                        // X-Requested-With: die Session merkt sich den Fragment-Abruf nicht als «vorherige URL»
                        const res = await fetch(ziel, { headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' } });
                        if (!res.ok || res.redirected) throw new Error(res.status);
                        this.html = await res.text();
                        this.$nextTick(() => this.$root.querySelector('#note_wert')?.focus());
                    } catch (e) {
                        window.location.href = url;
                    } finally {
                        this.laedt = false;
                    }
                },
            };
        }

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
            const offen = {{ $offeneNote ?: 'null' }};

            document.querySelectorAll('details.np-note-detail').forEach((d) => {
                const c = d.querySelector('.np-chevron-note');
                d.addEventListener('toggle', () => {
                    c?.classList.toggle('rotate-90', d.open);
                    if (d.open) {
                        fetch(`/grades/${d.dataset.noteId}/seen`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } }).catch(() => {});
                    }
                });
            });

            // Deep-Link (?_open=): die Zeile ist serverseitig aufgeklappt, hier nur die Prüfung öffnen
            const ziel = offen && document.querySelector(`details.np-note-detail[data-note-id="${offen}"]`);
            if (ziel) {
                ziel.open = true;
                setTimeout(() => ziel.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60);
            }
        });
    </script>
</x-app-layout>
