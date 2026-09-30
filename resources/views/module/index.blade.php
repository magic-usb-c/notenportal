<x-app-layout>
    <x-slot name="title">{{ __('Module') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Module')" :untertitel="__('Gemeinsame Modulliste – alle sehen dieselben Angaben.')">
            <x-slot:aktionen>
                <a href="{{ route('modules.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <span class="text-lg leading-none">+</span> {{ __('Modul anlegen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <form method="GET" action="{{ route('modules.index') }}" class="flex gap-2">
                <label for="suche" class="sr-only">{{ __('Suche') }}</label>
                <input type="search" id="suche" name="suche" value="{{ $suche }}" placeholder="{{ __('Nummer oder Titel') }}"
                       class="np-feld max-w-sm">
                <button type="submit" class="np-knopf np-knopf-sekundaer np-knopf-gross">{{ __('Suchen') }}</button>
                @if($suche !== '')
                    <a href="{{ route('modules.index') }}" class="inline-flex h-10 items-center px-3 text-sm text-muted hover:text-text">{{ __('Zurücksetzen') }}</a>
                @endif
            </form>

            <div class="np-karte overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="sticky top-0 bg-surface-2">
                            <tr>
                                <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Nummer') }}</th>
                                <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Titel') }}</th>
                                <th scope="col" class="h-9 px-2.5 sm:px-4 text-right text-2xs font-medium text-muted">{{ __('Ziele') }}</th>
                                <th scope="col" class="h-9 px-2.5 sm:px-4 text-right text-2xs font-medium text-muted">{{ __('Unterlagen') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($module as $m)
                                <tr class="h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                                    <td class="px-2.5 sm:px-4 font-mono font-semibold whitespace-nowrap">
                                        <a href="{{ route('modules.show', $m->modul_id) }}" class="text-accent-text hover:underline">{{ $m->modul_nummer }}</a>
                                        @if($m->version)<span class="ml-1.5 font-sans text-2xs font-normal text-muted">V{{ $m->version }}</span>@endif
                                    </td>
                                    <td class="px-2.5 sm:px-4 text-text">
                                        <a href="{{ route('modules.show', $m->modul_id) }}" class="hover:underline">{{ $m->titel }}</a>
                                        @unless($m->aktiv)<span class="ml-2 rounded-md border border-border bg-surface-2 px-2 py-0.5 text-xs text-muted">{{ __('inaktiv') }}</span>@endunless
                                    </td>
                                    <td class="px-2.5 sm:px-4 text-right text-muted">{{ $m->handlungsziele_count }}</td>
                                    <td class="px-2.5 sm:px-4 text-right text-muted">{{ $m->dokumente_count }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-muted">
                                        {{ $suche !== '' ? __('Kein Modul gefunden. Leg es an, dann sehen es alle.') : __('Noch keine Module erfasst.') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $module->links() }}

            <p class="px-1 text-xs text-muted">{{ __('Was du hier ergänzt, steht sofort allen zur Verfügung. Deine Noten bleiben privat.') }}</p>
        </div>
    </div>
</x-app-layout>
