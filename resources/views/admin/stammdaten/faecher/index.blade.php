{{-- Fächer: aktive zuerst, nach Kategorie und Name; die ganze Zeile öffnet das Fach. --}}
<x-app-layout>
    <x-slot name="title">{{ __('Fächer') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Fächer')" :zaehler="$faecher->count() ?: null">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.subjects.create') }}" class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neues Fach') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if($faecher->isEmpty())
                <div class="np-karte max-w-7xl">
                    <x-leer symbol="book-open" :titel="__('Noch keine Fächer erfasst.')" />
                </div>
            @else
                <div class="np-karte max-w-7xl p-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="w-28">{{ __('Kürzel') }}</th>
                                <th scope="col">{{ __('Name') }}</th>
                                <th scope="col" class="w-52">{{ __('Kategorie') }}</th>
                                <th scope="col" class="w-28">{{ __('Track') }}</th>
                                <th scope="col" class="w-28 text-right">{{ __('Lehrberufe') }}</th>
                                <th scope="col" class="w-28 text-right">{{ __('Noten') }}</th>
                                <th scope="col" class="w-32"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($faecher as $f)
                                @php($ziel = route('admin.master-data.subjects.edit', $f->fach_id))
                                <tr data-href="{{ $ziel }}">
                                    <td class="font-semibold {{ $f->aktiv ? 'text-text' : 'text-muted' }}">{{ $f->kurzname }}</td>
                                    <td>
                                        <div class="flex min-w-0 items-center gap-2">
                                            <a href="{{ $ziel }}" class="truncate {{ $f->aktiv ? 'text-text' : 'text-muted' }} hover:text-accent-text">{{ $f->name }}</a>
                                            @if($f->skala === 'stufe')<span class="np-marke shrink-0 text-muted">{{ __('Stufe') }}</span>@endif
                                            @unless($f->zaehlt)<span class="np-marke shrink-0 text-muted">{{ __('zählt nicht') }}</span>@endunless
                                            @unless($f->aktiv)<span class="np-marke shrink-0 text-muted">{{ __('Inaktiv') }}</span>@endunless
                                        </div>
                                    </td>
                                    <td class="truncate text-muted">{{ $f->kategorie_name ?? '–' }}</td>
                                    <td class="text-muted">{{ $f->track_typ ?? '–' }}</td>
                                    <td class="text-right {{ $f->lehrberuf_count > 0 ? 'text-text' : 'text-muted' }}">{{ $f->lehrberuf_count }}</td>
                                    <td class="text-right {{ $f->noten_count > 0 ? 'text-text' : 'text-muted' }}">{{ $f->noten_count }}</td>
                                    <td class="text-right">
                                        <x-zeilen-link :href="$ziel" :zeile="$f->name" />
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
