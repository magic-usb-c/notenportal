<x-app-layout>
    <x-slot name="title">{{ __('Prüfungstermine') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Prüfungstermine')" :zaehler="$anzahl">
            <x-slot:aktionen>
                <a href="{{ route('settings.calendar') }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text whitespace-nowrap">
                    {{ __('Kalender-Abo') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $auswahl = 'h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-48';
        $aktiveFilter = ($filter['lernender_id'] ? 1 : 0) + ($filter['zeitraum'] !== 'alle' ? 1 : 0);
        $pillBasis = 'inline-flex px-1.5 py-0.5 rounded-full text-xs whitespace-nowrap';
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

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

            @forelse($gruppen as $g)
                <section class="rounded-xl border border-border bg-card overflow-hidden">
                    <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">
                        {{ $g['label'] }} <span class="ml-1 text-sm text-muted tabular-nums">{{ $g['zeilen']->count() }}</span>
                    </h3>

                    {{-- Mobil: Karten --}}
                    <div class="md:hidden divide-y divide-border">
                        @foreach($g['zeilen'] as $z)
                            @php [$p, $status] = [$z['pruefung'], $z['status']]; @endphp
                            <div class="p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-text">{{ trim($p->bezeichnung().($p->titel ? ' – '.$p->titel : '')) }}</div>
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
                            </div>
                        @endforeach
                    </div>

                    {{-- Desktop: Tabelle --}}
                    <div class="hidden md:block overflow-x-auto">
                        <table class="w-full text-sm tabular-nums">
                            <thead>
                                <tr class="border-b border-border">
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Datum') }}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">{{ __('Lernende/r') }}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">{{ __('Fach / Modul') }}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">{{ __('Art') }}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">{{ __('Raum') }}</th>
                                    <th scope="col" class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($g['zeilen'] as $z)
                                    @php [$p, $status] = [$z['pruefung'], $z['status']]; @endphp
                                    <tr class="border-b border-border last:border-0 hover:bg-surface-2/60">
                                        <td class="h-11 px-3 whitespace-nowrap">
                                            {{ $p->datum->format('d.m.Y') }}
                                            @if($p->uhrzeit)<span class="block text-xs text-muted">{{ substr((string) $p->uhrzeit, 0, 5) }}</span>@endif
                                        </td>
                                        <td class="h-11 px-3">
                                            <a href="{{ route($bereich.'.learners.show', $p->lernender_id) }}" class="text-text hover:text-accent-text">
                                                {{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }}
                                            </a>
                                        </td>
                                        <td class="h-11 px-3">
                                            <div class="truncate text-text">{{ $p->bezeichnung() }}</div>
                                            @if($p->titel)<div class="truncate text-xs text-muted">{{ $p->titel }}</div>@endif
                                        </td>
                                        <td class="h-11 px-3">{{ $p->pruefungsart ?: '–' }}</td>
                                        <td class="h-11 px-3">{{ $p->raum ?: '–' }}</td>
                                        <td class="h-11 px-3">
                                            <span class="{{ $pillBasis }} {{ $status['klasse'] }}">{{ $status['label'] }}</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @empty
                <div class="rounded-xl border border-border bg-card px-5 py-10 text-center text-sm text-muted">
                    {{ __('Keine Prüfungstermine für die aktuelle Auswahl.') }}
                </div>
            @endforelse

        </div>

    </div>
</x-app-layout>
