<x-app-layout>
    <x-slot name="title">Noten-Rechner</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Noten-Rechner</h2>
            <a href="{{ route('lernender.noten.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-4"
             x-data="notenRechner({
                allNotes: @js($allNotes),
                currentNotes: @js($currentNotes),
                hasCurrentSemester: {{ $currentSemester ? 'true' : 'false' }}
             })">

            <div class="glass rounded-2xl p-5 space-y-4">
                <div>
                    <h3 class="font-semibold text-text">Welche Note brauche ich?</h3>
                    <p class="text-sm text-muted mt-1">
                        Berechnet, welche Note du in der nächsten Prüfung mindestens brauchst,
                        um deinen Ziel-Durchschnitt zu erreichen.
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs uppercase tracking-wide text-muted">Ziel-Durchschnitt</label>
                        <input type="number"
                               step="0.1"
                               min="1.0"
                               max="6.0"
                               x-model.number="ziel"
                               class="mt-1 w-full text-2xl font-bold text-center rounded-xl border border-border bg-input text-text py-2.5 focus:ring-2 focus:ring-accent/50 focus:border-accent">
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wide text-muted">Basis</label>
                        <select x-model="basis"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text py-2.5 focus:ring-2 focus:ring-accent/50 focus:border-accent">
                            <option value="current" :disabled="!hasCurrentSemester">
                                Aktuelles Semester ({{ collect($currentNotes)->count() }} Noten)
                            </option>
                            <option value="all">
                                Alle Semester ({{ collect($allNotes)->count() }} Noten)
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs uppercase tracking-wide text-muted">Gewichtung der nächsten Note (%)</label>
                        <input type="number"
                               step="5"
                               min="0"
                               max="200"
                               x-model.number="naechsteGewicht"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text py-2.5 focus:ring-2 focus:ring-accent/50 focus:border-accent">
                    </div>

                    <div class="flex flex-col justify-end">
                        <div class="text-xs uppercase tracking-wide text-muted">Aktueller Ø</div>
                        <div class="mt-1 text-2xl font-bold tabular-nums"
                             :class="aktuellColor">
                            <span x-text="aktuellAvg !== null ? aktuellAvg.toFixed(2) : '–'"></span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Ergebnis-Karte --}}
            <div class="glass rounded-2xl p-6 text-center">
                <div class="text-xs uppercase tracking-wide text-muted">Benötigte Note</div>

                <template x-if="status === 'ok'">
                    <div class="mt-2">
                        <div class="text-5xl font-bold tabular-nums"
                             :class="benoetigtColor"
                             x-text="benoetigt.toFixed(2)"></div>
                        <p class="mt-3 text-sm text-muted">
                            Mit dieser Note (Gewichtung <span x-text="naechsteGewicht"></span>%)
                            erreichst du genau den Ziel-Ø von
                            <span class="font-semibold text-text" x-text="ziel.toFixed(1)"></span>.
                        </p>
                    </div>
                </template>

                <template x-if="status === 'unerreichbar'">
                    <div class="mt-2">
                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">Nicht erreichbar</div>
                        <p class="mt-3 text-sm text-muted">
                            Selbst mit einer 6.0 in der nächsten Prüfung kommst du nicht auf
                            <span class="font-semibold text-text" x-text="ziel.toFixed(1)"></span>.
                            Du müsstest mindestens
                            <span class="font-semibold text-text" x-text="benoetigt.toFixed(2)"></span>
                            schreiben.
                        </p>
                    </div>
                </template>

                <template x-if="status === 'erreicht'">
                    <div class="mt-2">
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">Ziel bereits übertroffen</div>
                        <p class="mt-3 text-sm text-muted">
                            Dein aktueller Ø liegt bereits über
                            <span class="font-semibold text-text" x-text="ziel.toFixed(1)"></span>.
                            Eine
                            <span class="font-semibold text-text" x-text="Math.max(1, benoetigt).toFixed(2)"></span>
                            in der nächsten Prüfung würde den Ø noch genau auf das Ziel drücken.
                        </p>
                    </div>
                </template>

                <template x-if="status === 'leer'">
                    <div class="mt-2">
                        <div class="text-2xl font-semibold text-muted">Keine Daten</div>
                        <p class="mt-3 text-sm text-muted">
                            Noch keine Noten erfasst, daher kann kein Bedarf berechnet werden.
                        </p>
                    </div>
                </template>
            </div>

            <div class="text-xs text-muted px-1">
                Berechnung: gewichteter Durchschnitt. Die benötigte Note ergibt sich aus
                <code class="bg-bg px-1 rounded">(Ziel · (ΣGewicht + g) − ΣNote·Gewicht) / g</code>,
                wobei g die Gewichtung der nächsten Note ist.
            </div>
        </div>
    </div>

    <script>
        function notenRechner({ allNotes, currentNotes, hasCurrentSemester }) {
            return {
                allNotes,
                currentNotes,
                hasCurrentSemester,
                ziel: 4.5,
                basis: hasCurrentSemester ? 'current' : 'all',
                naechsteGewicht: 100,

                get basisNotes() {
                    return this.basis === 'current' ? this.currentNotes : this.allNotes;
                },

                get aktuellAvg() {
                    const n = this.basisNotes;
                    if (!n.length) return null;
                    let wSum = 0, sum = 0;
                    n.forEach(x => { wSum += x.gew; sum += x.wert * x.gew; });
                    return wSum > 0 ? (sum / wSum) : null;
                },

                get aktuellColor() {
                    return this.colorFor(this.aktuellAvg);
                },

                get benoetigt() {
                    const n = this.basisNotes;
                    const g = Number(this.naechsteGewicht) || 0;
                    if (g <= 0) return NaN;
                    let wSum = 0, sum = 0;
                    n.forEach(x => { wSum += x.gew; sum += x.wert * x.gew; });
                    // (Ziel * (wSum + g) - sum) / g
                    return (this.ziel * (wSum + g) - sum) / g;
                },

                get benoetigtColor() {
                    return this.colorFor(this.benoetigt);
                },

                get status() {
                    if (!this.basisNotes.length) return 'leer';
                    const b = this.benoetigt;
                    if (!Number.isFinite(b)) return 'leer';
                    if (b > 6.0) return 'unerreichbar';
                    if (b < 1.0) return 'erreicht';
                    return 'ok';
                },

                colorFor(v) {
                    if (v === null || !Number.isFinite(v)) return 'text-muted';
                    if (v >= 5.0) return 'text-green-600 dark:text-green-400';
                    if (v >= 4.0) return 'text-emerald-600 dark:text-emerald-400';
                    if (v >= 3.5) return 'text-yellow-600 dark:text-yellow-400';
                    return 'text-red-600 dark:text-red-400';
                },
            };
        }
    </script>
</x-app-layout>
