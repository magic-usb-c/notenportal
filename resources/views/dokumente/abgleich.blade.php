<x-app-layout>
    <x-slot name="title">{{ __('Zeugnis-Abgleich') }}</x-slot>
    @php
        $zeilen = $ergebnis['zeilen'];
        $anzahl = collect($zeilen)->countBy('status');
        $fehlend = collect($zeilen)->where('status', 'fehlt');
    @endphp

    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Zeugnis-Abgleich') }}"
                      :untertitel="($bereich ? $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname.' · ' : '').$dokument->titel">
            <x-slot:aktionen>
                <a href="{{ $r('show', ['dokument_id' => $dokument->dokument_id, 'anzeigen' => 1]) }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Zeugnis öffnen') }}</a>
                <a href="{{ $r('index') }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Zurück') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">
            <form method="GET" action="{{ $r('reconcile', ['dokument_id' => $dokument->dokument_id]) }}" class="rounded-xl border border-border bg-card p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="semester_id" class="block text-sm font-medium text-text">{{ __('Semester') }}</label>
                    <select id="semester_id" name="semester_id" onchange="this.form.submit()"
                            class="mt-1 w-48 rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        <option value="">–</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}" @selected($semesterId === (int) $s->semester_id)>{{ $s->bezeichnung }}</option>
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
                <div class="rounded-xl border border-border bg-card px-5 py-12 text-center text-sm text-muted">{{ __('Kein Text im PDF erkannt') }}</div>
            @elseif($zeilen === [])
                <div class="rounded-xl border border-border bg-card px-5 py-12 text-center text-sm text-muted">{{ __('Keine Fächer oder Module erkannt') }}</div>
            @else
                <form method="POST" action="{{ $r('reconcile.apply', ['dokument_id' => $dokument->dokument_id]) }}" class="flex flex-col gap-4"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $semesterId }}">
                    <section class="rounded-xl border border-border bg-card overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-text">
                                <thead class="text-xs text-muted">
                                    <tr class="border-b border-border">
                                        <th class="text-left px-5 py-2 font-medium">{{ __('Fach / Modul') }}</th>
                                        <th class="text-right px-3 py-2 font-medium">{{ __('Zeugnis') }}</th>
                                        <th class="text-right px-3 py-2 font-medium">{{ __('Portal') }}</th>
                                        <th class="text-right px-3 py-2 font-medium">{{ __('Differenz') }}</th>
                                        <th class="text-left px-5 py-2 font-medium">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($zeilen as $i => $z)
                                        <tr>
                                            <td class="px-5 py-2.5">
                                                <div class="font-medium">{{ $z['name'] }}</div>
                                                @if(! $z['sicher'] || $z['label'] !== $z['name'])
                                                    <div class="text-xs text-muted">{{ $z['label'] }}</div>
                                                @endif
                                                @if($z['bezug'])
                                                    <input type="hidden" name="zeilen[{{ $i }}][bezug]" value="{{ $z['bezug'] }}">
                                                    <input type="hidden" name="zeilen[{{ $i }}][note]" value="{{ $z['note'] }}">
                                                @endif
                                            </td>
                                            <td class="px-3 py-2.5 text-right font-bold tabular-nums {{ \App\Support\NotenSkala::text($z['note']) }}">{{ \App\Support\NotenSkala::format($z['note'], 1) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums {{ \App\Support\NotenSkala::text($z['portal']) }}">{{ \App\Support\NotenSkala::format($z['portal'], 1) }}</td>
                                            <td @class(['px-3 py-2.5 text-right tabular-nums',
                                                'text-muted' => $z['status'] !== 'abweichung',
                                                'text-note-knapp font-semibold' => $z['status'] === 'abweichung'])>
                                                {{ $z['differenz'] !== null ? ($z['differenz'] > 0 ? '+' : '').\App\Support\NotenSkala::format($z['differenz'], 1) : '–' }}
                                            </td>
                                            <td class="px-5 py-2.5">
                                                @if($z['status'] === 'fehlt' && $darfUebernehmen && $semesterId)
                                                    <label class="inline-flex items-center gap-2 min-h-9 text-sm cursor-pointer">
                                                        <input type="checkbox" name="zeilen[{{ $i }}][uebernehmen]" value="1" checked class="w-5 h-5 rounded border-border text-accent focus:ring-ring">
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
                            <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary disabled:opacity-60">{{ __('Zeugnisnoten übernehmen') }}</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
