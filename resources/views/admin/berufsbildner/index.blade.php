{{-- Berufsbildner mit Betreuungslast und Warnungen; jede Zahl führt in die gefilterte Lernendenliste. --}}
@php
    $grenzeText = \App\Support\NotenSkala::format($grenze);
    $liste = fn (int $bb, array $mehr = []) => route('admin.learners.index', ['berufsbildner_id' => $bb] + $mehr);
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Berufsbildner') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Berufsbildner')" :zaehler="$berufsbildner->count()">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}" class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Berufsbildner erfassen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if($berufsbildner->isEmpty())
                <div class="np-karte">
                    <x-leer symbol="identification" :titel="__('Keine aktiven Berufsbildner.')">
                        <a href="{{ route('admin.users.create') }}" class="np-knopf np-knopf-sekundaer">{{ __('Berufsbildner erfassen') }}</a>
                    </x-leer>
                </div>
            @else
                <div class="np-karte overflow-hidden">
                    <div class="p-2">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Name') }}</th>
                                    <th scope="col" class="w-32 text-right">{{ __('Lernende') }}</th>
                                    <th scope="col" class="w-56 text-right">{{ __('Ohne Note seit :tage Tagen', ['tage' => $frist]) }}</th>
                                    <th scope="col" class="w-40 text-right">{{ __('Ø unter :grenze', ['grenze' => $grenzeText]) }}</th>
                                    <th scope="col" class="w-28 text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($berufsbildner as $bb)
                                    @php
                                        $id = (int) $bb->berufsbildner_id;
                                        $st = $stats->get($id);
                                        $name = $bb->nachname.' '.$bb->vorname;
                                        $zahlen = [
                                            ['wert' => $st?->ohne_noten ?? 0, 'warnung' => 'keine_noten', 'farbe' => 'bg-note-knapp/14 text-note-knapp hover:bg-note-knapp/22',
                                             'text' => __(':anzahl Lernende von :name ohne Note seit :tage Tagen', ['anzahl' => $st?->ohne_noten ?? 0, 'name' => $name, 'tage' => $frist])],
                                            ['wert' => $st?->tief_avg ?? 0, 'warnung' => 'tief_avg', 'farbe' => 'bg-note-ungenuegend/14 text-note-ungenuegend hover:bg-note-ungenuegend/22',
                                             'text' => __(':anzahl Lernende von :name mit Ø unter :grenze', ['anzahl' => $st?->tief_avg ?? 0, 'name' => $name, 'grenze' => $grenzeText])],
                                        ];
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="flex min-w-0 items-center gap-3">
                                                <span class="np-monogramm size-8 shrink-0 text-2xs" aria-hidden="true">{{ mb_strtoupper(mb_substr($bb->vorname ?? '', 0, 1).mb_substr($bb->nachname ?? '', 0, 1)) ?: '?' }}</span>
                                                <div class="min-w-0">
                                                    <div class="truncate font-medium text-text">{{ $name }}</div>
                                                    <div class="truncate text-xs text-muted">{{ $bb->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-right tabular-nums">
                                            @if(($st?->lernende ?? 0) > 0)
                                                <a href="{{ $liste($id) }}" aria-label="{{ __(':anzahl Lernende von :name', ['anzahl' => $st->lernende, 'name' => $name]) }}"
                                                   class="inline-flex min-h-6 min-w-8 items-center justify-end font-medium text-accent-text underline-offset-2 hover:underline">{{ $st->lernende }}</a>
                                            @else
                                                <span class="text-muted">0</span>
                                            @endif
                                        </td>
                                        @foreach($zahlen as $z)
                                            <td class="text-right tabular-nums">
                                                @if($z['wert'] > 0)
                                                    <a href="{{ $liste($id, ['warnung' => $z['warnung']]) }}" aria-label="{{ $z['text'] }}"
                                                       class="inline-flex h-6 min-w-8 items-center justify-center rounded-md px-1.5 font-semibold transition-colors duration-100 {{ $z['farbe'] }}">{{ $z['wert'] }}</a>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="text-right">
                                            <x-zeilen-link :href="route('admin.users.edit', $bb->benutzer_id)" :zeile="$name" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
