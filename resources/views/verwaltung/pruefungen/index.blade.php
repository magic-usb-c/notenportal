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
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

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

                    {{-- Mobil: Karten --}}
                    <div class="md:hidden divide-y divide-border">
                        @foreach($g['zeilen'] as $z)
                            @php [$p, $status] = [$z['pruefung'], $z['status']]; @endphp
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-x-1.5 gap-y-1">
                                            <span class="font-medium text-text [overflow-wrap:anywhere]">{{ trim($p->bezeichnung().($p->titel ? ' – '.$p->titel : '')) }}</span>
                                            @if($p->istAbgabe())
                                                <span class="{{ $pillBasis }} bg-accent/10 text-accent-text shrink-0">{{ __('Abgabetermin') }}</span>
                                            @endif
                                        </div>
                                        <a href="{{ route($bereich.'.learners.show', $p->lernender_id) }}" class="text-xs text-accent-text hover:underline underline-offset-2">
                                            {{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }}
                                        </a>
                                    </div>
                                    <span class="{{ $pillBasis }} {{ $status['klasse'] }} shrink-0">{{ $status['label'] }}</span>
                                </div>
                                <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted">
                                    <span class="tabular-nums">{{ $p->datum->format('d.m.Y') }}{{ $p->uhrzeit ? ' · '.substr((string) $p->uhrzeit, 0, 5) : '' }}</span>
                                    @if($p->pruefungsart)<span>{{ $p->pruefungsart }}</span>@endif
                                    @if($p->raum)<span>{{ $p->raum }}</span>@endif
                                </div>
                                @if($abgabeMoeglich && $p->istAbgabe())
                                    <div class="mt-2 flex items-center gap-3 text-xs">
                                        <a href="{{ route($bereich.'.exams.index', array_merge($filter, ['bearbeiten' => $p->pruefung_id])) }}"
                                           class="text-accent-text hover:underline underline-offset-2">{{ __('Bearbeiten') }}</a>
                                        <form method="POST" action="{{ route($bereich.'.exams.destroy', $p->pruefung_id) }}"
                                              data-bestaetigen="{{ __('Abgabetermin löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="lernender_id" value="{{ $p->lernender_id }}">
                                            <button :disabled="loading" class="text-muted hover:text-note-ungenuegend disabled:opacity-50">{{ __('Löschen') }}</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    {{-- Desktop: Tabelle --}}
                    <div class="hidden overflow-x-auto px-2 pb-2 md:block">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="whitespace-nowrap">{{ __('Datum') }}</th>
                                    <th scope="col">{{ __('Lernende/r') }}</th>
                                    <th scope="col">{{ __('Fach / Modul') }}</th>
                                    <th scope="col">{{ __('Art') }}</th>
                                    <th scope="col">{{ __('Raum') }}</th>
                                    <th scope="col">{{ __('Status') }}</th>
                                    @if($abgabeMoeglich)
                                        <th scope="col" class="w-24"><span class="sr-only">{{ __('Aktionen') }}</span></th>
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
                                        <td>
                                            <a href="{{ route($bereich.'.learners.show', $p->lernender_id) }}" class="text-text hover:text-accent-text">
                                                {{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }}
                                            </a>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-1.5">
                                                <span class="truncate text-text">{{ $p->bezeichnung() }}</span>
                                                @if($p->istAbgabe())
                                                    <span class="{{ $pillBasis }} bg-accent/10 text-accent-text shrink-0">{{ __('Abgabetermin') }}</span>
                                                @endif
                                            </div>
                                            @if($p->titel)<div class="truncate text-xs text-muted">{{ $p->titel }}</div>@endif
                                        </td>
                                        <td>{{ $p->pruefungsart ?: '–' }}</td>
                                        <td>{{ $p->raum ?: '–' }}</td>
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
