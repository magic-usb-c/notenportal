<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Theme init (vor CSS/JS, damit es nicht "blinkt") --}}
        <script>
            (function () {
                try {
                    const stored = localStorage.getItem('theme'); // 'dark' | 'light' | null
                    const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                    const useDark = stored ? (stored === 'dark') : true;
                    document.documentElement.classList.toggle('dark', useDark);
                } catch (e) {
                    // Falls localStorage blockiert ist: nichts tun
                }
            })();
        </script>

        <title>{{ isset($title) ? $title . " – " . config("app.name", "Notenportal") : config("app.name", "Notenportal") }}</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased bg-bg text-text">
        <div class="relative min-h-screen flex flex-col justify-center items-center px-4 py-8 bg-bg overflow-hidden">
            {{-- Dekorative Accent-Orbs für Tiefe hinter dem Glass-Panel --}}
            <div class="absolute -top-32 -left-32 w-96 h-96 rounded-full bg-accent/15 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-40 -right-24 w-[28rem] h-[28rem] rounded-full bg-accent/10 blur-3xl pointer-events-none"></div>

            <div class="relative w-full max-w-sm glass rounded-2xl overflow-hidden px-6 py-8">
                {{ $slot }}
            </div>

            <div class="relative mt-6 text-xs text-muted/70">
                Notenportal · Hamilton Bonaduz AG · {{ now()->year }}
            </div>
        </div>
    </body>
</html>