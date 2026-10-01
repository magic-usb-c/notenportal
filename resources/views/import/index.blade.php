<x-app-layout>
    <x-slot name="title">{{ __('Notenimport') }}</x-slot>
    @php
        $feld = 'np-feld px-2';
    @endphp

    <x-slot name="header">
        <x-seitenkopf :zurueck="$zurueck" titel="{{ __('Notenimport') }}" :untertitel="$bereich ? $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname : null" :schmal="! $vorschau">
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8 flex flex-col gap-5">
            @if(! $vorschau)
                <form method="POST" action="{{ $r('read') }}" enctype="multipart/form-data" class="np-karte np-spalte p-6 flex flex-col gap-4"
                      x-data="{ loading: false, name: '', ueber: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <label for="datei" class="flex flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-12 text-center cursor-pointer transition-colors"
                           :class="ueber ? 'border-accent bg-accent/5' : 'border-border hover:border-accent/50'"
                           @dragover.prevent="ueber = true" @dragleave.prevent="ueber = false"
                           @drop.prevent="ueber = false; $refs.datei.files = $event.dataTransfer.files; name = $event.dataTransfer.files[0]?.name ?? ''">
                        <svg class="w-8 h-8 text-accent-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18M9 4v16M5 4h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                        <span class="text-sm font-medium text-text" x-text="name || @js(__('Notenliste wählen oder hierher ziehen'))"></span>
                        <span class="text-xs text-muted">{{ __('Excel, CSV oder PDF mit Datum, Fach/Modul und Note') }}</span>
                        <input id="datei" x-ref="datei" name="datei" type="file" required class="sr-only" accept=".xlsx,.xls,.ods,.csv,.pdf"
                               @change="name = $event.target.files[0]?.name ?? ''">
                    </label>
                    @error('datei')<p class="-mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ $r('template') }}" class="np-knopf np-knopf-schlicht">{{ __('Vorlage (CSV)') }}</a>
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Datei lesen') }}</button>
                    </div>
                </form>
            @else
                @php
                    $zeilen = $vorschau['zeilen'];
                    $anzahl = collect($zeilen)->countBy('status');
                    $namen = ['datum' => __('Datum'), 'bezug' => __('Fach/Modul'), 'titel' => __('Titel'), 'note' => __('Note'), 'gewicht' => __('Gewicht')];
                    $spalte = fn (int $i) => $i < 26 ? chr(65 + $i) : (string) ($i + 1);
                @endphp
                <script>
                    function npImport(zeilen, pruefenUrl) {
                        return {
                            zeilen,
                            loading: false,
                            pruefeLaedt: false,
                            get gewaehlt() { return this.zeilen.filter((z) => z.uebernehmen).length; },
                            get json() { return JSON.stringify(this.zeilen.filter((z) => z.uebernehmen)); },
                            alle(an) { this.zeilen.forEach((z) => { z.uebernehmen = an && z.status !== 'fehler'; }); },
                            farbe(s) {
                                return {
                                    ok: 'bg-note-gut/14 text-note-gut',
                                    warnung: 'bg-note-knapp/14 text-note-knapp',
                                    pruefen: 'bg-note-knapp/14 text-note-knapp',
                                    doppelt: 'bg-bg text-muted border border-border',
                                    fehler: 'bg-note-ungenuegend/14 text-note-ungenuegend',
                                }[s] ?? '';
                            },
                            // «Erneut prüfen»: Korrekturen (Datum, Fach/Modul, Note, Gewicht) serverseitig gegen dieselben
                            // Regeln wie die erste Vorschau prüfen – ohne zu speichern.
                            async erneutPruefen() {
                                this.pruefeLaedt = true;
                                try {
                                    const antwort = await fetch(pruefenUrl, {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/json',
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                            Accept: 'application/json',
                                        },
                                        body: JSON.stringify({ zeilen: JSON.stringify(this.zeilen) }),
                                    });
                                    if (!antwort.ok) {
                                        throw new Error(String(antwort.status));
                                    }
                                    this.zeilen = (await antwort.json()).zeilen;
                                } catch (e) {
                                    window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: @js(__('Erneute Prüfung fehlgeschlagen.')), art: 'fehler' } }));
                                } finally {
                                    this.pruefeLaedt = false;
                                }
                            },
                        };
                    }
                </script>

                <form method="POST" action="{{ $r('apply') }}" class="flex flex-col gap-4"
                      x-data="npImport({{ \Illuminate\Support\Js::from($zeilen) }}, {{ \Illuminate\Support\Js::from($r('validate')) }})" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <input type="hidden" name="zeilen" :value="json">
                    <input type="hidden" name="token" value="{{ $vorschau['token'] ?? '' }}">

                    <section class="np-karte p-5 flex flex-wrap items-center gap-x-8 gap-y-3">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-text truncate">{{ $vorschau['datei'] }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @if($vorschau['format'] ?? null)
                                    <span class="np-marke bg-accent/12 text-accent-text">{{ $vorschau['format'] }}</span>
                                @endif
                                @foreach($vorschau['erkannt'] as $art => $index)
                                    <span class="np-marke text-muted">{{ $namen[$art] ?? $art }} ← {{ $spalte($index) }}</span>
                                @endforeach
                            </div>
                        </div>
                        <dl class="ml-auto flex flex-wrap gap-5 text-center">
                            @foreach(['ok' => __('bereit'), 'warnung' => __('Warnung'), 'pruefen' => __('prüfen'), 'doppelt' => __('bereits erfasst'), 'fehler' => __('Fehler')] as $status => $text)
                                <div>
                                    <dt class="text-xs text-muted">{{ $text }}</dt>
                                    <dd @class(['text-xl font-bold tabular-nums',
                                        'text-note-gut' => $status === 'ok',
                                        'text-note-knapp' => in_array($status, ['warnung', 'pruefen'], true),
                                        'text-muted' => $status === 'doppelt',
                                        'text-note-ungenuegend' => $status === 'fehler'])>{{ $anzahl[$status] ?? 0 }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>

                    <section class="np-karte overflow-hidden">
                        <div class="overflow-x-auto p-2">
                            <table class="np-tabelle text-sm">
                                <thead>
                                    <tr>
                                        <th class="w-12">
                                            <label class="inline-flex items-center justify-center min-w-9 min-h-9">
                                                <input type="checkbox" aria-label="{{ __('Alle auswählen') }}" :checked="gewaehlt === zeilen.length" @change="alle($event.target.checked)"
                                                       class="np-haken">
                                            </label>
                                        </th>
                                        <th>{{ __('Zeile') }}</th>
                                        <th>{{ __('Datum') }}</th>
                                        <th>{{ __('Fach/Modul') }}</th>
                                        <th>{{ __('Titel') }}</th>
                                        <th>{{ __('Note') }}</th>
                                        <th>{{ __('Gewicht %') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="z in zeilen" :key="z.nr">
                                        <tr :class="z.uebernehmen ? '' : 'opacity-60'">
                                            <td>
                                                <label class="inline-flex items-center justify-center min-w-9 min-h-9">
                                                    <input type="checkbox" x-model="z.uebernehmen" :disabled="z.status === 'fehler'"
                                                           :aria-label="@js(__('Zeile :nr übernehmen')).replace(':nr', z.nr)" class="np-haken">
                                                </label>
                                            </td>
                                            <td class="text-xs text-muted" x-text="z.nr"></td>
                                            <td class="min-w-36"><input type="date" x-model="z.datum" :aria-label="@js(__('Datum Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }} tabular-nums" :class="! z.datum && 'border-note-ungenuegend!'"></td>
                                            <td class="min-w-56">
                                                <select x-model="z.bezug" :aria-label="@js(__('Fach oder Modul Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }}" :class="! z.bezug && 'border-note-ungenuegend!'">
                                                    <option value="">–</option>
                                                    @foreach($optionen as $gruppe => $liste)
                                                        <optgroup label="{{ $gruppe }}">
                                                            @foreach($liste as $o)
                                                                <option value="{{ $o['wert'] }}">{{ $o['label'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                <div class="mt-0.5 text-3xs text-muted truncate max-w-56" x-show="z.bezug_roh" x-text="z.bezug_roh"></div>
                                            </td>
                                            <td class="min-w-40"><input type="text" x-model="z.titel" maxlength="150" :aria-label="@js(__('Titel Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }}"></td>
                                            <td class="w-24"><input type="number" x-model.number="z.note" min="1" max="6" step="0.05" :aria-label="@js(__('Note Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }} tabular-nums" :class="! z.note && 'border-note-ungenuegend!'"></td>
                                            <td class="w-24"><input type="number" x-model.number="z.gewicht" min="0" max="100" step="1" :aria-label="@js(__('Gewicht Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }} tabular-nums"></td>
                                            <td class="whitespace-nowrap">
                                                <span class="np-marke" :class="farbe(z.status)" x-text="z.meldung || @js(__('bereit'))"></span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" form="import-verwerfen" :disabled="loading" @click="loading = true" class="np-knopf np-knopf-sekundaer">{{ __('Verwerfen') }}</button>
                            <button type="button" :disabled="loading || pruefeLaedt" @click="erneutPruefen()"
                                    class="np-knopf np-knopf-sekundaer"
                                    x-text="pruefeLaedt ? @js(__('Prüfe …')) : @js(__('Erneut prüfen'))"></button>
                        </div>
                        <button type="submit" :disabled="loading || pruefeLaedt || gewaehlt === 0"
                                class="np-knopf np-knopf-primaer"
                                x-text="gewaehlt === 1 ? @js(__('1 Note importieren')) : @js(__(':anzahl Noten importieren')).replace(':anzahl', gewaehlt)"></button>
                    </div>
                </form>
                <form id="import-verwerfen" method="POST" action="{{ $r('discard') }}" class="hidden">@csrf</form>
            @endif
        </div>
    </div>
</x-app-layout>
