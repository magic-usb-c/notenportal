<x-app-layout>
    <x-slot name="title">{{ __('Module') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Module')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.modules.catalog') }}"
                   class="np-knopf np-knopf-sekundaer">
                    {{ __('Katalog einlesen') }}
                </a>
                <a href="{{ route('admin.master-data.modules.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <span class="text-lg leading-none">+</span> {{ __('Neues Modul') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $auswahl = 'np-feld px-2.5 sm:w-44';
        $aktiveFilter = collect([$suche, $lehrberufId, $kategorieId])->filter()->count();
        $aktiveWeitere = $gruppieren !== '' ? 1 : 0;

        $zeile = function ($m) {
            // Verweis in den Modulbaukasten nur mit bekannter Version – siehe App\Support\Modulbaukasten.
            $mbk = \App\Support\Modulbaukasten::modulLink($m->modul_nummer, $m->version ?? null);
            // Wie <x-zeilen-link>: schmal nur der Stift, der Name nennt das Modul
            $name = __('Bearbeiten').': '.$m->modul_nummer.' '.$m->titel;

            return '<tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                <td class="px-2.5 sm:px-4 font-mono font-semibold text-text whitespace-nowrap">'.e($m->modul_nummer)
                    .(($m->version ?? null) ? '<span class="ml-1.5 font-sans text-2xs font-normal text-muted">V'.e($m->version).'</span>' : '').'</td>
                <td class="px-2.5 sm:px-4 text-text">'.e($m->titel).($mbk === null ? '' :
                    ' <a href="'.e($mbk).'" target="_blank" rel="noopener noreferrer"
                         class="ml-1 text-xs text-accent-text underline underline-offset-2 hover:opacity-80">'
                        .e(__('Modulbaukasten')).'<span class="sr-only"> ('.e(__('neues Fenster')).')</span></a>')
                    .'<span class="block text-xs text-muted sm:hidden">'
                        .e((int) $m->lehrberuf_count === 1 ? __('1 Lehrberuf') : __(':anzahl Lehrberufe', ['anzahl' => (int) $m->lehrberuf_count]))
                        .($m->aktiv ? '' : ' · '.e(__('inaktiv'))).'</span></td>
                <td class="hidden px-2.5 sm:px-4 text-right text-muted sm:table-cell">'.e((string) $m->lehrberuf_count).'</td>
                <td class="hidden px-2.5 sm:px-4 sm:table-cell">'.($m->aktiv
                    ? '<span class="text-xs text-muted">'.e(__('aktiv')).'</span>'
                    : '<span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-muted border border-border">'.e(__('inaktiv')).'</span>').'</td>
                <td class="px-1.5 sm:px-4 text-right">
                    <a href="'.e(route('admin.master-data.modules.edit', $m->modul_id)).'" aria-label="'.e($name).'" title="'.e($name).'"
                       class="np-ziel inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg text-sm text-accent-text opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100 pointer-coarse:opacity-100 hover:bg-accent/10 @xl:px-3">'
                        .'<span class="hidden @xl:inline">'.e(__('Bearbeiten')).'</span>'
                        .'<svg class="size-4 @xl:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg></a>
                </td>
            </tr>';
        };
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

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
                    <div class="np-karte overflow-hidden">
                        <div class="px-4 py-2.5 border-b border-border bg-bg/40">
                            <h3 class="text-sm font-semibold text-text">{{ $name }}</h3>
                        </div>
                        <div class="@container overflow-x-auto">
                            <table class="w-full text-sm tabular-nums">
                                <thead class="sticky top-0 bg-surface-2">
                                    <tr>
                                        <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Nummer') }}</th>
                                        <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Titel') }}</th>
                                        <th scope="col" class="hidden h-9 px-2.5 sm:px-4 text-right text-2xs font-medium text-muted sm:table-cell">{{ __('Lehrberufe') }}</th>
                                        <th scope="col" class="hidden h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted sm:table-cell">{{ __('Status') }}</th>
                                        <th scope="col" class="h-9 px-2.5 sm:px-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($zeilen as $m)
                                        {!! $zeile($m) !!}
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="np-karte p-6 text-center text-muted">{{ __('Keine Module gefunden.') }}</div>
                @endforelse
            @else
                <div class="np-karte overflow-hidden">
                    <div class="@container overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="sticky top-0 bg-surface-2">
                            <tr>
                                <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Nummer') }}</th>
                                <th scope="col" class="h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted">{{ __('Titel') }}</th>
                                <th scope="col" class="hidden h-9 px-2.5 sm:px-4 text-right text-2xs font-medium text-muted sm:table-cell">{{ __('Lehrberufe') }}</th>
                                <th scope="col" class="hidden h-9 px-2.5 sm:px-4 text-left text-2xs font-medium text-muted sm:table-cell">{{ __('Status') }}</th>
                                <th scope="col" class="h-9 px-2.5 sm:px-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($module as $m)
                                {!! $zeile($m) !!}
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-muted">
                                        @if($aktiveFilter > 0)
                                            {{ __('Keine Module für diese Filtereinstellungen gefunden.') }}
                                        @else
                                            {{ __('Noch keine Module erfasst.') }}
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
            @endif

            <p class="text-xs text-muted px-1">
                {{ __('Module werden über die') }}
                <a href="{{ route('admin.master-data.professions.index') }}" class="text-accent-text underline underline-offset-2">{{ __('Lehrberuf-Detailseite') }}</a>
                {{ __('einem Lehrberuf zugewiesen.') }}
            </p>

        </div>
    </div>
</x-app-layout>
