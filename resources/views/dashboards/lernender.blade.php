<x-app-layout>
    <x-slot name="title">{{ __('Übersicht') }}</x-slot>
    @php
        $a = $auswertung;
        $delta = $stand->delta();
        $skala = \App\Support\NotenSkala::class;
        $meta = $lehrzeit ? implode(' · ', array_filter([
            $lehrzeit['beruf'],
            $lehrzeit['lehrjahr'] ? __(':jahr. Lehrjahr', ['jahr' => $lehrzeit['lehrjahr']]) : null,
            $lehrzeit['tage'] > 0 ? __('noch :tage Tage', ['tage' => $lehrzeit['tage']]) : __('Lehrzeit abgeschlossen'),
        ])) : null;
        $anzahlBalken = max(count($balken['semester']['labels']), count($balken['lehrzeit']['labels']));
        $desktop = "window.matchMedia('(min-width: 1024px)').matches";
    @endphp
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Hallo :name', ['name' => auth()->user()->vorname]) }}" :untertitel="$meta">
            <x-slot:aktionen>
                <a href="{{ route('learner.exams.index', ['planen' => 1]) }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Prüfung planen') }}</a>
                <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none" aria-hidden="true">+</span> {{ __('Note') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-4 px-4 sm:px-6 lg:grid-flow-row-dense lg:grid-cols-12 lg:px-8">

            {{-- Stand: Heldenzahl, Bullet Graph, Semester, Kategorien --}}
            <x-karte titel="{{ __('Stand') }}" class="lg:col-span-8">
                @if($a->gesamtNote !== null)
                    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-3">
                        <div class="flex items-baseline gap-3">
                            <x-note :wert="$a->gesamtNote" variante="hero" :stellen="1" class="text-display leading-none" />
                            <span class="text-sm text-muted">{{ __('Gesamtschnitt') }}</span>
                        </div>
                        @if(count(array_filter($stand->verlauf, fn ($v) => $v !== null)) > 1)
                            <x-sparkline :werte="$stand->verlauf" :breite="120" :hoehe="36" :zahl="false" :label="__('Semesterschnitte')" />
                        @endif
                    </div>

                    <x-bullet class="mt-5" :wert="$a->gesamtNote" :ziel="$zielGesamt" :grenzen="$grenzen" />

                    @if($stand->semesterId)
                        <p class="mt-3 flex flex-wrap items-baseline gap-x-2 text-sm">
                            <span class="text-muted">{{ __('Semester :name', ['name' => $a->konfiguration->semesterName($stand->semesterId)]) }}</span>
                            <x-note :wert="$stand->semesterNote" :stellen="1" />
                            @if($delta !== null && $delta != 0)
                                <span class="text-xs tabular-nums text-muted">{{ $delta > 0 ? '▲' : '▼' }} {{ $skala::format(abs($delta), 1) }}</span>
                            @endif
                        </p>
                    @endif

                    @if($kategorien)
                        <ul class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-border pt-4 text-sm">
                            @foreach($kategorien as $kat)
                                <li>
                                    <a href="{{ route('learner.grades.index', ['kategorie_id' => $kat['id']]) }}"
                                       class="inline-flex min-h-9 items-center gap-2 rounded-md underline-offset-2 hover:underline">
                                        <span class="text-muted">{{ $kat['name'] }}</span>
                                        <x-note :wert="$kat['note']" :stellen="1" />
                                        <x-sparkline :werte="$kat['verlauf']" :breite="48" :hoehe="18" :zahl="false" :label="__('Verlauf :name', ['name' => $kat['name']])" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="flex items-center gap-3 text-sm text-muted">
                        {{ __('Noch keine Noten') }}
                        <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                           class="text-accent-text underline-offset-2 hover:underline">{{ __('Erste Note erfassen') }}</a>
                    </p>
                @endif
            </x-karte>

            {{-- Als Nächstes: Überfälliges oben, dann Hinweise, Prüfungen, fehlende Module --}}
            @php $spalte = collect($alsNaechstes)->contains(fn ($t) => $t['datum'] !== null) ? 'w-10' : 'w-2'; @endphp
            <x-karte titel="{{ __('Als Nächstes') }}" class="lg:col-span-4 lg:self-start" :polster="false" :link="route('learner.exams.index')" :link-text="__('Agenda')">
                @if($alsNaechstes)
                    <x-slot:aktionen><span class="text-xs tabular-nums text-muted">{{ count($alsNaechstes) }}</span></x-slot:aktionen>
                @endif
                @if($alsNaechstes)
                    <ul class="divide-y divide-border">
                        @foreach($alsNaechstes as $t)
                            <li>
                                <a href="{{ $t['link'] }}" class="flex min-h-12 items-center gap-3 px-5 py-2 transition-colors duration-100 hover:bg-surface-2/60">
                                    @if($t['datum'])
                                        <span class="{{ $spalte }} shrink-0 text-xs tabular-nums text-muted">{{ $t['datum']->format('d.m.') }}</span>
                                    @else
                                        <span class="flex {{ $spalte }} shrink-0" aria-hidden="true">
                                            <span @class(['size-2 rounded-full', 'bg-note-ungenuegend' => $t['ton'] === 'rot', 'bg-note-knapp' => $t['ton'] === 'gelb', 'bg-accent' => $t['ton'] === 'accent', 'bg-muted/40' => $t['ton'] === 'neutral'])></span>
                                        </span>
                                    @endif
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm text-text">{{ $t['text'] }}</span>
                                        @if($t['detail'])<span class="block truncate text-xs text-muted">{{ $t['detail'] }}</span>@endif
                                    </span>
                                    @if($t['rechts'])<span class="shrink-0 text-xs tabular-nums text-muted">{{ $t['rechts'] }}</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="flex items-center gap-3 px-5 pb-4 text-sm text-muted">
                        {{ __('Nichts offen') }}
                        <a href="{{ route('learner.exams.index', ['planen' => 1]) }}" class="text-accent-text underline-offset-2 hover:underline">{{ __('Prüfung planen') }}</a>
                    </p>
                @endif
            </x-karte>

            {{-- Wo stehe ich pro Fach und Modul --}}
            @if($anzahlBalken > 0)
                <x-karte titel="{{ __('Wo stehe ich') }}" class="lg:col-span-8"
                         x-data="{ modus: {{ \Illuminate\Support\Js::from(count($balken['semester']['labels']) ? 'semester' : 'lehrzeit') }}, d: {{ \Illuminate\Support\Js::from($balken) }}, g: {{ \Illuminate\Support\Js::from($grenzen) }} }">
                    <x-slot:aktionen>
                        <div class="inline-flex rounded-lg bg-surface-2 p-0.5 text-xs" role="radiogroup" x-radiogroup aria-label="{{ __('Zeitraum') }}">
                            <button type="button" role="radio" :aria-checked="modus === 'semester'" @click="modus = 'semester'" x-show="d.semester.labels.length"
                                    class="h-8 whitespace-nowrap rounded-md px-2.5 text-muted transition-colors duration-150 aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs" x-text="d.semester.name"></button>
                            <button type="button" role="radio" :aria-checked="modus === 'lehrzeit'" @click="modus = 'lehrzeit'"
                                    class="h-8 whitespace-nowrap rounded-md px-2.5 text-muted transition-colors duration-150 aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs">{{ __('Lehrzeit') }}</button>
                        </div>
                    </x-slot:aktionen>
                    <div x-data="npChart('balken')" :style="`height: ${Math.max(120, d[modus].labels.length * 28 + 60)}px`"
                         x-effect="zeichne({ labels: d[modus].labels, werte: d[modus].werte, grenzen: g })">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('Zeugnisnoten je Fach und Modul, schwächste zuerst') }}"></canvas>
                    </div>
                </x-karte>
            @endif

            {{-- Ziele: nur wenn gesetzt --}}
            @if($ziele)
                <x-karte titel="{{ __('Ziele') }}" class="lg:col-span-4" :link="route('learner.grades.calculator')" :link-text="__('Rechner')" :polster="false">
                    <ul class="divide-y divide-border">
                        @foreach($ziele as $z)
                            @php
                                $l = $z['loesung'];
                                $erreicht = $z['aktuell'] !== null && $z['aktuell'] >= $z['zielwert'] - 1e-9;
                            @endphp
                            <li>
                                <a href="{{ $z['link'] }}" class="block px-5 py-3 transition-colors duration-100 hover:bg-surface-2/60">
                                    <span class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="truncate text-text">{{ $z['label'] }} ≥ {{ $skala::format($z['zielwert'], 1) }}</span>
                                        <x-note :wert="$z['aktuell']" :stellen="1" class="shrink-0" />
                                    </span>
                                    <x-bullet class="mt-2" :wert="$z['aktuell']" :ziel="$z['zielwert']" :grenzen="$grenzen" :label="$z['label']" :skala="false" />
                                    <span class="mt-1.5 block text-xs text-muted">
                                        @switch($l['status'])
                                            @case('benoetigt')
                                                {{ $l['unbekannte'] === 1 ? __('Nötig in der offenen Prüfung:') : __('Nötig in den :anzahl offenen Prüfungen:', ['anzahl' => $l['unbekannte']]) }}
                                                <span class="font-semibold tabular-nums {{ $skala::bedarf($l['note']) }}">{{ $skala::format($l['note'], 2) }}</span>
                                                @break
                                            @case('erreicht')
                                                {{ __('Gesichert') }}
                                                @break
                                            @case('unerreichbar')
                                                {{ __('höchstens :note erreichbar', ['note' => $skala::format($l['maximum'], 1)]) }}
                                                @break
                                            @default
                                                {{ $erreicht ? __('Erreicht') : __('Keine offenen Prüfungen geplant') }}
                                        @endswitch
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </x-karte>
            @endif

            {{-- Verlauf: mobil zugeklappt --}}
            @if(count($verlauf['labels']) > 0)
                <details open x-data="{ modus: 'kategorien', fach: 0, d: {{ \Illuminate\Support\Js::from($verlauf) }} }" x-init="$el.open = {{ $desktop }}"
                         class="group relative rounded-xl border border-border bg-card lg:col-span-8">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-5 py-2 lg:cursor-default"
                             @click="if ({{ $desktop }}) $event.preventDefault()">
                        <h2 class="text-sm font-semibold text-text">{{ __('Verlauf') }}</h2>
                        <svg class="size-4 text-muted transition-transform duration-200 group-open:rotate-180 lg:hidden" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.27a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd"/></svg>
                    </summary>
                    <div class="px-5 pb-5">
                        <div class="mb-2 flex flex-wrap items-center justify-end gap-2 lg:absolute lg:right-5 lg:top-2 lg:mb-0">
                            <div class="inline-flex rounded-lg bg-surface-2 p-0.5 text-xs" role="radiogroup" x-radiogroup aria-label="{{ __('Ebene') }}">
                                <button type="button" role="radio" :aria-checked="modus === 'kategorien'" @click="modus = 'kategorien'"
                                        class="h-8 whitespace-nowrap rounded-md px-2.5 text-muted transition-colors duration-150 aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs">{{ __('Kategorien') }}</button>
                                <button type="button" role="radio" :aria-checked="modus === 'fach'" @click="modus = 'fach'" x-show="d.faecher.length"
                                        class="h-8 whitespace-nowrap rounded-md px-2.5 text-muted transition-colors duration-150 aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs">{{ __('Fach') }}</button>
                            </div>
                            <select x-show="modus === 'fach'" x-model.number="fach" class="h-8 rounded-lg border border-border-strong/70 bg-input py-0 pl-2 pr-7 text-xs text-text" aria-label="{{ __('Fach') }}">
                                <template x-for="(f, i) in d.faecher" :key="i"><option :value="i" x-text="f.name"></option></template>
                            </select>
                        </div>
                        <div class="h-64" x-data="npChart('verlauf')"
                             x-effect="zeichne(modus === 'fach' && d.faecher[fach]
                                ? { labels: d.labels, grenze: d.grenze, serien: [{ name: d.faecher[fach].name, werte: d.faecher[fach].werte, farbe: '--accent', dick: true }] }
                                : { labels: d.labels, grenze: d.grenze, serien: d.serien })">
                            <canvas x-ref="canvas" role="img" aria-label="{{ __('Notenverlauf je Semester') }}"></canvas>
                        </div>
                    </div>
                </details>
            @endif

            {{-- Letzte Noten: mobil zugeklappt --}}
            @if($letzteNoten->isNotEmpty())
                <details open x-init="$el.open = {{ $desktop }}" class="group rounded-xl border border-border bg-card lg:col-span-4">
                    <summary class="flex min-h-12 cursor-pointer list-none items-center justify-between gap-3 px-5 py-2 lg:cursor-default"
                             @click="if ({{ $desktop }}) $event.preventDefault()">
                        <h2 class="text-sm font-semibold text-text">{{ __('Letzte Noten') }}</h2>
                        <span class="flex items-center gap-3">
                            <a href="{{ route('learner.grades.index') }}" class="hidden whitespace-nowrap text-xs text-accent-text underline-offset-2 hover:underline lg:inline">{{ __('Alle') }}</a>
                            <svg class="size-4 text-muted transition-transform duration-200 group-open:rotate-180 lg:hidden" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.24a.75.75 0 0 1-1.06 0L5.21 8.27a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd"/></svg>
                        </span>
                    </summary>
                    <ul class="divide-y divide-border border-t border-border">
                        @foreach($letzteNoten as $n)
                            <li>
                                <a href="{{ route('learner.grades.index', ['_open' => $n->note_id]) }}" class="flex min-h-12 items-center justify-between gap-3 px-5 py-2 transition-colors duration-100 hover:bg-surface-2/60">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm text-text">{{ $n->fach?->name ?? trim(($n->modulBelegung?->modul?->modul_nummer ?? '').' '.($n->modulBelegung?->modul?->titel ?? '')) }}</span>
                                        <span class="block truncate text-xs text-muted">{{ $n->pruefungsdatum->format('d.m.Y') }}@if($n->titel) · {{ $n->titel }}@endif</span>
                                    </span>
                                    <x-note :wert="$n->note_wert" variante="badge" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    </div>

    {{-- Erfassen im Drawer wie auf /grades --}}
    <x-noten-drawer :fehler="$drawerFehler" />
</x-app-layout>
