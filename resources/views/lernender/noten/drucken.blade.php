<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notenblatt – {{ $lernender->vorname }} {{ $lernender->nachname }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            font-size: 11pt;
            color: #1a1a1a;
            background: #fff;
            padding: 20mm 20mm 15mm 20mm;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #1a1a1a;
            padding-bottom: 8px;
            margin-bottom: 16px;
        }

        .header-title { font-size: 18pt; font-weight: 700; }
        .header-sub   { font-size: 10pt; color: #555; margin-top: 2px; }
        .header-meta  { text-align: right; font-size: 9pt; color: #555; }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 20px;
            background: #f5f5f5;
            border-radius: 6px;
            padding: 10px 14px;
        }

        .meta-item label { font-size: 8pt; color: #777; display: block; }
        .meta-item span  { font-size: 10pt; font-weight: 600; }

        .semester-section { margin-bottom: 20px; page-break-inside: avoid; }
        .semester-title {
            font-size: 11pt;
            font-weight: 700;
            background: #1a1a1a;
            color: #fff;
            padding: 4px 10px;
            border-radius: 4px;
            margin-bottom: 6px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
        }

        thead th {
            background: #f0f0f0;
            border: 1px solid #ccc;
            padding: 4px 8px;
            text-align: left;
            font-weight: 600;
            font-size: 8.5pt;
            color: #444;
        }

        tbody td {
            border: 1px solid #ddd;
            padding: 4px 8px;
            vertical-align: top;
        }

        tbody tr:nth-child(even) td { background: #fafafa; }

        .note-cell {
            text-align: center;
            font-weight: 700;
            font-size: 11pt;
        }

        .note-pass { color: #166534; }
        .note-warn { color: #854d0e; }
        .note-fail { color: #991b1b; }

        .sem-footer {
            display: flex;
            justify-content: flex-end;
            gap: 20px;
            margin-top: 4px;
            font-size: 9pt;
            color: #555;
        }

        .sem-footer strong { color: #1a1a1a; }

        .total-section {
            margin-top: 20px;
            border-top: 2px solid #1a1a1a;
            padding-top: 12px;
            display: flex;
            justify-content: flex-end;
            gap: 30px;
        }

        .total-item { text-align: center; }
        .total-item .label { font-size: 8pt; color: #777; }
        .total-item .value { font-size: 14pt; font-weight: 700; }

        .footer-print {
            margin-top: 20px;
            font-size: 8pt;
            color: #aaa;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }

        @media print {
            body { padding: 0; }
            @page { margin: 15mm 15mm 12mm 15mm; size: A4; }
        }

        .no-print { }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    {{-- Drucken-Knopf (nur am Bildschirm) --}}
    <div class="no-print" style="text-align:right; margin-bottom: 12px;">
        <button onclick="window.print()"
                style="padding: 8px 20px; background: #2563eb; color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600;">
            Drucken / Als PDF speichern
        </button>
        <a href="javascript:history.back()"
           style="margin-left: 10px; padding: 8px 16px; background: #f3f4f6; color: #374151; border: 1px solid #d1d5db; border-radius: 8px; text-decoration: none; font-size: 13px;">
            Zurück
        </a>
    </div>

    <div class="header">
        <div>
            <div class="header-title">Notenblatt</div>
            <div class="header-sub">{{ $lernender->vorname }} {{ $lernender->nachname }}</div>
        </div>
        <div class="header-meta">
            Erstellt: {{ now()->format('d.m.Y') }}<br>
            {{ config('app.name') }}
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-item">
            <label>Lehrberuf</label>
            <span>{{ $profil?->lehrberuf_name ?? '–' }}</span>
        </div>
        <div class="meta-item">
            <label>Lehrbeginn</label>
            <span>{{ $profil?->lehrbeginn ? \Carbon\Carbon::parse($profil->lehrbeginn)->format('d.m.Y') : '–' }}</span>
        </div>
        <div class="meta-item">
            <label>Lehrende</label>
            <span>{{ $profil?->lehrende ? \Carbon\Carbon::parse($profil->lehrende)->format('d.m.Y') : '–' }}</span>
        </div>
    </div>

    @forelse($semesterNoten as $sem)
        @php
            $semNotes = $sem['noten'];
            $semAvg = null;
            if ($semNotes->isNotEmpty()) {
                $wSum = $semNotes->sum(fn($n) => (float) ($n->gewichtung_prozent ?? 100));
                $nSum = $semNotes->sum(fn($n) => (float)$n->note_wert * (float) ($n->gewichtung_prozent ?? 100));
                $semAvg = $wSum > 0 ? round($nSum / $wSum, 2) : null;
            }
            $passed = $semNotes->filter(fn($n) => (float)$n->note_wert >= 4.0)->count();
        @endphp
        <div class="semester-section">
            <div class="semester-title">{{ $sem['bezeichnung'] }}</div>
            <table>
                <thead>
                    <tr>
                        <th style="width:90px">Datum</th>
                        <th>Fach / Modul</th>
                        <th>Titel</th>
                        <th>Kategorie</th>
                        <th style="width:60px; text-align:center">Gew.%</th>
                        <th style="width:50px; text-align:center">Note</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($semNotes as $n)
                        @php
                            $thema = $n->fach_name ?? ($n->modul_nummer ? $n->modul_nummer . ' – ' . $n->modul_titel : '–');
                            $nw = (float)$n->note_wert;
                            $nc = $nw >= 4.0 ? 'note-pass' : ($nw >= 3.5 ? 'note-warn' : 'note-fail');
                        @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($n->pruefungsdatum)->format('d.m.Y') }}</td>
                            <td>{{ $thema }}</td>
                            <td>{{ $n->titel ?? '' }}</td>
                            <td>{{ $n->kategorie_name ?? '' }}</td>
                            <td style="text-align:center; color:#777">{{ $n->gewichtung_prozent !== null ? $n->gewichtung_prozent . ' %' : '' }}</td>
                            <td class="note-cell {{ $nc }}">{{ number_format($nw, 1) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if($semNotes->isNotEmpty())
                <div class="sem-footer">
                    <span>Anzahl: <strong>{{ $semNotes->count() }}</strong></span>
                    <span>Bestanden: <strong>{{ $passed }} / {{ $semNotes->count() }}</strong></span>
                    <span>Ø gewichtet: <strong>{{ $semAvg !== null ? number_format($semAvg, 2) : '–' }}</strong></span>
                </div>
            @endif
        </div>
    @empty
        <p style="color:#777; margin-top: 20px;">Keine Noten erfasst.</p>
    @endforelse

    @php
        $allNoten = collect($semesterNoten)->flatMap(fn($s) => $s['noten']);
        $globalWSum = $allNoten->sum(fn($n) => (float) ($n->gewichtung_prozent ?? 100));
        $globalNSum = $allNoten->sum(fn($n) => (float)$n->note_wert * (float) ($n->gewichtung_prozent ?? 100));
        $globalAvg  = $globalWSum > 0 ? round($globalNSum / $globalWSum, 2) : null;
        $globalPass = $allNoten->filter(fn($n) => (float)$n->note_wert >= 4.0)->count();
    @endphp

    @if($allNoten->isNotEmpty())
        <div class="total-section">
            <div class="total-item">
                <div class="label">Noten gesamt</div>
                <div class="value">{{ $allNoten->count() }}</div>
            </div>
            <div class="total-item">
                <div class="label">Bestanden gesamt</div>
                <div class="value">{{ $globalPass }} / {{ $allNoten->count() }}</div>
            </div>
            <div class="total-item">
                <div class="label">Gesamtdurchschnitt</div>
                <div class="value" style="color: {{ $globalAvg !== null && $globalAvg >= 4.0 ? '#166534' : ($globalAvg !== null && $globalAvg >= 3.5 ? '#854d0e' : '#991b1b') }}">
                    {{ $globalAvg !== null ? number_format($globalAvg, 2) : '–' }}
                </div>
            </div>
        </div>
    @endif

    <div class="footer-print">
        Gedruckt am {{ now()->format('d.m.Y H:i') }} Uhr &nbsp;·&nbsp; {{ config('app.name') }}
    </div>

</body>
</html>
