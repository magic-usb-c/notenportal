{{-- Semesterplan: das laufende Semester markiert, vergangene zurückgenommen; die ganze Zeile öffnet das Semester,
     Löschen liegt im Semester selbst. --}}
@use('App\Models\Semester')
@php $heute = now()->toDateString(); @endphp
<x-app-layout>
    <x-slot name="title">{{ __('Semester') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Semester')" :zaehler="$semester->count() ?: null">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.semesters.create') }}" class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neues Semester') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if($semester->isEmpty())
                <div class="np-karte">
                    <x-leer symbol="calendar" :titel="__('Noch keine Semester erfasst.')" />
                </div>
            @else
                <div class="np-karte p-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="w-48">{{ __('Bezeichnung') }}</th>
                                <th scope="col" class="w-48">{{ __('Semester') }}</th>
                                <th scope="col">{{ __('Zeitraum') }}</th>
                                <th scope="col" class="w-28 text-right">{{ __('Noten') }}</th>
                                <th scope="col" class="w-32"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($semester as $s)
                                @php
                                    $ziel = route('admin.master-data.semesters.edit', $s->semester_id);
                                    $aktuell = $s->start_datum <= $heute && $s->end_datum >= $heute;
                                    $vorbei = $s->end_datum < $heute;
                                @endphp
                                <tr data-href="{{ $ziel }}" @if($aktuell) aria-current="date" @endif>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ $ziel }}" class="font-semibold {{ $vorbei ? 'text-muted' : 'text-text' }} hover:text-accent-text">{{ $s->bezeichnung }}</a>
                                            @if($aktuell)<span class="np-marke bg-accent/12 text-accent-text">{{ __('Aktuell') }}</span>@endif
                                        </div>
                                    </td>
                                    <td class="{{ $vorbei ? 'text-muted' : 'text-text' }}">{{ Semester::neutralerName($s->start_datum) ?? '–' }}</td>
                                    <td class="tabular-nums text-muted">
                                        {{ \Carbon\Carbon::parse($s->start_datum)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($s->end_datum)->format('d.m.Y') }}
                                    </td>
                                    <td class="text-right {{ $s->noten_anzahl > 0 ? 'text-text' : 'text-muted' }}">{{ $s->noten_anzahl }}</td>
                                    <td class="text-right">
                                        <x-zeilen-link :href="$ziel" :zeile="$s->bezeichnung" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
