<x-app-layout>
    <x-slot name="title">{{ __('Lehrberufe') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Lehrberufe')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neuer Lehrberuf') }}
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
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Kürzel') }}</th>
                            <th scope="col">{{ __('Name') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Status') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($lehrberufe as $lb)
                            <tr class="group">
                                <td class="hidden @3xl:table-cell font-semibold text-text">{{ $lb->kuerzel }}</td>
                                <td class="text-text">
                                    <span class="block tabular-nums text-xs font-semibold text-muted @3xl:hidden">{{ $lb->kuerzel }}</span>
                                    {{ $lb->name }}
                                    @unless($lb->aktiv)
                                        <span class="block text-xs text-muted @3xl:hidden">{{ __('inaktiv') }}</span>
                                    @endunless
                                </td>
                                <td class="hidden @3xl:table-cell">
                                    @if($lb->aktiv)
                                        <span class="text-xs text-muted">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="np-marke text-muted">{{ __('inaktiv') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <div class="flex flex-col items-end justify-end gap-1 @xl:flex-row @xl:items-center @xl:gap-3 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                        <x-zeilen-link :href="route('admin.master-data.professions.edit', $lb->lehrberuf_id)" :zeile="$lb->name" />
                                        <a href="{{ route('admin.master-data.professions.show', $lb->lehrberuf_id) }}"
                                           class="inline-flex min-h-6 items-center text-right text-sm text-accent-text hover:underline @xl:whitespace-nowrap">{{ __('Module & Fächer') }}</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-muted">
                                    {{ __('Noch keine Lehrberufe erfasst.') }}
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
