<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="{{ $npTheme ?? 'gletscher' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Modus hell/dunkel vor CSS setzen, damit nichts flackert (Theme kommt serverseitig) --}}
        <script>
            (function () {
                try {
                    const stored = localStorage.getItem('theme'); // 'dark' | 'light' | null
                    const useDark = stored ? (stored === 'dark') : true;
                    document.documentElement.classList.toggle('dark', useDark);
                } catch (e) {
                    // Falls localStorage blockiert ist: nichts tun
                }
            })();
        </script>

        <title>{{ isset($title) ? $title . " – " . config("app.name", "Notenportal") : config("app.name", "Notenportal") }}</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        {{-- Schrift (Inter Variable) ist über app.css selbst gehostet, kein externer Aufruf --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased bg-bg text-text">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-8 bg-bg">
            <div class="w-full max-w-sm glass rounded-2xl overflow-hidden px-6 py-8">
                {{ $slot }}
            </div>

            <div class="mt-6 text-xs text-muted">
                Notenportal{{ $betriebName ? ' · '.$betriebName : '' }} · {{ now()->year }}
            </div>
        </div>
    </body>
</html>
