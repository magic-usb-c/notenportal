{{-- Abschluss (QV, Berufsmaturität): je Notenbaum links das Ergebnis – Note, wie viel davon schon erfasst ist und jede
     Bestehensbedingung mit ihrem Stand –, rechts der Aufbau mit Anteil und Note. Positionen von Hand werden direkt in
     der Tabelle erfasst; «Speichern» steht in der Symbolleiste. Lernende und Verwaltung teilen die Ansicht. --}}
@use('App\Services\Auswertung\Notenbaum\BaumErgebnis')
@use('App\Services\Auswertung\Notenbaum\Bedingung')
@use('App\Services\Auswertung\Notenbaum\Grund')
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
    $regel = fn (Bedingung $b) => match ($b->regel) {
        Grund::FALLNOTE => __('mindestens :note', ['note' => NotenSkala::format($b->grenze, 1)]),
        Grund::UNGENUEGEND => __('höchstens :anzahl ungenügend', ['anzahl' => (int) $b->grenze]),
        Grund::MINUSPUNKTE => __('höchstens :anzahl Minuspunkte', ['anzahl' => NotenSkala::format($b->grenze, 1)]),
    };
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
                <x-leer symbol="academic-cap" :titel="__('Noch keine Abschlussrechnung')"
                        :text="__('Für diese Ausbildung ist noch keine Gewichtung bis zur Gesamtnote hinterlegt.')">
                    @if(auth()->user()->hasRole('Admin'))
                        <a href="{{ route('admin.master-data.grade-trees.index') }}" class="np-knopf np-knopf-sekundaer">{{ __('Notenbaum laden') }}</a>
                    @endif
                </x-leer>
            @else
                <form id="abschluss" method="POST" action="{{ $speichernUrl }}" class="flex flex-col gap-10">
                    @csrf
                    @method('PUT')

                    @foreach($ergebnisse as $i => $e)
                        @php
                            $wurzel = $e->wurzel();
                            $erfasst = (int) round($e->erfasst() * 100);
                        @endphp
                        {{-- Links fest neben dem Aufbau: bei langen Bäumen (Berufsmaturität) bleibt das Ergebnis im Blick; die Tabelle wächst bis zur Inhaltskante, gedeckelt wie die Agenda --}}
                        <section class="grid max-w-[100rem] grid-cols-[26rem_minmax(0,1fr)] items-start gap-5" aria-labelledby="baum-{{ $e->baum->id }}">
                            <div class="np-karte sticky top-[calc(var(--np-symbolleiste-hoehe)+1rem)] flex flex-col p-5">
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

                                <div class="mt-4 flex items-baseline gap-3">
                                    @if($wurzel->note !== null)
                                        <x-note :wert="$wurzel->note" variante="hero" :stellen="1" @class(['leading-none', 'text-display' => $i === 0, 'text-3xl' => $i > 0]) />
                                    @else
                                        <span @class(['font-semibold leading-none text-muted', 'text-display' => $i === 0, 'text-3xl' => $i > 0])>–</span>
                                    @endif
                                    <span class="text-sm text-muted">{{ $wurzel->vollstaendig ? __('Gesamtnote') : __('Prognose') }}</span>
                                </div>

                                @unless($wurzel->vollstaendig)
                                    {{-- Worauf die Prognose beruht: Anteil der Gesamtnote mit erfasstem Wert --}}
                                    <div class="mt-4">
                                        <div class="flex items-baseline justify-between text-xs">
                                            <span class="text-muted">{{ __('Erfasst') }}</span>
                                            <span class="tabular-nums text-text">{{ __(':anteil % der Gesamtnote', ['anteil' => $erfasst]) }}</span>
                                        </div>
                                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-fill" aria-hidden="true">
                                            <div class="h-full rounded-full bg-accent" style="width: {{ $erfasst }}%"></div>
                                        </div>
                                    </div>
                                @endunless

                                @if($e->bedingungen !== [])
                                    <h3 class="mt-6 text-sm font-semibold text-text">{{ __('Bestehen') }}</h3>
                                    <ul class="mt-1">
                                        @foreach($e->bedingungen as $b)
                                            @php
                                                // Fest ist ein Stand, wenn er auf einer Prüfung beruht oder alles erfasst ist
                                                $fest = $b->definitiv || $wurzel->vollstaendig;
                                                [$symbol, $farbe, $stand] = match (true) {
                                                    $b->stand === Bedingung::OFFEN => ['circle', 'text-faint', __('offen')],
                                                    $b->stand === Bedingung::ERFUELLT && $fest => ['check-circle', 'text-note-gut', __('erfüllt')],
                                                    $b->stand === Bedingung::ERFUELLT => ['check-circle', 'text-muted', __('bisher erfüllt')],
                                                    $fest => ['x-circle', 'text-note-ungenuegend', __('nicht erfüllt')],
                                                    default => ['exclamation-triangle', 'text-note-knapp', __('gefährdet')],
                                                };
                                                $wert = match (true) {
                                                    $b->wert === null => null,
                                                    $b->regel === Grund::FALLNOTE => null,
                                                    $b->regel === Grund::UNGENUEGEND => __(':wert von :grenze', ['wert' => (int) $b->wert, 'grenze' => (int) $b->grenze]),
                                                    default => __(':wert von :grenze', ['wert' => NotenSkala::format($b->wert, 1), 'grenze' => NotenSkala::format($b->grenze, 1)]),
                                                };
                                            @endphp
                                            <li class="grid grid-cols-[1.25rem_minmax(0,1fr)_auto] items-center gap-x-3 border-b border-border py-2.5 last:border-0">
                                                <x-symbol :name="$symbol" class="size-5 {{ $farbe }}" />
                                                <div class="min-w-0">
                                                    <div class="wrap-break-word text-sm text-text">{{ $b->code === $e->baum->wurzel->code ? __('Gesamtnote') : $b->name }}</div>
                                                    <div class="text-xs text-muted">{{ $regel($b) }}<span class="sr-only">, {{ $stand }}</span></div>
                                                </div>
                                                <div class="text-right text-sm tabular-nums">
                                                    @if($b->regel === Grund::FALLNOTE && $b->wert !== null)
                                                        <x-note :wert="$b->wert" :stellen="1" />
                                                    @elseif($wert !== null)
                                                        <span class="text-text">{{ $wert }}</span>
                                                    @else
                                                        <span class="text-muted">–</span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <div class="np-karte p-2">
                                <table class="np-tabelle table-fixed text-sm">
                                    <colgroup>
                                        <col>
                                        <col class="w-28">
                                        <col class="w-32">
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th scope="col">{{ __('Teil') }}</th>
                                            <th scope="col" class="text-right">{{ __('Anteil') }}</th>
                                            <th scope="col" class="text-right">{{ __('Note') }}</th>
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
                                            @endphp
                                            <tr>
                                                <td class="{{ $einzug[min($z['tiefe'], 4)] }}">
                                                    <div @class(['wrap-break-word text-text', 'font-medium' => $gruppe])>{{ $k->name }}</div>
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
