<x-app-layout>
    <x-slot name="title">Notenimport</x-slot>
    @php
        $feld = 'h-9 w-full rounded-lg border border-border bg-input text-text px-2 text-sm focus:ring-2 focus:ring-ring focus:border-ring';
    @endphp

    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                @if($bereich)
                    <div class="text-sm text-muted truncate">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</div>
                @endif
                <h2 class="font-semibold text-xl text-text">Notenimport</h2>
            </div>
            <a href="{{ $zurueck }}" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Zurück</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">
            @if(! $vorschau)
                <form method="POST" action="{{ $r('lesen') }}" enctype="multipart/form-data" class="glass rounded-2xl p-6 flex flex-col gap-4 max-w-3xl w-full mx-auto"
                      x-data="{ loading: false, name: '', ueber: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <label for="datei" class="flex flex-col items-center justify-center gap-1.5 rounded-2xl border-2 border-dashed px-4 py-12 text-center cursor-pointer transition-colors"
                           :class="ueber ? 'border-accent bg-accent/5' : 'border-border hover:border-accent/50'"
                           @dragover.prevent="ueber = true" @dragleave.prevent="ueber = false"
                           @drop.prevent="ueber = false; $refs.datei.files = $event.dataTransfer.files; name = $event.dataTransfer.files[0]?.name ?? ''">
                        <svg class="w-8 h-8 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M9 4v16M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                        <span class="text-sm font-medium text-text" x-text="name || 'Notenliste wählen oder hierher ziehen'"></span>
                        <span class="text-xs text-muted">Excel, CSV oder PDF mit Datum, Fach/Modul und Note</span>
                        <input id="datei" x-ref="datei" name="datei" type="file" required class="sr-only" accept=".xlsx,.xls,.ods,.csv,.pdf"
                               @change="name = $event.target.files[0]?.name ?? ''">
                    </label>
                    @error('datei')<p class="-mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ $r('vorlage') }}" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">Vorlage (CSV)</a>
                        <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary disabled:opacity-60">Datei lesen</button>
                    </div>
                </form>
            @else
                @php
                    $zeilen = $vorschau['zeilen'];
                    $anzahl = collect($zeilen)->countBy('status');
                    $namen = ['datum' => 'Datum', 'bezug' => 'Fach/Modul', 'titel' => 'Titel', 'note' => 'Note', 'gewicht' => 'Gewicht'];
                    $spalte = fn (int $i) => $i < 26 ? chr(65 + $i) : (string) ($i + 1);
                @endphp
                <script>
                    function npImport(zeilen) {
                        return {
                            zeilen,
                            loading: false,
                            get gewaehlt() { return this.zeilen.filter((z) => z.uebernehmen).length; },
                            get json() { return JSON.stringify(this.zeilen.filter((z) => z.uebernehmen)); },
                            alle(an) { this.zeilen.forEach((z) => { z.uebernehmen = an; }); },
                            farbe(s) {
                                return {
                                    ok: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                    pruefen: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    doppelt: 'bg-bg text-muted border border-border',
                                    fehler: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                }[s] ?? '';
                            },
                        };
                    }
                </script>

                <form method="POST" action="{{ $r('uebernehmen') }}" class="flex flex-col gap-4"
                      x-data="npImport({{ \Illuminate\Support\Js::from($zeilen) }})" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <input type="hidden" name="zeilen" :value="json">

                    <section class="glass rounded-2xl p-5 flex flex-wrap items-center gap-x-8 gap-y-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-text truncate">{{ $vorschau['datei'] }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @foreach($vorschau['erkannt'] as $art => $index)
                                    <span class="px-2 py-0.5 rounded-full text-[11px] bg-bg/60 border border-border text-muted">{{ $namen[$art] ?? $art }} ← {{ $spalte($index) }}</span>
                                @endforeach
                            </div>
                        </div>
                        <dl class="ml-auto flex flex-wrap gap-5 text-center">
                            @foreach(['ok' => 'bereit', 'pruefen' => 'prüfen', 'doppelt' => 'bereits erfasst', 'fehler' => 'Fehler'] as $status => $text)
                                <div>
                                    <dt class="text-[11px] uppercase tracking-widest text-muted">{{ $text }}</dt>
                                    <dd @class(['text-xl font-bold tabular-nums',
                                        'text-green-700 dark:text-green-400' => $status === 'ok',
                                        'text-yellow-700 dark:text-yellow-400' => $status === 'pruefen',
                                        'text-muted' => $status === 'doppelt',
                                        'text-red-600 dark:text-red-400' => $status === 'fehler'])>{{ $anzahl[$status] ?? 0 }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>

                    <section class="glass rounded-2xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-text">
                                <thead class="text-xs text-muted">
                                    <tr class="border-b border-border">
                                        <th class="px-3 py-2 w-12">
                                            <label class="inline-flex items-center justify-center min-w-9 min-h-9">
                                                <input type="checkbox" aria-label="Alle auswählen" :checked="gewaehlt === zeilen.length" @change="alle($event.target.checked)"
                                                       class="w-5 h-5 rounded border-border text-accent focus:ring-ring">
                                            </label>
                                        </th>
                                        <th class="text-left px-2 py-2 font-medium">Zeile</th>
                                        <th class="text-left px-2 py-2 font-medium">Datum</th>
                                        <th class="text-left px-2 py-2 font-medium">Fach/Modul</th>
                                        <th class="text-left px-2 py-2 font-medium">Titel</th>
                                        <th class="text-left px-2 py-2 font-medium">Note</th>
                                        <th class="text-left px-2 py-2 font-medium">Gewicht %</th>
                                        <th class="text-left px-3 py-2 font-medium">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <template x-for="z in zeilen" :key="z.nr">
                                        <tr :class="z.uebernehmen ? '' : 'opacity-60'">
                                            <td class="px-3 py-1.5">
                                                <label class="inline-flex items-center justify-center min-w-9 min-h-9">
                                                    <input type="checkbox" x-model="z.uebernehmen" :aria-label="`Zeile ${z.nr} übernehmen`" class="w-5 h-5 rounded border-border text-accent focus:ring-ring">
                                                </label>
                                            </td>
                                            <td class="px-2 py-1.5 text-xs text-muted tabular-nums" x-text="z.nr"></td>
                                            <td class="px-2 py-1.5 min-w-36"><input type="date" x-model="z.datum" aria-label="Datum" class="{{ $feld }} tabular-nums" :class="! z.datum && 'border-red-500!'"></td>
                                            <td class="px-2 py-1.5 min-w-56">
                                                <select x-model="z.bezug" aria-label="Fach oder Modul" class="{{ $feld }}" :class="! z.bezug && 'border-red-500!'">
                                                    <option value="">–</option>
                                                    @foreach($optionen as $gruppe => $liste)
                                                        <optgroup label="{{ $gruppe }}">
                                                            @foreach($liste as $o)
                                                                <option value="{{ $o['wert'] }}">{{ $o['label'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                <div class="mt-0.5 text-[11px] text-muted truncate max-w-56" x-show="z.bezug_roh" x-text="z.bezug_roh"></div>
                                            </td>
                                            <td class="px-2 py-1.5 min-w-40"><input type="text" x-model="z.titel" maxlength="150" aria-label="Titel" class="{{ $feld }}"></td>
                                            <td class="px-2 py-1.5 w-24"><input type="number" x-model.number="z.note" min="1" max="6" step="0.05" aria-label="Note" class="{{ $feld }} tabular-nums" :class="! z.note && 'border-red-500!'"></td>
                                            <td class="px-2 py-1.5 w-24"><input type="number" x-model.number="z.gewicht" min="0" max="100" step="1" aria-label="Gewicht" class="{{ $feld }} tabular-nums"></td>
                                            <td class="px-3 py-1.5 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold" :class="farbe(z.status)" x-text="z.meldung || 'bereit'"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <button type="submit" form="import-verwerfen" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Verwerfen</button>
                        <button type="submit" :disabled="loading || gewaehlt === 0"
                                class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary disabled:opacity-60"
                                x-text="gewaehlt === 1 ? '1 Note importieren' : `${gewaehlt} Noten importieren`"></button>
                    </div>
                </form>
                <form id="import-verwerfen" method="POST" action="{{ $r('verwerfen') }}" class="hidden">@csrf</form>
            @endif
        </div>
    </div>
</x-app-layout>
