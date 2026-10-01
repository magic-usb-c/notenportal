<x-app-layout>
    <x-slot name="title">{{ __('Module') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Module')" :zaehler="$module->count() ?: null">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.modules.catalog') }}"
                   class="np-knopf np-knopf-sekundaer">
                    {{ __('Katalog einlesen') }}
                </a>
                <a href="{{ route('admin.master-data.modules.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neues Modul') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $auswahl = 'np-feld np-feld-klein w-auto max-w-64';
        $aktiveFilter = collect([$suche, $lehrberufId, $kategorieId])->filter()->count();
        $aktiveWeitere = $gruppieren !== '' ? 1 : 0;
        $leer = $module->isEmpty() && $aktiveFilter === 0;
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-4">

            @if($leer)
                <div class="np-karte">
                    <x-leer symbol="rectangle-stack" :titel="__('Noch keine Module erfasst.')" />
                </div>
            @else
            <x-filterleiste :action="route('admin.master-data.modules.index')" suche-name="suche" :suche-wert="$suche"
                             :suche-platzhalter="__('Nummer oder Titel')" :aktive-filter="$aktiveFilter + $aktiveWeitere" :aktive-weitere="$aktiveWeitere"
                             :zurueck="route('admin.master-data.modules.index')">
                <label for="lehrberuf_id" class="sr-only">{{ __('Lehrberuf') }}</label>
                <select name="lehrberuf_id" id="lehrberuf_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">{{ __('Alle Lehrberufe') }}</option>
                    @foreach($lehrberufe as $lb)
                        <option value="{{ $lb->lehrberuf_id }}" @selected($lehrberufId === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                    @endforeach
                </select>

                <label for="kategorie_id" class="sr-only">{{ __('Lernort') }}</label>
                <select name="kategorie_id" id="kategorie_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">{{ __('Alle Lernorte') }}</option>
                    @foreach($kategorien as $k)
                        <option value="{{ $k->kategorie_id }}" @selected($kategorieId === (int) $k->kategorie_id)>{{ $k->name }}</option>
                    @endforeach
                </select>

                <x-slot:weitere>
                    <label for="gruppieren" class="sr-only">{{ __('Gruppieren nach') }}</label>
                    <select name="gruppieren" id="gruppieren" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="" @selected($gruppieren === '')>{{ __('Keine Gruppierung') }}</option>
                        <option value="lehrberuf" @selected($gruppieren === 'lehrberuf')>{{ __('Nach Lehrberuf gruppieren') }}</option>
                        <option value="lernort" @selected($gruppieren === 'lernort')>{{ __('Nach Lernort gruppieren') }}</option>
                    </select>
                </x-slot:weitere>
            </x-filterleiste>

            @if($gruppieren !== '')
                @forelse($gruppen as $name => $zeilen)
                    <section class="np-karte">
                        <h3 class="px-5 pt-4 pb-1 text-sm font-semibold text-text">{{ $name }}</h3>
                        <div class="px-2 pb-2">
                            <table class="np-tabelle table-fixed text-sm">
                                <thead>
                                    @include('admin.stammdaten.module._kopf')
                                </thead>
                                <tbody>
                                    @foreach($zeilen as $m)
                                        @include('admin.stammdaten.module._zeile')
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @empty
                    <div class="np-karte px-3 py-6 text-center text-sm text-muted">{{ __('Keine Module für diese Filtereinstellungen gefunden.') }}</div>
                @endforelse
            @else
                <div class="np-karte">
                    <div class="p-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            @include('admin.stammdaten.module._kopf')
                        </thead>
                        <tbody>
                            @forelse($module as $m)
                                @include('admin.stammdaten.module._zeile')
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-muted">{{ __('Keine Module für diese Filtereinstellungen gefunden.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
            @endif
            @endif
        </div>
    </div>
</x-app-layout>
