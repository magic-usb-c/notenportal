<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Admin: Berufsbildner</h2>
            <a href="{{ route('admin.benutzer.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap text-sm">
                <span class="text-lg leading-none">+</span>
                Neuer Benutzer
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('status'))
                <div class="rounded-xl border border-green-300 bg-green-50 dark:bg-green-900/20 dark:border-green-700 px-4 py-3 text-sm text-green-800 dark:text-green-200" data-autohide>
                    {{ session('status') }}
                </div>
            @endif

            @if($berufsbildner->isEmpty())
                <div class="bg-card border border-border rounded-2xl shadow-sm px-5 py-10 text-center text-muted text-sm">
                    Keine aktiven Berufsbildner gefunden.
                </div>
            @else
                {{-- Übersicht-Tabelle --}}
                <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-text">
                            <thead class="bg-bg text-muted">
                                <tr>
                                    <th class="text-left p-3">Berufsbildner</th>
                                    <th class="text-center p-3 whitespace-nowrap">Lernende</th>
                                    <th class="text-center p-3 whitespace-nowrap">Ohne Noteneintrag</th>
                                    <th class="text-center p-3 whitespace-nowrap">Ø &lt; 4.0</th>
                                    <th class="text-right p-3">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($berufsbildner as $bb)
                                    @php
                                        $st = $stats->get((int) $bb->berufsbildner_id);
                                        $warnOhneNoten = ($st?->ohne_noten ?? 0) > 0;
                                        $warnTiefAvg   = ($st?->tief_avg ?? 0) > 0;
                                    @endphp
                                    <tr class="hover:bg-bg">
                                        <td class="p-3">
                                            <div class="font-medium text-text">{{ $bb->nachname }} {{ $bb->vorname }}</div>
                                            <div class="text-xs text-muted">{{ $bb->email }}</div>
                                        </td>
                                        <td class="p-3 text-center">
                                            @if(($st?->lernende ?? 0) > 0)
                                                <a href="{{ route('admin.lernende.index', ['berufsbildner_id' => $bb->berufsbildner_id]) }}"
                                                   class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 rounded-xl font-semibold bg-bg hover:bg-accent hover:text-white transition-colors">
                                                    {{ $st->lernende }}
                                                </a>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-center">
                                            @if($warnOhneNoten)
                                                <a href="{{ route('admin.lernende.index', ['berufsbildner_id' => $bb->berufsbildner_id, 'warnung' => 'keine_noten']) }}"
                                                   class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 rounded-xl font-semibold
                                                          bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 hover:opacity-80 transition-opacity">
                                                    {{ $st->ohne_noten }}
                                                </a>
                                            @else
                                                <span class="text-muted">–</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-center">
                                            @if($warnTiefAvg)
                                                <a href="{{ route('admin.lernende.index', ['berufsbildner_id' => $bb->berufsbildner_id, 'warnung' => 'tief_avg']) }}"
                                                   class="inline-flex items-center justify-center min-w-[2rem] px-2 py-1 rounded-xl font-semibold
                                                          bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 hover:opacity-80 transition-opacity">
                                                    {{ $st->tief_avg }}
                                                </a>
                                            @else
                                                <span class="text-muted">–</span>
                                            @endif
                                        </td>
                                        <td class="p-3 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="{{ route('admin.lernende.index', ['berufsbildner_id' => $bb->berufsbildner_id]) }}"
                                                   class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg whitespace-nowrap">
                                                    Lernende
                                                </a>
                                                <a href="{{ route('admin.benutzer.edit', $bb->benutzer_id) }}"
                                                   class="px-3 py-1.5 rounded-xl bg-accent text-white text-xs hover:opacity-90 whitespace-nowrap">
                                                    Bearbeiten
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="px-4 py-2 border-t border-border text-xs text-muted">
                        {{ $berufsbildner->count() }} Berufsbildner
                    </div>
                </div>

                {{-- Legende --}}
                <div class="flex flex-wrap gap-4 text-xs text-muted px-1">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded bg-yellow-200 dark:bg-yellow-800"></span>
                        Kein Noteneintrag in den letzten 30 Tagen
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded bg-red-200 dark:bg-red-800"></span>
                        Ø aktuelles Semester unter 4.0
                    </span>
                    <span class="text-muted">Klick auf Zahl → gefilterte Lernenden-Liste</span>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
