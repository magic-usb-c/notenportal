<x-app-layout>
    <x-slot name="title">{{ __('Berufsbildner') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Berufsbildner')" :zaehler="$berufsbildner->count()">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary whitespace-nowrap">
                    {{ __('Neuer Benutzer') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            @if($berufsbildner->isEmpty())
                <div class="rounded-xl border border-border bg-card px-5 py-10 text-center text-muted text-sm">
                    {{ __('Keine aktiven Berufsbildner gefunden.') }}
                </div>
            @else
                {{-- Übersicht-Tabelle --}}
                <div class="rounded-xl border border-border bg-card overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm tabular-nums">
                            <thead class="sticky top-0 z-10 bg-surface-2">
                                <tr>
                                    <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{{ __('Berufsbildner') }}</th>
                                    <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">{{ __('Lernende') }}</th>
                                    <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">{{ __('Ohne Noteneintrag') }}</th>
                                    <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">{{ __('Ø < :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}</th>
                                    <th scope="col" class="h-9 px-3 text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($berufsbildner as $bb)
                                    @php
                                        $st = $stats->get((int) $bb->berufsbildner_id);
                                        $warnOhneNoten = ($st?->ohne_noten ?? 0) > 0;
                                        $warnTiefAvg   = ($st?->tief_avg ?? 0) > 0;
                                    @endphp
                                    @php $bbInitials = strtoupper(mb_substr($bb->vorname ?? '', 0, 1) . mb_substr($bb->nachname ?? '', 0, 1)); @endphp
                                    <tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                                        <td class="px-3 text-left">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-8 h-8 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0">
                                                    {{ $bbInitials ?: '?' }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-text truncate">{{ $bb->nachname }} {{ $bb->vorname }}</div>
                                                    <div class="text-xs text-muted truncate">{{ $bb->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 text-right">
                                            @if(($st?->lernende ?? 0) > 0)
                                                <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id]) }}"
                                                   class="inline-flex items-center justify-center min-w-8 px-2 py-1 rounded-lg font-semibold bg-surface-2 hover:bg-accent hover:text-accent-contrast transition-colors">
                                                    {{ $st->lernende }}
                                                </a>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="px-3 text-right">
                                            @if($warnOhneNoten)
                                                <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id, 'warnung' => 'keine_noten']) }}"
                                                   class="inline-flex items-center justify-center min-w-8 px-2 py-1 rounded-lg font-semibold
                                                          bg-note-knapp/14 text-note-knapp hover:opacity-80 transition-opacity">
                                                    {{ $st->ohne_noten }}
                                                </a>
                                            @else
                                                <span class="text-muted">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 text-right">
                                            @if($warnTiefAvg)
                                                <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id, 'warnung' => 'tief_avg']) }}"
                                                   class="inline-flex items-center justify-center min-w-8 px-2 py-1 rounded-lg font-semibold
                                                          bg-note-ungenuegend/14 text-note-ungenuegend hover:opacity-80 transition-opacity">
                                                    {{ $st->tief_avg }}
                                                </a>
                                            @else
                                                <span class="text-muted">–</span>
                                            @endif
                                        </td>
                                        <td class="px-3 text-right">
                                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                                <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id]) }}"
                                                   class="px-3 py-1.5 rounded-lg border border-border text-xs hover:bg-surface-2 whitespace-nowrap">
                                                    {{ __('Lernende') }}
                                                </a>
                                                <a href="{{ route('admin.users.edit', $bb->benutzer_id) }}"
                                                   class="px-3 py-1.5 rounded-lg bg-accent text-accent-contrast text-xs np-btn-primary whitespace-nowrap">
                                                    {{ __('Bearbeiten') }}
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Legende --}}
                <div class="flex flex-wrap gap-4 text-xs text-muted px-1">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-sm bg-note-knapp"></span>
                        {{ __('Kein Noteneintrag in den letzten 30 Tagen') }}
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block w-3 h-3 rounded-sm bg-note-ungenuegend"></span>
                        {{ __('Gesamtschnitt unter :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}
                    </span>
                    <span class="text-muted">{{ __('Klick auf Zahl → gefilterte Lernenden-Liste') }}</span>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
