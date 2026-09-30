<x-app-layout>
    <x-slot name="title">{{ __('Kategorien') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Notenkategorien')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.categories.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> {{ __('Neue Kategorie') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="@container rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead class="sticky top-0 bg-surface-2">
                        <tr>
                            <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Code') }}</th>
                            <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Name') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-2.5 sm:px-4 text-right text-2xs font-medium text-muted">{{ __('Sortierung') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-2.5 sm:px-4 text-right text-2xs font-medium text-muted">{{ __('Rundung') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-2.5 sm:px-4 text-right text-2xs font-medium text-muted">{{ __('Gewicht') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Promotion') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Status') }}</th>
                            <th scope="col" class="h-9 px-2.5 sm:px-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($kategorien as $k)
                            <tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                                <td class="px-2.5 sm:px-4 font-mono text-text font-semibold">{{ $k->code }}</td>
                                <td class="px-2.5 sm:px-4 text-text">
                                    {{ $k->name }}
                                    <span class="block text-xs text-muted @3xl:hidden">{{ __('Gewicht') }} {{ number_format((float) $k->gewicht_gesamt, 2) }} · {{ __('Rundung') }} {{ number_format((float) $k->rundung_element, 2) }} / {{ number_format((float) $k->rundung_schnitt, 2) }}@unless(is_null($k->promotion_min_schnitt)) · {{ __('Promotion') }}@endunless @unless($k->aktiv) · {{ __('inaktiv') }}@endunless</span>
                                </td>
                                <td class="hidden @3xl:table-cell px-2.5 sm:px-4 text-right text-muted">{{ $k->sortierung }}</td>
                                <td class="hidden @3xl:table-cell px-2.5 sm:px-4 text-right text-muted text-xs">{{ number_format((float) $k->rundung_element, 2) }} / {{ number_format((float) $k->rundung_schnitt, 2) }}</td>
                                <td class="hidden @3xl:table-cell px-2.5 sm:px-4 text-right text-muted">{{ number_format((float) $k->gewicht_gesamt, 2) }}</td>
                                <td class="hidden @3xl:table-cell px-2.5 sm:px-4">
                                    @if(! is_null($k->promotion_min_schnitt))
                                        <span class="text-xs text-muted">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-muted border border-border">–</span>
                                    @endif
                                </td>
                                <td class="hidden @3xl:table-cell px-2.5 sm:px-4">
                                    @if($k->aktiv)
                                        <span class="text-xs text-muted">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-muted border border-border">{{ __('inaktiv') }}</span>
                                    @endif
                                </td>
                                <td class="px-1.5 sm:px-4 text-right">
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
