{{-- Abschluss (QV, Berufsmaturität): je Notenbaum links das Ergebnis mit Status und Gründen, rechts der Aufbau mit
     Anteil und Note. Positionen von Hand werden direkt in der Tabelle erfasst; «Speichern» steht in der Symbolleiste.
     Lernende und Verwaltung teilen die Ansicht. --}}
@use('App\Services\Auswertung\Notenbaum\BaumErgebnis')
@use('App\Services\Auswertung\Notenbaum\Knoten')
@use('App\Support\NotenSkala')
@php
    $name = $lernender ? trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname) : null;
    $einzug = ['pl-3', 'pl-8', 'pl-13', 'pl-18', 'pl-23'];
    $prozent = fn (float $anteil) => rtrim(rtrim(number_format($anteil * 100, 1, '.', ''), '0'), '.')."\u{00A0}%";

    // Baum flach in Anzeige-Reihenfolge, mit Tiefe und Anteil am Elternknoten
    $zeilen = function ($ergebnis) {
        $out = [];
        $gehe = function ($knoten, int $tiefe) use (&$gehe, &$out) {
            $summe = array_sum(array_map(fn ($k) => $k->knoten->zaehlt ? $k->knoten->gewicht : 0, $knoten->kinder));
            foreach ($knoten->kinder as $kind) {
                $out[] = ['e' => $kind, 'tiefe' => $tiefe, 'anteil' => $kind->knoten->zaehlt && $summe > 0 ? $kind->knoten->gewicht / $summe : null];
                if (! $kind->knoten->entfaellt) {
                    $gehe($kind, $tiefe + 1);
                }
            }
        };
        $gehe($ergebnis->wurzel(), 0);

        return $out;
    };
    $regeln = fn (Knoten $k) => array_filter([
        $k->fallnote !== null ? __('mindestens :note', ['note' => NotenSkala::format($k->fallnote, 1)]) : null,
        $k->maxUngenuegend !== null ? __('höchstens :anzahl ungenügend', ['anzahl' => $k->maxUngenuegend]) : null,
        $k->maxMinuspunkte !== null ? __('höchstens :anzahl Minuspunkte', ['anzahl' => NotenSkala::format($k->maxMinuspunkte, 1)]) : null,
    ]);
    // Bestehensregel der Wurzel als Satz: «Bestanden ab 4.0 · höchstens 2 ungenügend …»
    $bestehen = fn (Knoten $k) => array_filter([
        $k->fallnote !== null ? __('Bestanden ab :note', ['note' => NotenSkala::format($k->fallnote, 1)]) : null,
        $k->maxUngenuegend !== null ? __('höchstens :anzahl ungenügend', ['anzahl' => $k->maxUngenuegend]) : null,
        $k->maxMinuspunkte !== null ? __('höchstens :anzahl Minuspunkte', ['anzahl' => NotenSkala::format($k->maxMinuspunkte, 1)]) : null,
    ]);
    $hatManuell = collect($ergebnisse)->contains(fn ($e) => collect($e->baum->alle())->contains(fn ($k) => $k->typ === Knoten::MANUELL && ! $k->entfaellt));
@endphp
<x-app-layout>
    <x-slot name="title">{{ $name ? __('Abschluss').' · '.$name : __('Abschluss') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="$lernender ? route($bereich.'.learners.show', $lernender->lernender_id) : null" :titel="__('Abschluss')" :untertitel="$name">
            @if($ergebnisse !== [] && $hatManuell)
                <x-slot:aktionen>
                    <button type="submit" form="abschluss" class="np-knopf np-knopf-primaer"
                            x-data="{ loading: false }" :disabled="loading"
                            @submit.window="if ($event.target.id === 'abschluss' && ! $event.defaultPrevented) loading = true"
                            @pageshow.window="loading = false">
                        {{ __('Speichern') }}
                    </button>
                </x-slot:aktionen>
            @endif
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if($ergebnisse === [])
                <div class="np-karte">
                    <x-leer symbol="academic-cap" :titel="__('Noch keine Abschlussrechnung')"
                            :text="__('Für diese Ausbildung ist noch keine Gewichtung bis zur Gesamtnote hinterlegt.')">
                        @if(auth()->user()->hasRole('Admin'))
                            <a href="{{ route('admin.master-data.grade-trees.index') }}" class="np-knopf np-knopf-sekundaer">{{ __('Notenbaum laden') }}</a>
                        @endif
                    </x-leer>
                </div>
            @else
                <form id="abschluss" method="POST" action="{{ $speichernUrl }}" class="flex flex-col gap-8">
                    @csrf
                    @method('PUT')

                    @foreach($ergebnisse as $i => $e)
                        @php
                            $wurzel = $e->wurzel();
                        @endphp
                        <section class="grid grid-cols-12 items-start gap-5" aria-labelledby="baum-{{ $e->baum->id }}">
                            <div class="np-karte col-span-4 flex flex-col gap-4 p-5">
                                <div class="flex items-start justify-between gap-3">
                                    <h2 id="baum-{{ $e->baum->id }}" class="min-w-0 text-base font-semibold text-text">{{ $e->baum->name }}</h2>
                                    @switch($e->status)
                                        @case(BaumErgebnis::BESTANDEN)
                                            <span class="np-marke shrink-0 bg-note-gut/14 text-note-gut">{{ __('Bestanden') }}</span>
                                            @break
                                        @case(BaumErgebnis::NICHT_BESTANDEN)
                                            <span class="np-marke shrink-0 gap-1 bg-note-ungenuegend/14 text-note-ungenuegend"><span aria-hidden="true">▼</span>{{ __('Nicht bestanden') }}</span>
                                            @break
                                        @default
                                            <span class="np-marke shrink-0 text-muted">{{ __('Offen') }}</span>
                                    @endswitch
                                </div>

                                <div>
                                    <div class="flex items-baseline gap-3">
                                        @if($wurzel->note !== null)
                                            <x-note :wert="$wurzel->note" variante="hero" :stellen="1" @class(['leading-none', 'text-display' => $i === 0, 'text-2xl' => $i > 0]) />
                                        @else
                                            <span @class(['font-semibold leading-none text-muted', 'text-display' => $i === 0, 'text-2xl' => $i > 0])>–</span>
                                        @endif
                                        <span class="text-sm text-muted">{{ $wurzel->vollstaendig ? __('Gesamtnote') : __('Prognose') }}</span>
                                    </div>
                                    @if($r = $bestehen($wurzel->knoten))
                                        <p class="mt-2 text-sm text-muted">{{ implode(' · ', $r) }}</p>
                                    @endif
                                </div>

                                @if($e->gruende)
                                    <ul class="flex flex-col gap-1.5 rounded-lg bg-fill-2 px-4 py-3 text-sm">
                                        @foreach($e->gruende as $g)
                                            <li class="flex items-baseline gap-2">
                                                <span @class(['size-1.5 shrink-0 -translate-y-0.5 rounded-full', 'bg-note-ungenuegend' => $g->definitiv || $wurzel->vollstaendig, 'bg-note-knapp' => ! $g->definitiv && ! $wurzel->vollstaendig]) aria-hidden="true"></span>
                                                <span class="text-text">{{ $g->text() }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="np-karte col-span-8 p-2">
                                <table class="np-tabelle table-fixed text-sm">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('Teil') }}</th>
                                            <th scope="col" class="w-28 text-right">{{ __('Anteil') }}</th>
                                            <th scope="col" class="w-32 text-right">{{ __('Note') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($zeilen($e) as $z)
                                            @php
                                                $k = $z['e']->knoten;
                                                $gruppe = $k->typ === Knoten::GRUPPE;
                                                $feld = 'werte.'.$k->id;
                                                $position = $positionen[$k->id] ?? null;
                                                $anteil = $z['anteil'] !== null ? $prozent($z['anteil']) : ($k->entfaellt ? __('entfällt') : __('zählt nicht'));
                                                $r = $regeln($k);
                                            @endphp
                                            <tr>
                                                <td class="{{ $einzug[min($z['tiefe'], 4)] }}">
                                                    <div @class(['wrap-break-word text-text', 'font-medium' => $gruppe])>{{ $k->name }}</div>
                                                    @if($r)
                                                        <div class="wrap-break-word text-xs text-muted">{{ implode(' · ', $r) }}</div>
                                                    @endif
                                                    @error($feld)<p id="wert-{{ $k->id }}-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                                                </td>
                                                <td class="whitespace-nowrap text-right text-muted">{{ $anteil }}</td>
                                                <td class="text-right">
                                                    @if($k->entfaellt)
                                                        <span class="text-muted">–</span>
                                                    @elseif($k->typ === Knoten::MANUELL)
                                                        <label for="wert-{{ $k->id }}" class="sr-only">{{ $k->name }}</label>
                                                        <input id="wert-{{ $k->id }}" name="werte[{{ $k->id }}]" inputmode="decimal" autocomplete="off"
                                                               value="{{ old($feld, $position ? NotenSkala::format($position->note_wert) : '') }}"
                                                               @error($feld) aria-invalid="true" aria-describedby="wert-{{ $k->id }}-fehler" @enderror
                                                               class="np-feld np-feld-klein ml-auto w-18 px-2 text-right tabular-nums" placeholder="–">
                                                    @elseif($z['e']->note !== null)
                                                        <x-note :wert="$z['e']->note" :stellen="$k->rundung === 1.0 || $k->rundung === 0.5 ? null : 1" />
                                                    @else
                                                        <span class="text-muted">–</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @endforeach
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
