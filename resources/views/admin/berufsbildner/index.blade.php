<x-app-layout>
    <x-slot name="title">{{ __('Berufsbildner') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Berufsbildner')" :zaehler="$berufsbildner->count()">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}"
                   class="np-knopf np-knopf-primaer">
                    {{ __('Neuer Benutzer') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            @if($berufsbildner->isEmpty())
                <div class="np-karte px-5 py-10 text-center text-muted text-sm">
                    {{ __('Keine aktiven Berufsbildner gefunden.') }}
                </div>
            @else
                {{-- Übersicht-Tabelle --}}
                <div class="np-karte @container overflow-hidden">
                    <div class="overflow-x-auto p-2">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Berufsbildner') }}</th>
                                    <th scope="col" class="hidden @3xl:table-cell text-right whitespace-nowrap">{{ __('Lernende') }}</th>
                                    <th scope="col" class="hidden @3xl:table-cell text-right whitespace-nowrap">{{ __('Ohne Noteneintrag') }}</th>
                                    <th scope="col" class="hidden @3xl:table-cell text-right whitespace-nowrap">{{ __('Ø < :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}</th>
                                    <th scope="col" class="text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($berufsbildner as $bb)
                                    @php
                                        $st = $stats->get((int) $bb->berufsbildner_id);
                                        $warnOhneNoten = ($st?->ohne_noten ?? 0) > 0;
                                        $warnTiefAvg   = ($st?->tief_avg ?? 0) > 0;
                                    @endphp
                                    @php $bbInitials = strtoupper(mb_substr($bb->vorname ?? '', 0, 1) . mb_substr($bb->nachname ?? '', 0, 1)); @endphp
                                    <tr class="group">
                                        <td class="text-left">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="w-8 h-8 rounded-full bg-accent/10 text-accent-text text-xs font-bold hidden sm:flex items-center justify-center shrink-0">
                                                    {{ $bbInitials ?: '?' }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-text break-words sm:truncate">{{ $bb->nachname }} {{ $bb->vorname }}</div>
                                                    <div class="text-xs text-muted break-all sm:truncate" title="{{ $bb->email }}">{{ $bb->email }}</div>
                                                    <div class="flex flex-wrap items-center gap-x-3 text-xs text-muted @3xl:hidden">
                                                        <span>{{ $st?->lernende ?? 0 }} {{ __('Lernende') }}</span>
                                                        @if($warnOhneNoten)
                                                            <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id, 'warnung' => 'keine_noten']) }}"
                                                               class="inline-flex min-h-6 items-center gap-1.5 text-note-knapp">
                                                                {{ __('Ohne Noteneintrag') }} <span class="font-semibold">{{ $st->ohne_noten }}</span>
                                                            </a>
                                                        @endif
                                                        @if($warnTiefAvg)
                                                            <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id, 'warnung' => 'tief_avg']) }}"
                                                               class="inline-flex min-h-6 items-center gap-1.5 text-note-ungenuegend">
                                                                {{ __('Ø < :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }} <span class="font-semibold">{{ $st->tief_avg }}</span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="hidden @3xl:table-cell text-right">
                                            @if(($st?->lernende ?? 0) > 0)
                                                <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id]) }}"
                                                   class="inline-flex min-h-6 min-w-8 items-center justify-end font-medium text-accent-text tabular-nums hover:underline underline-offset-2">
                                                    {{ $st->lernende }}
                                                </a>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        <td class="hidden @3xl:table-cell text-right">
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
                                        <td class="hidden @3xl:table-cell text-right">
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
                                        <td class="text-right">
                                            <div class="flex flex-col items-end justify-end gap-1 sm:flex-row sm:items-center sm:gap-2 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                                <a href="{{ route('admin.learners.index', ['berufsbildner_id' => $bb->berufsbildner_id]) }}"
                                                   class="np-knopf np-knopf-sekundaer np-knopf-klein">
                                                    {{ __('Lernende') }}
                                                </a>
                                                <a href="{{ route('admin.users.edit', $bb->benutzer_id) }}"
                                                   class="np-knopf np-knopf-primaer np-knopf-klein">
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
