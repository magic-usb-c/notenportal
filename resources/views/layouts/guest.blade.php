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
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('layouts._darstellung')

        @if(app()->getLocale() !== 'de')<script>window.npI18n = {{ \Illuminate\Support\Js::from(\App\Support\JsTexte::uebersetzt()) }};</script>@endif
        <title>{{ $titel ? $titel.' – '.config('app.name', 'Notenportal') : config('app.name', 'Notenportal') }}</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        @include('layouts._pwa-head')

        {{-- Schrift (Inter Variable) ist über app.css selbst gehostet, kein externer Aufruf --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-akzent-eigen-stil :hex="$npAkzentEigen ?? null" />
    </head>

    <body class="font-sans antialiased bg-bg text-text">
        {{-- Wie die Anmeldefenster von macOS: App-Symbol und Titel frei über einer einzelnen Karte,
             Nebenaktionen (Passwort vergessen, Zurück) als ruhige Links darunter. --}}
        <main class="flex min-h-screen flex-col items-center justify-center px-4 py-16">
            <div class="w-full max-w-100">
                <div class="mb-8 flex flex-col items-center text-center">
                    <x-application-logo class="mb-5 h-16 max-w-full" />
                    <h1 class="text-2xl font-semibold text-text">{{ $titel ?? config('app.name', 'Notenportal') }}</h1>
                    @if($text ?? ($titel ? null : $betriebName))
                        <p class="mt-1.5 max-w-80 text-sm text-muted">{{ $text ?? $betriebName }}</p>
                    @endif
                </div>

                <div class="np-karte p-8">
                    {{ $slot }}
                </div>

                @isset($fuss)
                    <div class="mt-6 flex flex-col items-center gap-2 text-sm">{{ $fuss }}</div>
                @endisset
            </div>
        </main>
    </body>
</html>
