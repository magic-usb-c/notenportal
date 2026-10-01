<x-app-layout>
    <x-slot name="title">{{ __('Zeugnis-Abgleich') }}</x-slot>
    @php
        $zeilen = $ergebnis['zeilen'];
        $anzahl = collect($zeilen)->countBy('status');
        $fehlend = collect($zeilen)->where('status', 'fehlt');
    @endphp

    <x-slot name="header">
        <x-seitenkopf :zurueck="$r('index')" titel="{{ __('Zeugnis-Abgleich') }}"
                      :untertitel="($bereich ? $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname.' · ' : '').$dokument->titel">
            <x-slot:aktionen>
                <a href="{{ $r('show', ['dokument_id' => $dokument->dokument_id, 'anzeigen' => 1]) }}" target="_blank" rel="noopener" class="np-knopf np-knopf-sekundaer">{{ __('Zeugnis öffnen') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="np-seite mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">
            <form method="GET" action="{{ $r('reconcile', ['dokument_id' => $dokument->dokument_id]) }}" class="np-karte p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="semester_id" class="block text-sm font-medium text-text">{{ __('Semester') }}</label>
                    <select id="semester_id" name="semester_id" data-sofort
                            class="np-feld mt-1 w-48">
                        <option value="">–</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}" @selected($semesterId === (int) $s->semester_id)>{{ \App\Services\Auswertung\Konfiguration::ausDb()->semesterName((int) $s->semester_id, (int) $lernender->lernender_id) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-auto sm:ml-auto grid grid-cols-3 gap-2 sm:gap-3">
                    @foreach(['gleich' => [__('Übereinstimmend'), 'gruen'], 'abweichung' => [__('Abweichend'), 'gelb'], 'fehlt' => [__('Fehlt im Portal'), 'neutral']] as $status => [$text, $ton])
                        <x-kachel :label="$text" :wert="$anzahl[$status] ?? 0" :ton="($anzahl[$status] ?? 0) ? $ton : 'neutral'" class="sm:min-w-32" />
                    @endforeach
                </div>
            </form>

            @if(! $ergebnis['text'])
                <div class="np-karte px-5 py-12 text-center text-sm text-muted">{{ __('Kein Text im PDF erkannt') }}</div>
            @elseif($zeilen === [])
                <div class="np-karte px-5 py-12 text-center text-sm text-muted">{{ __('Keine Fächer oder Module erkannt') }}</div>
            @else
                <form method="POST" action="{{ $r('reconcile.apply', ['dokument_id' => $dokument->dokument_id]) }}" class="flex flex-col gap-4"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $semesterId }}">
                    <section class="np-karte overflow-hidden">
                        <div class="overflow-x-auto p-2">
                            <table class="np-tabelle text-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Fach / Modul') }}</th>
                                        <th class="text-right">{{ __('Zeugnis') }}</th>
                                        <th class="text-right">{{ __('Portal') }}</th>
                                        <th class="text-right">{{ __('Differenz') }}</th>
                                        <th>{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($zeilen as $i => $z)
                                        <tr>
                                            <td>
                                                <div class="font-medium">{{ $z['name'] }}</div>
                                                @if(! $z['sicher'] || $z['label'] !== $z['name'])
                                                    <div class="text-xs text-muted">{{ $z['label'] }}</div>
                                                @endif
                                                @if($z['bezug'])
                                                    <input type="hidden" name="zeilen[{{ $i }}][bezug]" value="{{ $z['bezug'] }}">
                                                    <input type="hidden" name="zeilen[{{ $i }}][note]" value="{{ $z['note'] }}">
                                                @endif
                                            </td>
                                            <td class="text-right font-semibold {{ \App\Support\NotenSkala::text($z['note']) }}">{{ \App\Support\NotenSkala::format($z['note'], 1) }}</td>
                                            <td class="text-right {{ \App\Support\NotenSkala::text($z['portal']) }}">{{ \App\Support\NotenSkala::format($z['portal'], 1) }}</td>
                                            <td @class(['px-3 py-2.5 text-right tabular-nums',
                                                'text-muted' => $z['status'] !== 'abweichung',
                                                'text-note-knapp font-semibold' => $z['status'] === 'abweichung'])>
                                                {{ $z['differenz'] !== null ? ($z['differenz'] > 0 ? '+' : '').\App\Support\NotenSkala::format($z['differenz'], 1) : '–' }}
                                            </td>
                                            <td>
                                                @if($z['status'] === 'fehlt' && $darfUebernehmen && $semesterId)
                                                    <label class="inline-flex items-center gap-2 min-h-9 text-sm cursor-pointer">
                                                        <input type="checkbox" name="zeilen[{{ $i }}][uebernehmen]" value="1" checked class="np-haken">
                                                        {{ __('übernehmen') }}
                                                    </label>
                                                @else
                                                    <x-status :status="match ($z['status']) { 'gleich' => 'gruen', 'abweichung' => 'gelb', default => 'neutral' }"
                                                              :text="match ($z['status']) { 'gleich' => __('stimmt'), 'abweichung' => __('abweichend'), 'unbekannt' => __('nicht zugeordnet'), default => __('fehlt') }" />
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                    @if($fehlend->isNotEmpty() && $darfUebernehmen && $semesterId)
                        <div class="flex justify-end">
                            <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Zeugnisnoten übernehmen') }}</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
