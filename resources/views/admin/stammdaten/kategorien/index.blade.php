<x-app-layout>
    <x-slot name="title">{{ __('Kategorien') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Notenkategorien')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.categories.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neue Kategorie') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="np-karte @container overflow-hidden">
                <div class="overflow-x-auto p-2">
                <table class="np-tabelle text-sm">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Code') }}</th>
                            <th scope="col">{{ __('Name') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell text-right">{{ __('Sortierung') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell text-right">{{ __('Rundung') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell text-right">{{ __('Gewicht') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Promotion') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Status') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($kategorien as $k)
                            <tr class="group">
                                <td class="text-text font-semibold">{{ $k->code }}</td>
                                <td class="text-text">
                                    {{ $k->name }}
                                    <span class="block text-xs text-muted @3xl:hidden">{{ __('Gewicht') }} {{ number_format((float) $k->gewicht_gesamt, 2) }} · {{ __('Rundung') }} {{ number_format((float) $k->rundung_element, 2) }} / {{ number_format((float) $k->rundung_schnitt, 2) }}@unless(is_null($k->promotion_min_schnitt)) · {{ __('Promotion') }}@endunless @unless($k->aktiv) · {{ __('inaktiv') }}@endunless</span>
                                </td>
                                <td class="hidden @3xl:table-cell text-right text-muted">{{ $k->sortierung }}</td>
                                <td class="hidden @3xl:table-cell text-right text-muted text-xs">{{ number_format((float) $k->rundung_element, 2) }} / {{ number_format((float) $k->rundung_schnitt, 2) }}</td>
                                <td class="hidden @3xl:table-cell text-right text-muted">{{ number_format((float) $k->gewicht_gesamt, 2) }}</td>
                                <td class="hidden @3xl:table-cell">
                                    @if(! is_null($k->promotion_min_schnitt))
                                        <span class="text-xs text-muted">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="np-marke text-muted">–</span>
                                    @endif
                                </td>
                                <td class="hidden @3xl:table-cell">
                                    @if($k->aktiv)
                                        <span class="text-xs text-muted">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="np-marke text-muted">{{ __('inaktiv') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-zeilen-link :href="route('admin.master-data.categories.edit', $k->kategorie_id)" :zeile="$k->name"
                                                   class="opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-8 text-center text-muted">
                                    {{ __('Noch keine Kategorien erfasst.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

        </div>
    </div>
</x-app-layout>
