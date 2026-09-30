<x-app-layout>
    <x-slot name="title">{{ __('Fächer') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Fächer')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.subjects.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> {{ __('Neues Fach') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead class="sticky top-0 bg-surface-2">
                        <tr>
                            <th scope="col" class="hidden sm:table-cell h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Kürzel') }}</th>
                            <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Name') }}</th>
                            <th scope="col" class="hidden sm:table-cell h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Kategorie') }}</th>
                            <th scope="col" class="hidden sm:table-cell h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Track') }}</th>
                            <th scope="col" class="hidden sm:table-cell h-9 px-4 text-right text-2xs font-medium text-muted">{{ __('Lehrberufe') }}</th>
                            <th scope="col" class="hidden sm:table-cell h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Status') }}</th>
                            <th scope="col" class="h-9 px-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($faecher as $f)
                            <tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                                <td class="hidden sm:table-cell px-4 font-mono font-semibold text-text">{{ $f->kurzname }}</td>
                                <td class="px-4 text-text">
                                    <span class="block font-mono text-xs font-semibold text-muted sm:hidden">{{ $f->kurzname }}</span>
                                    {{ $f->name }}
                                    @if($f->skala === 'stufe')<span class="ml-1.5 rounded-md bg-surface-2 px-1.5 py-0.5 text-xs text-muted">{{ __('Stufe') }}</span>@endif
                                    @unless($f->zaehlt)<span class="ml-1.5 text-xs text-muted">{{ __('zählt nicht') }}</span>@endunless
                                    <span class="block text-xs text-muted sm:hidden">{{ $f->kategorie_name }}@unless($f->aktiv) · {{ __('inaktiv') }}@endunless</span>
                                </td>
                                <td class="hidden sm:table-cell px-4 text-muted">{{ $f->kategorie_name }}</td>
                                <td class="hidden sm:table-cell px-4">
                                    @if($f->track_typ)
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-text">
                                            {{ $f->track_typ }}
                                        </span>
                                    @else
                                        <span class="text-xs text-muted">–</span>
                                    @endif
                                </td>
                                <td class="hidden sm:table-cell px-4 text-right text-muted">{{ $f->lehrberuf_count }}</td>
                                <td class="hidden sm:table-cell px-4">
                                    @if($f->aktiv)
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-note-gut/14 text-note-gut">{{ __('aktiv') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-muted border border-border">{{ __('inaktiv') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 text-right">
                                    <a href="{{ route('admin.master-data.subjects.edit', $f->fach_id) }}"
                                       class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent-text opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100 hover:bg-accent/10">{{ __('Bearbeiten') }}</a>
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
