{{-- Lehrberufe: die ganze Zeile öffnet Module und Fächer des Berufs, «Bearbeiten» Kürzel, Name und Status.
     Die Zahl der Lernenden führt in die gefilterte Lernendenliste. --}}
<x-app-layout>
    <x-slot name="title">{{ __('Lehrberufe') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Lehrberufe')" :zaehler="$lehrberufe->count() ?: null">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.create') }}" class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neuer Lehrberuf') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if($lehrberufe->isEmpty())
                <div class="np-karte">
                    <x-leer symbol="briefcase" :titel="__('Noch keine Lehrberufe erfasst.')" />
                </div>
            @else
                <div class="np-karte p-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="w-32">{{ __('Kürzel') }}</th>
                                <th scope="col">{{ __('Name') }}</th>
                                <th scope="col" class="w-32 text-right">{{ __('Lernende') }}</th>
                                <th scope="col" class="w-32 text-right">{{ __('Module') }}</th>
                                <th scope="col" class="w-32 text-right">{{ __('Fächer') }}</th>
                                <th scope="col" class="w-32"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($lehrberufe as $lb)
                                @php($ziel = route('admin.master-data.professions.show', $lb->lehrberuf_id))
                                <tr data-href="{{ $ziel }}">
                                    <td class="font-semibold {{ $lb->aktiv ? 'text-text' : 'text-muted' }}">{{ $lb->kuerzel }}</td>
                                    <td>
                                        <div class="flex min-w-0 items-center gap-2">
                                            <a href="{{ $ziel }}" class="truncate {{ $lb->aktiv ? 'text-text' : 'text-muted' }} hover:text-accent-text">{{ $lb->name }}</a>
                                            @unless($lb->aktiv)<span class="np-marke shrink-0 text-muted">{{ __('Inaktiv') }}</span>@endunless
                                        </div>
                                    </td>
                                    <td class="text-right">
                                        @if($lb->lernende_anzahl > 0)
                                            <a href="{{ route('admin.learners.index', ['lehrberuf_id' => $lb->lehrberuf_id]) }}"
                                               aria-label="{{ __('Lernende im Lehrberuf :name', ['name' => $lb->name]) }}"
                                               class="inline-flex min-h-6 items-center text-text hover:text-accent-text">{{ $lb->lernende_anzahl }}</a>
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="text-right {{ $lb->module_anzahl > 0 ? 'text-text' : 'text-muted' }}">{{ $lb->module_anzahl }}</td>
                                    <td class="text-right {{ $lb->faecher_anzahl > 0 ? 'text-text' : 'text-muted' }}">{{ $lb->faecher_anzahl }}</td>
                                    <td class="text-right">
                                        <x-zeilen-link :href="route('admin.master-data.professions.edit', $lb->lehrberuf_id)" :zeile="$lb->name" />
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
