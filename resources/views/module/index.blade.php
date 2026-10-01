<x-app-layout>
    <x-slot name="title">{{ __('Module') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Module')" :untertitel="__('Gemeinsame Modulliste – alle sehen dieselben Angaben.')">
            <x-slot:aktionen>
                <a href="{{ route('modules.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Modul anlegen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <form method="GET" action="{{ route('modules.index') }}" class="flex items-center gap-3">
                <x-suchfeld name="suche" :value="$suche" :platzhalter="__('Nummer oder Titel')" :label="__('Module suchen')"
                            x-on:input.debounce.400ms="$el.form.requestSubmit()" class="w-full max-w-xs" />
                @if($suche !== '')
                    <a href="{{ route('modules.index') }}" class="np-knopf np-knopf-schlicht">{{ __('Zurücksetzen') }}</a>
                @endif
                <noscript><button type="submit" class="np-knopf np-knopf-sekundaer">{{ __('Suchen') }}</button></noscript>
            </form>

            <div class="np-karte">
                <div class="overflow-x-auto p-2">
                    <table class="np-tabelle text-sm">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Nummer') }}</th>
                                <th scope="col">{{ __('Titel') }}</th>
                                <th scope="col" class="text-right">{{ __('Ziele') }}</th>
                                <th scope="col" class="text-right">{{ __('Unterlagen') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($module as $m)
                                <tr>
                                    <td class="whitespace-nowrap font-medium">
                                        <a href="{{ route('modules.show', $m->modul_id) }}" class="text-text hover:text-accent-text">{{ $m->modul_nummer }}</a>
                                        @if($m->version)<span class="ml-1.5 text-2xs font-normal text-muted">V{{ $m->version }}</span>@endif
                                    </td>
                                    <td class="text-text">
                                        <a href="{{ route('modules.show', $m->modul_id) }}" class="hover:text-accent-text">{{ $m->titel }}</a>
                                        @unless($m->aktiv)<span class="np-marke ml-2 text-muted">{{ __('Inaktiv') }}</span>@endunless
                                    </td>
                                    <td class="text-right text-muted">{{ $m->handlungsziele_count }}</td>
                                    <td class="text-right text-muted">{{ $m->dokumente_count }}</td>
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

            <div class="px-1">{{ $module->links() }}</div>

            <p class="px-1 text-xs text-muted">{{ __('Was du hier ergänzt, steht sofort allen zur Verfügung. Deine Noten bleiben privat.') }}</p>
        </div>
    </div>
</x-app-layout>
