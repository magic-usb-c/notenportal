<x-app-layout>
    <x-slot name="title">{{ __('Fächer') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Fächer')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.subjects.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neues Fach') }}
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
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Kategorie') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Track') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell text-right">{{ __('Lehrberufe') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Status') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($faecher as $f)
                            <tr class="group">
                                <td class="hidden @3xl:table-cell font-semibold text-text">{{ $f->kurzname }}</td>
                                <td class="text-text">
                                    <span class="block font-mono text-xs font-semibold text-muted @3xl:hidden">{{ $f->kurzname }}</span>
                                    {{ $f->name }}
                                    @if($f->skala === 'stufe')<span class="ml-1.5 rounded-md bg-surface-2 px-1.5 py-0.5 text-xs text-muted">{{ __('Stufe') }}</span>@endif
                                    @unless($f->zaehlt)<span class="ml-1.5 text-xs text-muted">{{ __('zählt nicht') }}</span>@endunless
                                    <span class="block text-xs text-muted @3xl:hidden">{{ $f->kategorie_name }}@unless($f->aktiv) · {{ __('inaktiv') }}@endunless</span>
                                </td>
                                <td class="hidden @3xl:table-cell text-muted">{{ $f->kategorie_name }}</td>
                                <td class="hidden @3xl:table-cell">
                                    @if($f->track_typ)
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-text">
                                            {{ $f->track_typ }}
                                        </span>
                                    @else
                                        <span class="text-xs text-muted">–</span>
                                    @endif
                                </td>
                                <td class="hidden @3xl:table-cell text-right text-muted">{{ $f->lehrberuf_count }}</td>
                                <td class="hidden @3xl:table-cell">
                                    @if($f->aktiv)
                                        <span class="text-xs text-muted">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-muted border border-border">{{ __('inaktiv') }}</span>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <x-zeilen-link :href="route('admin.master-data.subjects.edit', $f->fach_id)" :zeile="$f->name"
                                                   class="opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100" />
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-muted">
                                    {{ __('Noch keine Fächer erfasst.') }}
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
