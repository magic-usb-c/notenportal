{{-- Abschluss (QV, Berufsmaturität): Ergebnis je Notenbaum, Positionen von Hand. Lernende und Verwaltung teilen die Ansicht. --}}
@use('App\Services\Auswertung\Notenbaum\BaumErgebnis')
@use('App\Services\Auswertung\Notenbaum\Knoten')
@use('App\Support\NotenSkala')
@php
    $name = $lernender ? trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname) : null;
    // Auf dem Handy halber Einzug, sonst frisst die Tiefe die Namensspalte
    $einzug = ['pl-4 sm:pl-5', 'pl-6 sm:pl-9', 'pl-8 sm:pl-13', 'pl-10 sm:pl-17', 'pl-12 sm:pl-21'];
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
        <x-seitenkopf :zurueck="$lernender ? route($bereich.'.learners.show', $lernender->lernender_id) : null" :titel="__('Abschluss')" :untertitel="$name" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            @if($ergebnisse === [])
                <p class="np-karte flex flex-wrap items-center gap-3 px-5 py-4 text-sm text-muted">
                    {{ __('Für diese Ausbildung ist noch keine Gewichtung bis zur Gesamtnote hinterlegt.') }}
                    @if(auth()->user()->hasRole('Admin'))
                        <a href="{{ route('admin.master-data.grade-trees.index') }}" class="inline-flex min-h-6 items-center text-accent-text underline-offset-2 hover:underline">{{ __('Notenbaum laden') }}</a>
                    @endif
                </p>
            @else
                <form method="POST" action="{{ $speichernUrl }}" x-data="{ loading: false }" @submit="loading = true"
                      @class(['grid grid-cols-1 items-start gap-5', 'max-w-4xl' => count($ergebnisse) === 1, 'xl:grid-cols-2' => count($ergebnisse) > 1])>
                    @csrf
                    @method('PUT')

                    @foreach($ergebnisse as $i => $e)
                        @php
                            $wurzel = $e->wurzel();
                            $definitiv = collect($e->gruende)->contains(fn ($g) => $g->definitiv);
                        @endphp
                        <section class="np-karte" aria-labelledby="baum-{{ $e->baum->id }}">
                            <header class="flex flex-wrap items-start justify-between gap-4 px-5 pt-5">
                                <div class="min-w-0">
                                    <h2 id="baum-{{ $e->baum->id }}" class="text-sm font-semibold text-text">{{ $e->baum->name }}</h2>
                                    <div class="mt-2 flex items-baseline gap-3">
                                        @if($wurzel->note !== null)
                                            <x-note :wert="$wurzel->note" variante="hero" :stellen="1" @class(['leading-none', 'text-display' => $i === 0, 'text-2xl' => $i > 0]) />
                                        @else
                                            <span @class(['font-semibold leading-none text-muted', 'text-display' => $i === 0, 'text-2xl' => $i > 0])>–</span>
                                        @endif
                                        <span class="text-sm text-muted">{{ $wurzel->vollstaendig ? __('Gesamtnote') : __('Prognose') }}</span>
                                    </div>
                                    @if($r = $bestehen($wurzel->knoten))
                                        <p class="mt-2 text-xs text-muted">{{ implode(' · ', $r) }}</p>
                                    @endif
                                </div>
                                @switch($e->status)
                                    @case(BaumErgebnis::BESTANDEN)
                                        <span class="inline-flex h-7 items-center rounded-md bg-note-gut/14 px-2.5 text-sm font-medium text-note-gut">{{ __('Bestanden') }}</span>
                                        @break
                                    @case(BaumErgebnis::NICHT_BESTANDEN)
                                        <span class="inline-flex h-7 items-center gap-1.5 rounded-md bg-note-ungenuegend/14 px-2.5 text-sm font-medium text-note-ungenuegend"><span aria-hidden="true">▼</span>{{ __('Nicht bestanden') }}</span>
                                        @break
                                    @default
                                        <span class="inline-flex h-7 items-center rounded-md bg-surface-2 px-2.5 text-sm font-medium text-muted">{{ __('Offen') }}</span>
                                @endswitch
                            </header>

                            @if($e->gruende)
                                <ul class="mx-5 mt-4 flex flex-col gap-1.5 rounded-lg bg-fill-2 px-4 py-3 text-sm">
                                    @foreach($e->gruende as $g)
                                        <li class="flex items-center gap-2">
                                            <span @class(['size-1.5 shrink-0 rounded-full', 'bg-note-ungenuegend' => $g->definitiv || $wurzel->vollstaendig, 'bg-note-knapp' => ! $g->definitiv && ! $wurzel->vollstaendig]) aria-hidden="true"></span>
                                            <span class="text-text">{{ $g->text() }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-4 overflow-x-auto border-t border-border">
                                <table class="np-tabelle text-sm">
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('Teil') }}</th>
                                            <th scope="col" class="hidden text-right sm:table-cell">{{ __('Anteil') }}</th>
                                            <th scope="col" class="w-20 text-right sm:w-32">{{ __('Note') }}</th>
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
                                                    <div @class(['text-text wrap-break-word', 'font-medium' => $gruppe])>{{ $k->name }}</div>
                                                    {{-- Auf dem Handy steht der Anteil hier statt in einer eigenen Spalte --}}
                                                    <div class="text-xs text-muted wrap-break-word"><span class="sm:hidden">{{ $anteil }}@if($r) · @endif</span>{{ $r ? implode(' · ', $r) : '' }}</div>
                                                </td>
                                                <td class="hidden whitespace-nowrap text-right text-muted sm:table-cell">{{ $anteil }}</td>
                                                <td class="text-right">
                                                    @if($k->entfaellt)
                                                        <span class="text-muted">–</span>
                                                    @elseif($k->typ === Knoten::MANUELL)
                                                        <label for="wert-{{ $k->id }}" class="sr-only">{{ $k->name }}</label>
                                                        <input id="wert-{{ $k->id }}" name="werte[{{ $k->id }}]" inputmode="decimal" autocomplete="off"
                                                               value="{{ old($feld, $position ? NotenSkala::format($position->note_wert) : '') }}"
                                                               @error($feld) aria-invalid="true" aria-describedby="wert-{{ $k->id }}-fehler" @enderror
                                                               class="np-feld np-feld-klein w-16 px-2 text-right tabular-nums"
                                                               placeholder="–">
                                                        @error($feld)<p id="wert-{{ $k->id }}-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
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

                    @if($hatManuell)
                        <div @class(['flex justify-end', 'xl:col-span-2' => count($ergebnisse) > 1])>
                            <button type="submit" :disabled="loading"
                                    class="np-knopf np-knopf-primaer">
                                {{ __('Speichern') }}
                            </button>
                        </div>
                    @endif
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
