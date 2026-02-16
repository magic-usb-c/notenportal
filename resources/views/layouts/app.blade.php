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
        <div class="min-h-screen bg-gray-100 dark:bg-gray-950">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-900 border-b border-gray-100 dark:border-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 text-gray-900 dark:text-gray-100">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
