{{-- Notenkategorien in Zeugnisreihenfolge: Rundung, Gewicht und Promotionsregel lesbar statt als Rohwerte. --}}
@use('App\Support\NotenSkala')
@php
    $zahl = fn ($w) => rtrim(rtrim(number_format((float) $w, 2, '.', ''), '0'), '.');
    $rundung = fn ($w) => (float) $w > 0 ? $zahl($w) : __('Ungerundet');
    $promotion = function ($k) use ($zahl) {
        if ($k->promotion_min_schnitt === null) {
            return null;
        }
        return collect([
            __('Schnitt ab :wert', ['wert' => NotenSkala::format($k->promotion_min_schnitt, 1)]),
            $k->promotion_max_ungenuegend !== null ? __('höchstens :n ungenügend', ['n' => (int) $k->promotion_max_ungenuegend]) : null,
            $k->promotion_max_minuspunkte !== null ? __('höchstens :n Minuspunkte', ['n' => $zahl($k->promotion_max_minuspunkte)]) : null,
        ])->filter()->implode(' · ');
    };
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Kategorien') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Kategorien')" :zaehler="$kategorien->count() ?: null">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.categories.create') }}" class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neue Kategorie') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            @if($kategorien->isEmpty())
                <div class="np-karte">
                    <x-leer symbol="tag" :titel="__('Noch keine Kategorien erfasst.')" />
                </div>
            @else
                <div class="np-karte p-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="w-28">{{ __('Code') }}</th>
                                <th scope="col" class="w-56">{{ __('Name') }}</th>
                                <th scope="col" class="w-40 text-right">{{ __('Rundung Zeugnisnote') }}</th>
                                <th scope="col" class="w-36 text-right">{{ __('Rundung Schnitt') }}</th>
                                <th scope="col" class="w-28 text-right">{{ __('Gewicht') }}</th>
                                <th scope="col" class="pl-8">{{ __('Promotion') }}</th>
                                <th scope="col" class="w-24 text-right">{{ __('Fächer') }}</th>
                                <th scope="col" class="w-24 text-right">{{ __('Module') }}</th>
                                <th scope="col" class="w-32"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($kategorien as $k)
                                @php
                                    $ziel = route('admin.master-data.categories.edit', $k->kategorie_id);
                                    $regel = $promotion($k);
                                @endphp
                                <tr data-href="{{ $ziel }}">
                                    <td class="font-semibold {{ $k->aktiv ? 'text-text' : 'text-muted' }}">{{ $k->code }}</td>
                                    <td>
                                        <div class="flex min-w-0 items-center gap-2">
                                            <a href="{{ $ziel }}" class="truncate {{ $k->aktiv ? 'text-text' : 'text-muted' }} hover:text-accent-text">{{ $k->name }}</a>
                                            @unless($k->aktiv)<span class="np-marke shrink-0 text-muted">{{ __('Inaktiv') }}</span>@endunless
                                        </div>
                                    </td>
                                    <td class="text-right text-muted">{{ $rundung($k->rundung_element) }}</td>
                                    <td class="text-right text-muted">{{ $rundung($k->rundung_schnitt) }}</td>
                                    <td class="text-right text-text">{{ $zahl($k->gewicht_gesamt) }}</td>
                                    <td class="truncate pl-8 {{ $regel ? 'text-text' : 'text-muted' }}" @if($regel) title="{{ $regel }}" @endif>{{ $regel ?? '–' }}</td>
                                    <td class="text-right {{ $k->faecher_anzahl > 0 ? 'text-text' : 'text-muted' }}">{{ $k->faecher_anzahl }}</td>
                                    <td class="text-right {{ $k->module_anzahl > 0 ? 'text-text' : 'text-muted' }}">{{ $k->module_anzahl }}</td>
                                    <td class="text-right">
                                        <x-zeilen-link :href="$ziel" :zeile="$k->name" />
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
