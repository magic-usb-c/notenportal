@php
    use App\Support\Betriebslogo;
    use App\Support\NotenSkala;
    use App\Support\Zahl;
    use Illuminate\Support\Carbon;

    $g = NotenSkala::grenzen();
    $stufe = fn ($n) => $n === null ? '' : ($n >= $g['gut'] ? 'n-gut' : ($n >= $g['genuegend'] ? 'n-ok' : ($n >= $g['kritisch'] ? 'n-knapp' : 'n-tief')));
    $datum = fn ($d, $format = 'd.m.Y') => $d ? Carbon::parse($d)->format($format) : '–';
    $logoUrl = Betriebslogo::url();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Notenblatt') }} – {{ $blatt['name'] }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Helvetica Neue', Arial, sans-serif; font-size: 10pt; color: #1a1a1a; background: #fff; padding: 16mm 18mm; max-width: 210mm; margin: 0 auto; }
        .leiste { display: flex; justify-content: space-between; margin-bottom: 18px; }
        .leiste a, .leiste button { font: inherit; font-size: 13px; padding: 8px 16px; border-radius: 10px; border: 1px solid #d1d5db; background: #f3f4f6; color: #374151; text-decoration: none; cursor: pointer; }
        .leiste button { background: #2563eb; border-color: #2563eb; color: #fff; font-weight: 600; }
        header { display: flex; justify-content: space-between; align-items: flex-end; border-bottom: 2px solid #1a1a1a; padding-bottom: 8px; margin-bottom: 14px; }
        header .titel { display: flex; align-items: center; gap: 10px; }
        header img.logo { height: 34px; width: auto; }
        h1 { font-size: 20pt; letter-spacing: -0.02em; }
        .sub { font-size: 12pt; margin-top: 2px; }
        .rechts { text-align: right; font-size: 9pt; color: #555; line-height: 1.5; }
        .eckdaten { display: grid; grid-template-columns: repeat(3, 1fr) auto; gap: 12px; align-items: center; background: #f5f5f5; border-radius: 8px; padding: 10px 14px; margin-bottom: 10px; }
        .eckdaten label { display: block; font-size: 7.5pt; color: #666; text-transform: uppercase; letter-spacing: 0.08em; }
        .eckdaten span { font-weight: 600; }
        .gesamt { text-align: right; }
        .gesamt strong { display: block; font-size: 22pt; line-height: 1; }
        .kats { display: flex; flex-wrap: wrap; gap: 6px 18px; font-size: 9pt; color: #444; margin-bottom: 18px; padding: 0 2px; }
        .kats strong { margin-left: 4px; }
        .semester { margin-bottom: 18px; }
        h2 { display: flex; justify-content: space-between; align-items: baseline; font-size: 11pt; background: #1a1a1a; color: #fff; padding: 5px 10px; border-radius: 5px; margin-bottom: 6px; break-after: avoid; }
        h2 small { font-size: 9pt; font-weight: 400; opacity: 0.8; }
        h2 strong { font-size: 11pt; margin-left: 6px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; break-inside: avoid; }
        th { text-align: left; font-size: 8.5pt; color: #444; background: #f0f0f0; border: 1px solid #ccc; padding: 4px 8px; }
        th .prom { font-weight: 400; margin-left: 8px; }
        th .prom.nein { color: #991b1b; font-weight: 600; }
        td { border: 1px solid #ddd; padding: 4px 8px; vertical-align: top; }
        td.el { width: 34%; font-weight: 600; }
        td.pr { font-size: 8.5pt; color: #444; }
        td.pr span { display: inline-block; margin-right: 10px; white-space: nowrap; }
        td.pr b { color: #1a1a1a; }
        td.pr .offen { color: #854d0e; }
        .r { text-align: right; width: 52px; }
        td.note { font-weight: 700; font-size: 11pt; }
        .n-gut { color: #14532d; }
        .n-ok { color: #166534; }
        .n-knapp { color: #854d0e; }
        .n-tief { color: #991b1b; }
        .leer { color: #666; margin: 20px 0; }
        .unterschriften { display: flex; gap: 40px; margin-top: 32px; break-inside: avoid; }
        .unterschriften div { flex: 1; border-bottom: 1px solid #1a1a1a; height: 36px; }
        .unterschriften p { font-size: 8pt; color: #666; margin-top: 4px; }
        footer { margin-top: 18px; font-size: 8pt; color: #666; border-top: 1px solid #ddd; padding-top: 6px; }
        @media print {
            body { padding: 0; max-width: none; }
            .leiste { display: none; }
            @page { size: A4; margin: 14mm 14mm 12mm 14mm; }
        }
        @media screen and (max-width: 480px) {
            body { padding: 4mm; font-size: 9pt; }
            .leiste { flex-wrap: wrap; gap: 8px; }
            header { flex-wrap: wrap; gap: 6px; }
            .rechts { text-align: left; }
            .eckdaten { grid-template-columns: 1fr 1fr; }
            .gesamt { grid-column: 1 / -1; text-align: left; }
            table { display: block; overflow-x: auto; }
            td.pr span { white-space: normal; margin-right: 6px; }
        }
    </style>
</head>
<body>
    <div class="leiste">
        <a href="{{ $zurueck }}">← {{ __('Zurück') }}</a>
        <button type="button" onclick="window.print()">{{ __('Drucken / PDF') }}</button>
    </div>

    <header>
        <div class="titel">
            @if($logoUrl)<img src="{{ $logoUrl }}" alt="" class="logo">@endif
            <div>
                <h1>{{ __('Notenblatt') }}</h1>
                <div class="sub">{{ $blatt['name'] }}</div>
            </div>
        </div>
        <div class="rechts">
            @if($blatt['betrieb']){{ $blatt['betrieb'] }}<br>@endif
            {{ now()->format('d.m.Y') }}
        </div>
    </header>

    <section class="eckdaten">
        <div><label>{{ __('Lehrberuf') }}</label><span>{{ $blatt['lehrberuf'] ?? '–' }}</span></div>
        <div><label>{{ __('Lehrbeginn') }}</label><span>{{ $datum($blatt['lehrbeginn']) }}</span></div>
        <div><label>{{ __('Lehrende') }}</label><span>{{ $datum($blatt['lehrende']) }}</span></div>
        <div class="gesamt"><label>{{ __('Gesamtnote') }}</label><strong class="{{ $stufe($blatt['gesamt']) }}">{{ NotenSkala::format($blatt['gesamt'], 1) }}</strong></div>
    </section>

    @if($blatt['kategorien'])
        <div class="kats">
            @foreach($blatt['kategorien'] as $k)
                <span>{{ $k['name'] }}<strong class="{{ $stufe($k['note']) }}">{{ NotenSkala::format($k['note'], 1) }}</strong></span>
            @endforeach
        </div>
    @endif

    @forelse($blatt['semester'] as $sem)
        <section class="semester">
            <h2><span>{{ $sem['bezeichnung'] }}</span><small>{{ __('Semesterschnitt') }}<strong>{{ NotenSkala::format($sem['note'], 1) }}</strong></small></h2>
            @foreach($sem['kategorien'] as $kat)
                <table>
                    <thead>
                        <tr>
                            <th colspan="2">
                                {{ $kat['name'] }}
                                @if($kat['promotion'])
                                    <span @class(['prom', 'nein' => ! $kat['promotion']['erfuellt']])>{{ $kat['promotion']['erfuellt'] ? __('Promotion erfüllt') : __('Promotion gefährdet') }}</span>
                                @endif
                            </th>
                            <th class="r {{ $stufe($kat['note']) }}">{{ NotenSkala::format($kat['note'], 1) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($kat['elemente'] as $el)
                            <tr>
                                <td class="el">{{ $el['label'] }}</td>
                                <td class="pr">
                                    @foreach($el['pruefungen'] as $p)
                                        <span>{{ $datum($p['datum'], 'd.m.') }}@if($p['titel']) {{ $p['titel'] }}@endif <b>{{ $p['stufe'] ?? NotenSkala::format($p['note']) }}</b>@if(abs($p['gewicht'] - 100) > 0.001) · {{ Zahl::prozent($p['gewicht']) }}@endif</span>
                                    @endforeach
                                    @if($el['offen'])
                                        <span class="offen">{{ Zahl::prozent($el['offen']) }} {{ __('offen') }}</span>
                                    @endif
                                </td>
                                <td class="r note {{ $stufe($el['note']) }}">{{ $el['stufe'] ?? NotenSkala::format($el['note'], 1) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        </section>
    @empty
        <p class="leer">{{ __('Keine Noten erfasst.') }}</p>
    @endforelse

    <div class="unterschriften">
        <section><div></div><p>{{ __('Datum / Unterschrift Lernende/r') }}</p></section>
        <section><div></div><p>{{ __('Datum / Unterschrift Berufsbildner/in') }}</p></section>
    </div>

    <footer>{{ __('Gedruckt am') }} {{ now()->format('d.m.Y H:i') }} · {{ config('app.name') }}</footer>
</body>
</html>
