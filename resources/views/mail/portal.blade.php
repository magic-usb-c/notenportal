@php
    // Mailprogramme kennen keine CSS-Variablen: Farben inline (entsprechen den Tokens aus theme.css, Hell).
    $f = ['bg' => '#eef2f7', 'card' => '#ffffff', 'text' => '#0f172a', 'muted' => '#475569', 'border' => '#e2e8f0', 'accent' => '#2563eb', 'soft' => '#f1f5f9'];
    $schrift = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";
    $absatz = function (string $text) use ($f): string {
        $html = '';
        $liste = false;
        foreach (preg_split('/\R/u', trim($text)) as $zeile) {
            $t = trim($zeile);
            if (preg_match('/^[-•*]\s+(.+)$/u', $t, $m)) {
                if (! $liste) {
                    $html .= '<ul style="margin:4px 0 10px;padding-left:20px;">';
                    $liste = true;
                }
                $html .= '<li style="margin:3px 0;">'.e($m[1]).'</li>';
                continue;
            }
            if ($liste) {
                $html .= '</ul>';
                $liste = false;
            }
            $html .= $t === '' ? '<div style="height:6px;line-height:6px;">&nbsp;</div>' : '<p style="margin:0 0 6px;">'.e($t).'</p>';
        }

        return $html.($liste ? '</ul>' : '');
    };
    $gruppen = collect($c->items)->groupBy(fn ($i) => $i['group'] ?? '');
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>{{ $c->subject }}</title>
    <style>
        @media (max-width: 620px) { .np-card { border-radius: 0 !important; } .np-pad { padding-left: 22px !important; padding-right: 22px !important; } .np-btn { display: block !important; text-align: center !important; } }
        @media (prefers-color-scheme: dark) {
            .np-body { background: #020617 !important; } .np-card { background: #0f172a !important; border-color: #334155 !important; }
            .np-text { color: #e2e8f0 !important; } .np-muted { color: #94a3b8 !important; } .np-soft { background: #1e293b !important; }
            .np-line { border-color: #334155 !important; }
        }
    </style>
</head>
<body class="np-body" style="margin:0;padding:0;background:{{ $f['bg'] }};">
@if($c->preheader)
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $c->preheader }}&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:{{ $f['bg'] }};" class="np-body">
    <tr><td align="center" style="padding:28px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">
            <tr><td class="np-pad" style="padding:0 8px 14px;font-family:{{ $schrift }};font-size:13px;color:{{ $f['muted'] }};" >
                @if($logoUrl ?? null)
                    <img src="{{ $logoUrl }}" alt="" height="20" style="vertical-align:middle;margin-right:8px;border-radius:4px;">
                @else
                    <span style="display:inline-block;width:8px;height:8px;border-radius:4px;background:{{ $f['accent'] }};margin-right:8px;vertical-align:middle;"></span>
                @endif
                <span class="np-muted" style="vertical-align:middle;font-weight:600;letter-spacing:.02em;">Notenportal{{ $betrieb !== '' ? ' · '.$betrieb : '' }}</span>
            </td></tr>
            <tr><td class="np-card" style="background:{{ $f['card'] }};border:1px solid {{ $f['border'] }};border-radius:16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr><td class="np-pad np-text" style="padding:32px 36px 8px;font-family:{{ $schrift }};color:{{ $f['text'] }};font-size:15px;line-height:1.55;">
                        <h1 class="np-text" style="margin:0 0 18px;font-size:21px;line-height:1.3;font-weight:700;color:{{ $f['text'] }};">{{ $c->title ?? $c->subject }}</h1>
                        @if($vorname)<p style="margin:0 0 12px;">{{ __('Hallo :vorname', ['vorname' => $vorname]) }}</p>@endif
                        @foreach($c->lines as $zeile)
                            <p style="margin:0 0 12px;">{{ $zeile }}</p>
                        @endforeach
                    </td></tr>

                    @if($c->facts)
                        <tr><td class="np-pad" style="padding:6px 36px 10px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="np-soft" style="background:{{ $f['soft'] }};border-radius:12px;">
                                @foreach($c->facts as $label => $wert)
                                    <tr>
                                        <td class="np-muted np-line" style="padding:10px 16px;font-family:{{ $schrift }};font-size:13px;color:{{ $f['muted'] }};width:38%;vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid '.$f['border'].';' }}">{{ $label }}</td>
                                        <td class="np-text np-line" style="padding:10px 16px;font-family:{{ $schrift }};font-size:14px;font-weight:600;color:{{ $f['text'] }};vertical-align:top;{{ $loop->last ? '' : 'border-bottom:1px solid '.$f['border'].';' }}">{{ $wert }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td></tr>
                    @endif

                    @foreach($c->sections as $abschnitt)
                        <tr><td class="np-pad np-text" style="padding:14px 36px 4px;font-family:{{ $schrift }};color:{{ $f['text'] }};font-size:14px;line-height:1.6;">
                            <p class="np-muted" style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:{{ $f['muted'] }};">{{ $abschnitt['title'] }}</p>
                            {!! $absatz($abschnitt['text']) !!}
                        </td></tr>
                    @endforeach

                    @if($c->table)
                        <tr><td class="np-pad" style="padding:12px 36px 6px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-family:{{ $schrift }};font-size:14px;border-collapse:collapse;">
                                <tr>
                                    @foreach($c->table['head'] as $i => $kopf)
                                        <th class="np-muted np-line" align="{{ $i === 0 ? 'left' : 'right' }}" style="padding:8px 6px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:{{ $f['muted'] }};border-bottom:1px solid {{ $f['border'] }};">{{ $kopf }}</th>
                                    @endforeach
                                </tr>
                                @foreach($c->table['rows'] as $zeile)
                                    <tr>
                                        @foreach($zeile as $i => $zelle)
                                            <td class="np-text np-line" align="{{ $i === 0 ? 'left' : 'right' }}" style="padding:9px 6px;color:{{ $f['text'] }};border-bottom:1px solid {{ $f['border'] }};{{ $i === 0 ? '' : 'font-variant-numeric:tabular-nums;font-weight:600;' }}">{{ $zelle }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </table>
                        </td></tr>
                    @endif

                    @foreach($gruppen as $gruppe => $eintraege)
                        <tr><td class="np-pad" style="padding:14px 36px 2px;font-family:{{ $schrift }};">
                            @if($gruppe !== '')<p class="np-muted" style="margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:{{ $f['muted'] }};">{{ $gruppe }}</p>@endif
                            @foreach($eintraege as $e)
                                <div class="np-line" style="padding:9px 0;border-bottom:1px solid {{ $f['border'] }};">
                                    @if(! empty($e['url']))
                                        <a href="{{ $e['url'] }}" style="color:{{ $f['accent'] }};font-weight:600;font-size:14px;text-decoration:none;">{{ $e['title'] }}</a>
                                    @else
                                        <span class="np-text" style="color:{{ $f['text'] }};font-weight:600;font-size:14px;">{{ $e['title'] }}</span>
                                    @endif
                                    @if(! empty($e['text']))<div class="np-muted" style="color:{{ $f['muted'] }};font-size:13px;margin-top:2px;">{{ $e['text'] }}</div>@endif
                                </div>
                            @endforeach
                        </td></tr>
                    @endforeach

                    @if($c->actionUrl)
                        <tr><td class="np-pad" style="padding:22px 36px 6px;">
                            <a class="np-btn" href="{{ $c->actionUrl }}" style="display:inline-block;background:{{ $f['accent'] }};color:#ffffff;font-family:{{ $schrift }};font-size:15px;font-weight:600;text-decoration:none;padding:13px 24px;border-radius:12px;">{{ $c->actionLabel ?? __('Im Notenportal öffnen') }}</a>
                        </td></tr>
                    @endif

                    <tr><td class="np-pad np-text" style="padding:18px 36px 30px;font-family:{{ $schrift }};color:{{ $f['text'] }};font-size:14px;line-height:1.55;">
                        @foreach($c->outro as $zeile)
                            <p style="margin:0 0 10px;">{{ $zeile }}</p>
                        @endforeach
                        @if($c->actionUrl)
                            <p class="np-muted" style="margin:10px 0 0;font-size:12px;color:{{ $f['muted'] }};word-break:break-all;">{{ __('Knopf funktioniert nicht?') }} {{ $c->actionUrl }}</p>
                        @endif
                    </td></tr>
                </table>
            </td></tr>
            <tr><td class="np-pad np-muted" style="padding:18px 8px 0;font-family:{{ $schrift }};font-size:12px;line-height:1.6;color:{{ $f['muted'] }};">
                @if($anlass){{ __('Du erhältst diese Mail wegen «:anlass».', ['anlass' => $anlass]) }}@endif
                @if($einstellungenUrl) <a href="{{ $einstellungenUrl }}" style="color:{{ $f['muted'] }};text-decoration:underline;">{{ __('Benachrichtigungen einstellen') }}</a>@endif
                @if($abmeldenUrl) · <a href="{{ $abmeldenUrl }}" style="color:{{ $f['muted'] }};text-decoration:underline;">{{ __('Diese Mails abbestellen') }}</a>@endif
                <br><a href="{{ $portalUrl }}" style="color:{{ $f['muted'] }};text-decoration:none;">Notenportal{{ $betrieb !== '' ? ' '.$betrieb : '' }}</a>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
