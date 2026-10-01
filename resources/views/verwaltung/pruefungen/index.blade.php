<x-app-layout>
    <x-slot name="title">{{ __('Prüfungstermine') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Prüfungstermine')" :zaehler="$anzahl">
            <x-slot:aktionen>
                @if($abgabeMoeglich)
                    <a href="{{ route($bereich.'.exams.index', array_merge($filter, ['planen' => 1])) }}" @unless($bearbeiten) x-data @click.prevent="$dispatch('open-drawer', 'abgabetermin')" @endunless
                       class="np-knopf np-knopf-primaer">
                        <x-symbol name="plus" strich="2" />{{ __('Abgabetermin') }}
                    </a>
                @endif
                <a href="{{ route('settings.calendar') }}" class="np-knopf np-knopf-sekundaer">
                    {{ __('Kalender-Abo') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $auswahl = 'np-feld np-feld-klein w-auto max-w-64';
        $aktiveFilter = ($filter['lernender_id'] ? 1 : 0) + ($filter['zeitraum'] !== 'alle' ? 1 : 0);
        $pillBasis = 'np-marke';
        // Spalten ohne einen einzigen Wert entfallen; feste Breiten halten die Abschnitte untereinander bündig.
        $alle = collect($gruppen)->flatMap(fn ($g) => $g['zeilen'])->map(fn ($z) => $z['pruefung']);
        $mitArt = $alle->contains(fn ($p) => filled($p->pruefungsart));
        $mitRaum = $alle->contains(fn ($p) => filled($p->raum));
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-8 flex flex-col gap-5">

            <x-filterleiste :action="route($bereich.'.exams.index')" :zaehler="$anzahl" :zurueck="route($bereich.'.exams.index')" :aktive-filter="$aktiveFilter">
                <label for="lernender_id" class="sr-only">{{ __('Lernende/r') }}</label>
                <select name="lernender_id" id="lernender_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">{{ __('Alle Lernenden') }}</option>
                    @foreach($lernendeOptionen as $l)
                        <option value="{{ $l->lernender_id }}" @selected($filter['lernender_id'] === (int) $l->lernender_id)>{{ $l->benutzer->nachname }} {{ $l->benutzer->vorname }}</option>
                    @endforeach
                </select>

                <label for="zeitraum" class="sr-only">{{ __('Zeitraum') }}</label>
                <select name="zeitraum" id="zeitraum" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="7" @selected($filter['zeitraum'] === '7')>{{ __('Nächste 7 Tage') }}</option>
                    <option value="30" @selected($filter['zeitraum'] === '30')>{{ __('Nächste 30 Tage') }}</option>
                    <option value="alle" @selected($filter['zeitraum'] === 'alle')>{{ __('Nächste 90 Tage') }}</option>
                </select>
            </x-filterleiste>

            @if(! $abgabeMoeglich && \App\Models\Pruefung::hatArtSpalte())
                <p class="text-xs text-muted">{{ __('Lernende/n auswählen, um Abgabetermine zu erfassen.') }}</p>
            @endif

            @forelse($gruppen as $g)
                <section class="np-karte">
                    <h3 class="flex items-center gap-2 px-5 pt-4 pb-2 text-sm font-semibold text-text">
                        {{ $g['label'] }} <span class="np-marke font-medium tabular-nums text-muted">{{ $g['zeilen']->count() }}</span>
                    </h3>

                    <div class="overflow-x-auto px-2 pb-2">
                        <table class="np-tabelle table-fixed text-sm">
                            <colgroup>
                                <col class="w-32">
                                <col class="w-64">
                                <col>
                                @if($mitArt)<col class="w-44">@endif
                                @if($mitRaum)<col class="w-32">@endif
                                <col class="w-32">
                                @if($abgabeMoeglich)<col class="w-36">@endif
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col" class="whitespace-nowrap">{{ __('Datum') }}</th>
                                    <th scope="col">{{ __('Lernende/r') }}</th>
                                    <th scope="col">{{ __('Fach / Modul') }}</th>
                                    @if($mitArt)<th scope="col">{{ __('Art') }}</th>@endif
                                    @if($mitRaum)<th scope="col">{{ __('Raum') }}</th>@endif
                                    <th scope="col">{{ __('Status') }}</th>
                                    @if($abgabeMoeglich)
                                        <th scope="col"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($g['zeilen'] as $z)
                                    @php [$p, $status] = [$z['pruefung'], $z['status']]; @endphp
                                    <tr>
                                        <td class="whitespace-nowrap">
                                            {{ $p->datum->format('d.m.Y') }}
                                            @if($p->uhrzeit)<span class="block text-xs text-muted">{{ substr((string) $p->uhrzeit, 0, 5) }}</span>@endif
                                        </td>
                                        <td class="truncate">
                                            <a href="{{ route($bereich.'.learners.show', $p->lernender_id) }}" class="text-text hover:text-accent-text">
                                                {{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="flex min-w-0 items-center gap-1.5">
                                                <span class="truncate text-text">{{ $p->bezeichnung() }}</span>
                                                @if($p->istAbgabe())
                                                    <span class="{{ $pillBasis }} bg-accent/10 text-accent-text shrink-0">{{ __('Abgabetermin') }}</span>
                                                @endif
                                            </div>
                                            @if($p->titel)<div class="truncate text-xs text-muted">{{ $p->titel }}</div>@endif
                                        </td>
                                        @if($mitArt)<td class="truncate">{{ $p->pruefungsart ?: '–' }}</td>@endif
                                        @if($mitRaum)<td class="truncate">{{ $p->raum ?: '–' }}</td>@endif
                                        <td>
                                            <span class="{{ $pillBasis }} {{ $status['klasse'] }}">{{ $status['label'] }}</span>
                                        </td>
                                        @if($abgabeMoeglich)
                                            <td class="whitespace-nowrap text-right">
                                                @if($p->istAbgabe())
                                                    <a href="{{ route($bereich.'.exams.index', array_merge($filter, ['bearbeiten' => $p->pruefung_id])) }}"
                                                       class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Bearbeiten') }}</a>
                                                    <form method="POST" action="{{ route($bereich.'.exams.destroy', $p->pruefung_id) }}" class="inline"
                                                          data-bestaetigen="{{ __('Abgabetermin löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                        @csrf @method('DELETE')
                                                        <input type="hidden" name="lernender_id" value="{{ $p->lernender_id }}">
                                                        <button :disabled="loading" aria-label="{{ __('Löschen') }}" title="{{ __('Löschen') }}"
                                                                class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr np-knopf-klein"><x-symbol name="trash" /></button>
                                                    </form>
                                                @endif
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @empty
                <div class="np-karte px-5 py-10 text-center text-sm text-muted">
                    {{ __('Keine Prüfungstermine für die aktuelle Auswahl.') }}
                </div>
            @endforelse

        </div>
    </div>

    @if($abgabeMoeglich)
        <x-drawer name="abgabetermin" :offen="$bearbeiten || request()->has('planen') || old('_drawer') === 'abgabetermin'" :titel="$bearbeiten ? __('Abgabetermin bearbeiten') : __('Abgabetermin erfassen')">
            @include('verwaltung.pruefungen._abgabe_form')
        </x-drawer>
    @endif
</x-app-layout>
