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
                <form method="POST" action="{{ $r('read') }}" enctype="multipart/form-data" class="np-karte np-spalte flex flex-col gap-5 p-6" :aria-busy="loading"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <x-ablagezone symbol="table-cells" polster="py-14" required accept=".xlsx,.xls,.ods,.csv,.pdf"
                                  :titel="__('Notenliste wählen oder hierher ziehen')"
                                  :hinweis="__('Excel, CSV oder PDF mit Datum, Fach/Modul und Note')" />
                    <div class="flex items-center justify-between gap-3">
                        <a href="{{ $r('template') }}" class="np-knopf np-knopf-schlicht">{{ __('Vorlage (CSV)') }}</a>
                        <div class="flex items-center gap-3">
                            <span role="status" class="inline-flex items-center gap-2 text-sm text-muted" x-show="loading" x-cloak>
                                <span class="size-4 animate-spin rounded-full border-2 border-muted border-t-transparent" aria-hidden="true"></span>
                                {{ __('Datei wird gelesen …') }}
                            </span>
                            <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Datei lesen') }}</button>
                        </div>
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
                    function npImport(zeilen, pruefenUrl, bezeichnungen) {
                        return {
                            zeilen,
                            bezeichnungen,
                            loading: false,
                            pruefeLaedt: false,
                            get gewaehlt() { return this.zeilen.filter((z) => z.uebernehmen).length; },
                            get waehlbar() { return this.zeilen.filter((z) => z.status !== 'fehler').length; },
                            anzahl(status) { return this.zeilen.filter((z) => z.status === status).length; },
                            get json() { return JSON.stringify(this.zeilen.filter((z) => z.uebernehmen)); },
                            alle(an) { this.zeilen.forEach((z) => { z.uebernehmen = an && z.status !== 'fehler'; }); },
                            farbe(s) {
                                return {
                                    ok: 'bg-note-gut/14 text-note-gut',
                                    warnung: 'bg-note-knapp/14 text-note-knapp',
                                    pruefen: 'bg-note-knapp/14 text-note-knapp',
                                    doppelt: 'bg-fill-2 text-muted',
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

                <form method="POST" action="{{ $r('apply') }}" class="flex flex-col gap-4" :aria-busy="loading || pruefeLaedt"
                      x-data="npImport({{ Js::from($zeilen) }}, {{ Js::from($r('validate')) }}, {{ Js::from(collect($optionen)->flatten(1)->pluck('label', 'wert')) }})" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <input type="hidden" name="zeilen" :value="json">
                    <input type="hidden" name="token" value="{{ $vorschau['token'] ?? '' }}">

                    <section class="np-karte p-5 flex flex-wrap items-center gap-x-8 gap-y-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-text" title="{{ $vorschau['datei'] }}">{{ $vorschau['datei'] }}</div>
                            <div class="mt-1.5 flex flex-wrap gap-1.5">
                                @if($vorschau['format'] ?? null)
                                    <span class="np-marke bg-fill text-muted">{{ $vorschau['format'] }}</span>
                                @endif
                                @foreach($vorschau['erkannt'] as $art => $index)
                                    <span class="np-marke text-muted">{{ $namen[$art] ?? $art }} ← {{ $spalte($index) }}</span>
                                @endforeach
                            </div>
                        </div>
                        <dl class="ml-auto flex flex-wrap gap-x-8 gap-y-3">
                            @foreach(['ok' => [__('bereit'), 'bg-note-gut'], 'warnung' => [__('Warnung'), 'bg-note-knapp'], 'pruefen' => [__('prüfen'), 'bg-note-knapp'], 'doppelt' => [__('bereits erfasst'), 'bg-faint'], 'fehler' => [__('Fehler'), 'bg-note-ungenuegend']] as $status => [$text, $punkt])
                                <div>
                                    <dt class="flex items-center gap-1.5 text-xs text-muted"><span class="size-2 rounded-full {{ $punkt }}" aria-hidden="true"></span>{{ $text }}</dt>
                                    <dd class="mt-0.5 text-xl font-semibold tabular-nums" :class="anzahl(@js($status)) === 0 && 'text-muted'"
                                        x-text="anzahl(@js($status))">{{ $anzahl[$status] ?? 0 }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </section>

                    <section class="np-karte overflow-hidden">
                        <div class="overflow-x-auto p-2">
                            <table class="np-tabelle min-w-[80rem] table-fixed text-sm">
                                <colgroup>
                                    <col class="w-16">
                                    <col class="w-16">
                                    <col class="w-44">
                                    <col class="w-80">
                                    <col>
                                    <col class="w-24">
                                    <col class="w-24">
                                    <col class="w-72">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col">
                                            <label class="inline-flex items-center justify-center min-w-9 min-h-9">
                                                <input type="checkbox" aria-label="{{ __('Alle auswählen') }}" :checked="waehlbar !== 0 && gewaehlt === waehlbar" x-effect="$el.indeterminate = gewaehlt !== 0 && gewaehlt !== waehlbar"
                                                       :disabled="waehlbar === 0" @change="alle($event.target.checked)"
                                                       class="np-haken">
                                            </label>
                                        </th>
                                        <th scope="col" class="text-right">{{ __('Zeile') }}</th>
                                        <th scope="col">{{ __('Datum') }}</th>
                                        <th scope="col">{{ __('Fach/Modul') }}</th>
                                        <th scope="col">{{ __('Titel') }}</th>
                                        <th scope="col" class="text-right">{{ __('Note') }}</th>
                                        <th scope="col" class="text-right">{{ __('Gewicht %') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
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
                                            <td class="text-right text-xs text-muted" x-text="z.nr"></td>
                                            <td><input type="date" x-model="z.datum" :aria-label="@js(__('Datum Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }} tabular-nums" :aria-invalid="! z.datum ? 'true' : null"></td>
                                            <td>
                                                <select x-model="z.bezug" :title="bezeichnungen[z.bezug] ?? null" :aria-label="@js(__('Fach oder Modul Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }}" :aria-invalid="! z.bezug ? 'true' : null">
                                                    <option value="">–</option>
                                                    @foreach($optionen as $gruppe => $liste)
                                                        <optgroup label="{{ $gruppe }}">
                                                            @foreach($liste as $o)
                                                                <option value="{{ $o['wert'] }}">{{ $o['label'] }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endforeach
                                                </select>
                                                <div class="mt-1 truncate text-2xs text-muted" x-show="z.bezug_roh && z.bezug_roh !== bezeichnungen[z.bezug]" :title="z.bezug_roh" x-text="z.bezug_roh"></div>
                                            </td>
                                            <td><input type="text" x-model="z.titel" maxlength="150" :title="z.titel" :aria-label="@js(__('Titel Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }}"></td>
                                            <td><input type="number" x-model.number="z.note" min="1" max="6" step="0.05" :aria-label="@js(__('Note Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }} text-right tabular-nums" :aria-invalid="! z.note ? 'true' : null"></td>
                                            <td><input type="number" x-model.number="z.gewicht" min="0" max="100" step="1" :aria-label="@js(__('Gewicht Zeile :nr')).replace(':nr', z.nr)" class="{{ $feld }} text-right tabular-nums"></td>
                                            <td>
                                                <span class="np-marke max-w-full" :class="farbe(z.status)" :title="z.meldung || null">
                                                    <span class="truncate" x-text="z.meldung || @js(__('bereit'))"></span>
                                                </span>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" form="import-verwerfen" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Verwerfen') }}</button>
                            <button type="button" :disabled="loading || pruefeLaedt" @click="erneutPruefen()"
                                    class="np-knopf np-knopf-sekundaer">
                                <span class="size-4 animate-spin rounded-full border-2 border-muted border-t-transparent" x-show="pruefeLaedt" x-cloak aria-hidden="true"></span>
                                <span x-text="pruefeLaedt ? @js(__('Prüfe …')) : @js(__('Erneut prüfen'))"></span>
                            </button>
                        </div>
                        <div class="flex items-center gap-3">
                            <span role="status" class="inline-flex items-center gap-2 text-sm text-muted" x-show="loading" x-cloak>
                                <span class="size-4 animate-spin rounded-full border-2 border-muted border-t-transparent" aria-hidden="true"></span>
                                {{ __('Noten werden importiert …') }}
                            </span>
                            <button type="submit" :disabled="loading || pruefeLaedt || gewaehlt === 0"
                                    class="np-knopf np-knopf-primaer"
                                    x-text="gewaehlt === 1 ? @js(__('1 Note importieren')) : @js(__(':anzahl Noten importieren')).replace(':anzahl', gewaehlt)"></button>
                        </div>
                    </div>
                </form>
                <form id="import-verwerfen" method="POST" action="{{ $r('discard') }}" class="hidden">@csrf</form>
            @endif
        </div>
    </div>
</x-app-layout>
