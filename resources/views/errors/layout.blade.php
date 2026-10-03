<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $npTheme ?? 'gletscher' }}"
      @if($npAkzent ?? null) data-akzent="{{ $npAkzent }}" @endif
      @if(($npSchrift ?? 'normal') !== 'normal') data-schrift="{{ $npSchrift }}" @endif
      @if(($npSchriftart ?? 'standard') !== 'standard') data-schriftart="{{ $npSchriftart }}" @endif
      @if(($npBewegung ?? 'normal') !== 'normal') data-bewegung="{{ $npBewegung }}" @endif
      @if(($npEcken ?? 'rund') !== 'rund') data-ecken="{{ $npEcken }}" @endif
      @if(($npTransparenz ?? 'normal') !== 'normal') data-transparenz="{{ $npTransparenz }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} – {{ config('app.name') }}</title>
    @include('layouts._darstellung')
    @include('layouts._pwa-head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-akzent-eigen-stil :hex="$npAkzentEigen ?? null" />
</head>
@php
    $angemeldet = rescue(fn () => auth()->user(), null, false);
    // Zurück nur, wenn die Person von einer Seite dieses Portals kam (kein Datenbankzugriff nötig)
    $herkunft = (string) request()->headers->get('referer');
    $teile = $herkunft !== '' ? parse_url($herkunft) : false;
    $ausPortal = is_array($teile)
        && in_array($teile['scheme'] ?? '', ['http', 'https'], true)
        && ($teile['host'] ?? null) === request()->getHost()
        && ($teile['port'] ?? ($teile['scheme'] === 'https' ? 443 : 80)) === request()->getPort();
    // Bei GET führt die eigene Adresse nicht «zurück»; ein Formular, das auf sich selbst sendet, schon
    $zurueck = $ausPortal && (! request()->isMethod('GET') || $herkunft !== request()->fullUrl()) ? $herkunft : null;
    // Bei Wartung und Drosselung führt «Zur Anmeldung» nirgends hin oder mitten aus der Aufgabe: erneut versuchen ist der Hauptweg
    $wiederholen = in_array($code, [429, 503], true)
        ? (request()->isMethod('GET') ? request()->fullUrl() : $zurueck)
        : null;
    $start = $angemeldet ? url('/') : route('login');
    $startText = $angemeldet ? __('Zur Übersicht') : __('Zur Anmeldung');
@endphp
<body class="font-sans antialiased bg-bg text-text min-h-screen flex flex-col items-center justify-center p-6">
    <div class="max-w-md w-full text-center space-y-4">
        <div class="text-display text-ghost" aria-hidden="true">{{ $code }}</div>
        <h1 class="text-2xl font-bold text-text">{{ $title }}</h1>
        <p class="text-muted text-sm">{{ $message }}</p>
        @if($code === 403 && $angemeldet)
            {{-- Häufig: Mail-Link für ein anderes Konto (z. B. Admin-Adresse), geöffnet in einer fremden Sitzung --}}
            <p class="text-muted text-sm">{{ __('Angemeldet als :name', ['name' => $angemeldet->vorname.' '.$angemeldet->nachname.' ('.$angemeldet->email.')']) }}</p>
        @endif
        <div class="flex flex-wrap items-center justify-center gap-2 pt-2">
            @if($wiederholen)
                <a href="{{ $wiederholen }}" class="np-knopf np-knopf-primaer np-knopf-gross">{{ $code === 503 ? __('Erneut laden') : __('Erneut versuchen') }}</a>
                <a href="{{ $start }}" class="np-knopf np-knopf-sekundaer np-knopf-gross">{{ $startText }}</a>
            @else
                <a href="{{ $start }}" class="np-knopf np-knopf-primaer np-knopf-gross">{{ $startText }}</a>
                @if($zurueck && $code !== 503)
                    <a href="{{ $zurueck }}" x-data @click.prevent="history.length > 1 ? history.back() : (location.href = $el.href)" class="np-knopf np-knopf-sekundaer np-knopf-gross">{{ __('Zurück') }}</a>
                @endif
            @endif
        </div>
        @if($code === 403 && $angemeldet)
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <input type="hidden" name="weiter" value="{{ request()->getRequestUri() }}">
                <button type="submit" class="np-knopf np-knopf-schlicht">{{ __('Mit anderem Konto anmelden') }}</button>
            </form>
        @endif
    </div>
</body>
</html>
