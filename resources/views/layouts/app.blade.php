<!DOCTYPE html>
@php
    $darstellung = auth()->user()?->darstellung ?? 'system';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="{{ $darstellung === 'dunkel' ? 'dark' : '' }}" data-theme="{{ $npTheme ?? 'gletscher' }}"
      @if($npAkzent ?? null) data-akzent="{{ $npAkzent }}" @endif
      @if(($npSchrift ?? 'normal') !== 'normal') data-schrift="{{ $npSchrift }}" @endif
      @if(($npSchriftart ?? 'standard') !== 'standard') data-schriftart="{{ $npSchriftart }}" @endif
      @if(($npBewegung ?? 'normal') !== 'normal') data-bewegung="{{ $npBewegung }}" @endif
      @if(($npDichte ?? 'normal') !== 'normal') data-dichte="{{ $npDichte }}" @endif
      @if(($npDiagramm ?? 'standard') !== 'standard') data-diagramm="{{ $npDiagramm }}" @endif
      @if(($npEcken ?? 'rund') !== 'rund') data-ecken="{{ $npEcken }}" @endif
      @if(($npTransparenz ?? 'normal') !== 'normal') data-transparenz="{{ $npTransparenz }}" @endif
      @if(($npNavigation ?? 'oben') !== 'oben') data-navigation="{{ $npNavigation }}" @endif>
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

            {{-- Meldung im vorhandenen Toast (unten rechts, siehe <x-toast> weiter unten),
                 wenn ein optimistisch übernommener Schnellwechsel nicht gespeichert werden konnte. --}}
            function npFehlermeldung() {
                window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: @js(__('Änderung konnte nicht gespeichert werden.')) } }));
            }

            window.npToggleTheme = function () {
                const vorherDunkel = document.documentElement.classList.contains('dark');
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
                }).then(function (antwort) {
                    if (antwort.ok) return;
                    document.documentElement.classList.toggle('dark', vorherDunkel);
                    try { localStorage.setItem('theme', vorherDunkel ? 'dark' : 'light'); } catch (e) {}
                    npFehlermeldung();
                }).catch(function () {
                    document.documentElement.classList.toggle('dark', vorherDunkel);
                    try { localStorage.setItem('theme', vorherDunkel ? 'dark' : 'light'); } catch (e) {}
                    npFehlermeldung();
                });
            };

            {{-- Schnellwechsel aus der Befehlspalette (resources/js/suche.js): '#art:wert' –
                 Attribut sofort setzen (kein Warten auf die Antwort), dann speichern. Schlägt das
                 PATCH fehl (Netzwerk, 419 abgelaufene Sitzung, sonstiger Fehlerstatus), wird der
                 optimistisch gesetzte Wert zurückgesetzt und eine Meldung angezeigt. --}}
            window.npBefehl = function (ziel) {
                const treffer = /^#(darstellung|theme|schrift|dichte|diagramm|navigation):(.+)$/.exec(ziel);
                if (!treffer) return;
                const [, art, wert] = treffer;
                const root = document.documentElement;
                // Neutraler Wert je Attribut: ohne Präferenz wird kein data-* gesetzt (siehe layouts/app.blade.php Kopf).
                const neutral = { schrift: 'normal', dichte: 'normal', diagramm: 'standard', navigation: 'oben' };

                const vorher = {
                    dunkel: root.classList.contains('dark'),
                    theme: root.dataset.theme,
                    schrift: root.dataset.schrift,
                    dichte: root.dataset.dichte,
                    diagramm: root.dataset.diagramm,
                    navigation: root.dataset.navigation,
                };
                const zuruecksetzen = function () {
                    root.classList.toggle('dark', vorher.dunkel);
                    if (vorher.theme === undefined) delete root.dataset.theme; else root.dataset.theme = vorher.theme;
                    if (vorher.schrift === undefined) delete root.dataset.schrift; else root.dataset.schrift = vorher.schrift;
                    if (vorher.dichte === undefined) delete root.dataset.dichte; else root.dataset.dichte = vorher.dichte;
                    if (vorher.diagramm === undefined) delete root.dataset.diagramm; else root.dataset.diagramm = vorher.diagramm;
                    if (vorher.navigation === undefined) delete root.dataset.navigation; else root.dataset.navigation = vorher.navigation;
                    window.dispatchEvent(new CustomEvent('np-navigation'));
                    npFehlermeldung();
                };

                if (art === 'darstellung') {
                    let dunkel = wert === 'dunkel';
                    if (wert === 'system') {
                        dunkel = !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    }
                    root.classList.toggle('dark', dunkel);
                    try {
                        if (wert === 'system') localStorage.removeItem('theme');
                        else localStorage.setItem('theme', dunkel ? 'dark' : 'light');
                    } catch (e) {}
                } else if (art === 'theme') {
                    root.dataset.theme = wert;
                } else if (art === 'schrift' || art === 'dichte' || art === 'diagramm' || art === 'navigation') {
                    if (wert === neutral[art]) delete root.dataset[art];
                    else root.dataset[art] = wert;
                    if (art === 'navigation') window.dispatchEvent(new CustomEvent('np-navigation'));
                }

                fetch(@js(route('profile.preferences')), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ [art]: wert }),
                }).then(function (antwort) {
                    if (!antwort.ok) zuruecksetzen();
                }).catch(function () {
                    zuruecksetzen();
                });
            };
        </script>

        @if(app()->getLocale() !== 'de')<script>window.npI18n = {{ \Illuminate\Support\Js::from(\App\Support\JsTexte::uebersetzt()) }};</script>@endif
        @php
            // Der Titel kommt als Slot, oft über eine zweite Komponente gereicht und dabei schon maskiert:
            // auf Klartext zurückführen und genau einmal maskieren (sonst zeigt der Tab «&amp;amp;»).
            $seitentitel = isset($title) ? trim(strip_tags((string) $title)) : '';
            while ($seitentitel !== ($klartext = html_entity_decode($seitentitel, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) {
                $seitentitel = $klartext;
            }
        @endphp
        <title>{{ $seitentitel !== '' ? $seitentitel.' – '.config('app.name', 'Notenportal') : config('app.name', 'Notenportal') }}</title>
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        @include('layouts._pwa-head')

        {{-- Schrift (Inter Variable) ist über app.css selbst gehostet, kein externer Aufruf --}}
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <x-akzent-eigen-stil :hex="$npAkzentEigen ?? null" />
    </head>

    <body class="font-sans antialiased bg-bg text-text">
        {{-- Page-Progress-Bar (accent, 2px, oben) --}}
        <div id="np-progress"></div>

        <div class="min-h-screen flex flex-col seite:lg:pl-60">
            @include('layouts.navigation')

            @if(Route::has('system-notice.dismiss') && ($systemhinweis ?? null))
                @include('layouts._systemhinweis')
            @endif

            <!-- Page Heading -->
            {{-- Seitenkopf im selben Container wie der Inhalt: eine bündige Achse --}}
            @isset($header)
                <header class="mx-auto w-full np-seite px-4 pt-6 sm:px-6 sm:pt-8 lg:px-8">
                    {{ $header }}
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1">
                {{ $slot }}
            </main>

            {{-- Unter dem schwebenden Feedback-Knopf Platz lassen, damit er am Seitenende nichts verdeckt --}}
            @php $platzFuerFeedback = auth()->check() && \App\Support\Einstellungen::get(\App\Support\Einstellungen::FEEDBACK_KNOPF, '1') !== '0'; @endphp
            <footer @class(['pt-4 text-center text-xs text-muted', $platzFuerFeedback ? 'pb-24' : 'pb-4'])>
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

        @auth
            @include('layouts._sitzung')
        @endauth

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