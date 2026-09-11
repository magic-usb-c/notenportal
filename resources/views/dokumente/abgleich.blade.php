<x-app-layout>
    <x-slot name="title">Zeugnis-Abgleich</x-slot>
    @php
        $zeilen = $ergebnis['zeilen'];
        $anzahl = collect($zeilen)->countBy('status');
        $fehlend = collect($zeilen)->where('status', 'fehlt');
    @endphp

    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <div class="text-sm text-muted truncate">
                    @if($bereich){{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }} · @endif{{ $dokument->titel }}
                </div>
                <h2 class="font-semibold text-xl text-text">Zeugnis-Abgleich</h2>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ $r('show', ['dokument_id' => $dokument->dokument_id, 'anzeigen' => 1]) }}" target="_blank" rel="noopener" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Zeugnis öffnen</a>
                <a href="{{ $r('index') }}" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Zurück</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">
            <form method="GET" action="{{ $r('abgleich', ['dokument_id' => $dokument->dokument_id]) }}" class="glass rounded-2xl p-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="semester_id" class="block text-xs uppercase tracking-widest text-muted font-medium">Semester</label>
                    <select id="semester_id" name="semester_id" onchange="this.form.submit()"
                            class="mt-1 w-48 rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        <option value="">–</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}" @selected($semesterId === (int) $s->semester_id)>{{ $s->bezeichnung }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-auto sm:ml-auto grid grid-cols-3 gap-2 sm:gap-3">
                    @foreach(['gleich' => ['Übereinstimmend', 'gruen'], 'abweichung' => ['Abweichend', 'gelb'], 'fehlt' => ['Fehlt im Portal', 'neutral']] as $status => [$text, $ton])
                        <x-kachel :label="$text" :wert="$anzahl[$status] ?? 0" :ton="($anzahl[$status] ?? 0) ? $ton : 'neutral'" class="sm:min-w-32" />
                    @endforeach
                </div>
            </form>

            @if(! $ergebnis['text'])
                <div class="glass rounded-2xl px-5 py-12 text-center text-sm text-muted">Kein Text im PDF erkannt</div>
            @elseif($zeilen === [])
                <div class="glass rounded-2xl px-5 py-12 text-center text-sm text-muted">Keine Fächer oder Module erkannt</div>
            @else
                <form method="POST" action="{{ $r('abgleich.uebernehmen', ['dokument_id' => $dokument->dokument_id]) }}" class="flex flex-col gap-4"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <input type="hidden" name="semester_id" value="{{ $semesterId }}">
                    <section class="glass rounded-2xl overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-text">
                                <thead class="text-xs text-muted">
                                    <tr class="border-b border-border">
                                        <th class="text-left px-5 py-2 font-medium">Fach / Modul</th>
                                        <th class="text-right px-3 py-2 font-medium">Zeugnis</th>
                                        <th class="text-right px-3 py-2 font-medium">Portal</th>
                                        <th class="text-right px-3 py-2 font-medium">Differenz</th>
                                        <th class="text-left px-5 py-2 font-medium">Status</th>
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
                                                'text-yellow-700 dark:text-yellow-400 font-semibold' => $z['status'] === 'abweichung'])>
                                                {{ $z['differenz'] !== null ? ($z['differenz'] > 0 ? '+' : '').\App\Support\NotenSkala::format($z['differenz'], 1) : '–' }}
                                            </td>
                                            <td class="px-5 py-2.5">
                                                @if($z['status'] === 'fehlt' && $darfUebernehmen && $semesterId)
                                                    <label class="inline-flex items-center gap-2 min-h-9 text-sm cursor-pointer">
                                                        <input type="checkbox" name="zeilen[{{ $i }}][uebernehmen]" value="1" checked class="w-5 h-5 rounded border-border text-accent focus:ring-ring">
                                                        übernehmen
                                                    </label>
                                                @else
                                                    <x-status :status="match ($z['status']) { 'gleich' => 'gruen', 'abweichung' => 'gelb', default => 'neutral' }"
                                                              :text="match ($z['status']) { 'gleich' => 'stimmt', 'abweichung' => 'abweichend', 'unbekannt' => 'nicht zugeordnet', default => 'fehlt' }" />
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
                            <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary disabled:opacity-60">Zeugnisnoten übernehmen</button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
