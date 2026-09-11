{{-- Installierbare Web-App (PWA): Manifest, Theme-Farbe (hell/dunkel), iOS-Metadaten. --}}
@php
    $npBgHell = \App\Support\Theme::hintergrundHell($npTheme ?? 'gletscher');
    $npBgDunkel = \App\Support\Theme::hintergrundDunkel($npTheme ?? 'gletscher');
@endphp
@if(Route::has('manifest'))
<link rel="manifest" href="{{ route('manifest') }}">
@endif
<meta name="theme-color" media="(prefers-color-scheme: light)" content="{{ $npBgHell }}">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="{{ $npBgDunkel }}">
<link rel="apple-touch-icon" href="/app-icons/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Notenportal') }}">
