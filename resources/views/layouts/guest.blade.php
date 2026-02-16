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
                    const useDark = stored ? (stored === 'dark') : prefersDark;
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

    <body class="font-sans antialiased bg-gray-100 dark:bg-gray-950 text-gray-900 dark:text-gray-100">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-950">
            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20 fill-current text-gray-500 dark:text-gray-300" />
                </a>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white dark:bg-gray-900 border border-gray-100 dark:border-gray-800 shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
