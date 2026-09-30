<x-app-layout>
    <x-slot name="title">{{ __('Lehrberufe') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Lehrberufe')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <span class="text-lg leading-none">+</span> {{ __('Neuer Lehrberuf') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="np-karte @container overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-surface-2">
                        <tr>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Kürzel') }}</th>
                            <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Name') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Status') }}</th>
                            <th scope="col" class="h-9 px-2.5 sm:px-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($lehrberufe as $lb)
                            <tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                                <td class="hidden @3xl:table-cell px-2.5 sm:px-4 font-mono font-semibold text-text">{{ $lb->kuerzel }}</td>
                                <td class="px-2.5 sm:px-4 text-text">
                                    <span class="block font-mono text-xs font-semibold text-muted @3xl:hidden">{{ $lb->kuerzel }}</span>
                                    {{ $lb->name }}
                                    @unless($lb->aktiv)
                                        <span class="block text-xs text-muted @3xl:hidden">{{ __('inaktiv') }}</span>
                                    @endunless
                                </td>
                                <td class="hidden @3xl:table-cell px-2.5 sm:px-4">
                                    @if($lb->aktiv)
                                        <span class="text-xs text-muted">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-muted border border-border">{{ __('inaktiv') }}</span>
                                    @endif
                                </td>
                                <td class="px-1.5 sm:px-4 py-1 text-right">
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
