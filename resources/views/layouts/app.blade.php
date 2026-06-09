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

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased bg-bg text-text">
        <div class="min-h-screen bg-bg">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-card border-b border-border shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        <script>
            // Erfolgsmeldungen nach 3 Sekunden ausblenden
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('[data-autohide]').forEach(function (el) {
                    setTimeout(function () {
                        el.style.transition = 'opacity 0.5s';
                        el.style.opacity = '0';
                        setTimeout(function () { el.style.display = 'none'; }, 500);
                    }, 3000);
                });
            });
        </script>
    </body>
</html>