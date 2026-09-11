<!DOCTYPE html>
@php
    $darstellung = auth()->user()?->darstellung ?? 'system';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $darstellung === 'dunkel' ? 'dark' : '' }}" data-theme="{{ $npTheme ?? 'gletscher' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Darstellung: im Profil gespeichert (hell/dunkel) oder wie Gerät; vor CSS setzen, damit nichts flackert --}}
        <script>
            (function () {
                const gespeichert = @js($darstellung);
                window.npDarstellung = gespeichert;
                if (gespeichert !== 'system') return;
                let dunkel = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                try {
                    const lokal = localStorage.getItem('theme');
                    if (lokal) dunkel = lokal === 'dark';
                } catch (e) {}
                document.documentElement.classList.toggle('dark', dunkel);
            })();

            window.npToggleTheme = function () {
                const dunkel = document.documentElement.classList.toggle('dark');
                try { localStorage.setItem('theme', dunkel ? 'dark' : 'light'); } catch (e) {}
                fetch(@js(route('profile.appearance')), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ darstellung: dunkel ? 'dunkel' : 'hell' }),
                });
            };
        </script>

        <title>{{ isset($title) ? $title . " – " . config("app.name", "Notenportal") : config("app.name", "Notenportal") }}</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">

        {{-- Schrift (Inter Variable) ist über app.css selbst gehostet, kein externer Aufruf --}}
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    <body class="font-sans antialiased bg-bg text-text">
        {{-- Page-Progress-Bar (accent, 2px, oben) --}}
        <div id="np-progress"></div>

        <div class="min-h-screen flex flex-col">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-card/70 backdrop-blur-xs border-b border-border">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            <footer class="py-4 text-center text-xs text-muted/70">
                Notenportal{{ $betriebName ? ' · '.$betriebName : '' }} · {{ now()->year }}
            </footer>
        </div>

        {{-- Globale Flash-Messages (Toast unten rechts) --}}
        @php
            $flashSuccess = session('success') ?? session('status');
            $flashError   = session('error');
        @endphp
        @if($flashSuccess)
            <div x-data="{ show: true }" x-show="show"
                 x-init="setTimeout(() => show = false, 4000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 class="fixed bottom-4 right-4 z-50 flex items-center gap-3 px-4 py-3 bg-card border border-green-500/30 text-green-700 dark:text-green-300 rounded-xl shadow-lg text-sm max-w-md">
                <svg class="w-5 h-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                </svg>
                <span>{{ $flashSuccess }}</span>
                <button @click="show = false" class="ml-2 text-muted hover:text-text" aria-label="Schliessen">×</button>
            </div>
        @endif
        @if($flashError)
            <div x-data="{ show: true }" x-show="show"
                 x-init="setTimeout(() => show = false, 6000)"
                 x-transition:leave="transition ease-in duration-300"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 class="fixed bottom-4 right-4 z-50 flex items-center gap-3 px-4 py-3 bg-card border border-red-500/30 text-red-700 dark:text-red-300 rounded-xl shadow-lg text-sm max-w-md">
                <svg class="w-5 h-5 shrink-0 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span>{{ $flashError }}</span>
                <button @click="show = false" class="ml-2 text-muted hover:text-text" aria-label="Schliessen">×</button>
            </div>
        @endif

        {{-- JS-ausgelöster Toast (z.B. nach AJAX-Aktionen ohne Reload): window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: '...' } })) --}}
        <div x-data="{ show: false, message: '' }"
             x-on:np-toast.window="message = $event.detail.message; show = true; setTimeout(() => show = false, 4000)"
             x-show="show" x-cloak
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             class="fixed bottom-4 right-4 z-50 flex items-center gap-3 px-4 py-3 bg-card border border-green-500/30 text-green-700 dark:text-green-300 rounded-xl shadow-lg text-sm max-w-md">
            <svg class="w-5 h-5 shrink-0 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
            </svg>
            <span x-text="message"></span>
            <button @click="show = false" class="ml-2 text-muted hover:text-text" aria-label="Schliessen">×</button>
        </div>

        <x-feedback-widget />

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

            // Page-Progress-Bar: startet bei Navigation/Submit, endet beim (Re-)Load
            (function () {
                const bar = document.getElementById('np-progress');
                if (!bar) return;

                const start = () => {
                    bar.classList.remove('np-done');
                    // Reflow erzwingen, damit die Transition neu startet
                    void bar.offsetWidth;
                    bar.classList.add('np-loading');
                };

                document.addEventListener('click', (e) => {
                    const a = e.target.closest('a[href]');
                    if (!a) return;
                    if (a.target === '_blank' || a.hasAttribute('download')) return;
                    if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
                    const href = a.getAttribute('href');
                    if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:')) return;
                    if (a.origin && a.origin !== window.location.origin) return;
                    start();
                });

                document.addEventListener('submit', () => start());

                // Bei Back/Forward-Cache-Restore zurücksetzen
                window.addEventListener('pageshow', () => {
                    bar.classList.remove('np-loading');
                    bar.classList.add('np-done');
                    setTimeout(() => bar.classList.remove('np-done'), 300);
                });
            })();
        </script>
    </body>
</html>