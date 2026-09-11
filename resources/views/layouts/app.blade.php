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

        @if(app()->getLocale() !== 'de')<script>window.npI18n = {{ \Illuminate\Support\Js::from(\App\Support\JsTexte::uebersetzt()) }};</script>@endif<title>{{ isset($title) ? $title . " – " . config("app.name", "Notenportal") : config("app.name", "Notenportal") }}</title>
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
            {{-- Seitenkopf im selben Container wie der Inhalt: eine bündige Achse --}}
            @isset($header)
                <header class="mx-auto w-full max-w-7xl px-4 pt-6 sm:px-6 sm:pt-8 lg:px-8">
                    {{ $header }}
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
            <x-toast art="erfolg" :meldung="$flashSuccess" />
        @endif
        @if($flashError)
            <x-toast art="fehler" :meldung="$flashError" />
        @endif

        {{-- JS-ausgelöster Toast: window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: '...' } })) --}}
        <x-toast art="erfolg" />

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