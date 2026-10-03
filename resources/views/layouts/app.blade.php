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
      data-navigation="{{ $npNavigation ?? 'seite' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @include('layouts._darstellung')
        <script>
            {{-- Meldung im vorhandenen Toast (siehe <x-toast> weiter unten),
                 wenn ein optimistisch übernommener Schnellwechsel nicht gespeichert werden konnte. --}}
            function npFehlermeldung() {
                window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: @js(__('Änderung konnte nicht gespeichert werden.')), art: 'fehler' } }));
            }

            {{-- Menü wächst aus seinem Auslöser (GUI-R6 B1, G9). Setzt am Panel Startmass (--np-von-sx/-sy) und Ursprung
                 (--np-ursprung, Mitte des Auslösers im Koordinatensystem des Panels) und startet die Öffnungsbewegung
                 (@starting-style von glass-overlay) neu, damit sie mit diesen Werten läuft. Aufrufen, wenn das Panel
                 schon angezeigt wird (nach $nextTick). Gleiche Rechnung wie morphUrsprung() in np.js; sobald diese
                 Funktion an window.np hängt, nimmt npMorph sie. Ohne Mass (Panel noch versteckt) passiert nichts. --}}
            window.npMorph = function (ausloeser, panel) {
                try {
                    if (!ausloeser || !panel) return false;
                    if (window.np && typeof window.np.morphUrsprung === 'function') {
                        if (!window.np.morphUrsprung(ausloeser, panel)) return false;
                    } else {
                        const a = ausloeser.getBoundingClientRect();
                        const p = panel.getBoundingClientRect();
                        const breite = panel.offsetWidth;
                        const hoehe = panel.offsetHeight;
                        if (!breite || !hoehe || !a.width || !a.height) return false;
                        const skala = p.width / breite || 1;
                        const von = function (wert) { return Math.min(1, Math.max(0.01, wert)); };
                        panel.style.setProperty('--np-von-sx', von(a.width / breite).toFixed(3));
                        panel.style.setProperty('--np-von-sy', von(a.height / hoehe).toFixed(3));
                        panel.style.setProperty('--np-ursprung', ((a.left + a.width / 2 - p.left) / skala).toFixed(1) + 'px ' + ((a.top + a.height / 2 - p.top) / skala).toFixed(1) + 'px');
                    }
                    const ruhig = window.matchMedia('(prefers-reduced-motion: reduce)').matches || document.documentElement.dataset.bewegung === 'reduziert';
                    if (!ruhig) {
                        const anzeige = panel.style.display;
                        panel.style.display = 'none';
                        void panel.offsetWidth;
                        panel.style.display = anzeige;
                    }
                    return true;
                } catch (e) {
                    return false;
                }
            };

            window.npToggleTheme = function () {
                const vorher = window.npDarstellung;
                const dunkel = !document.documentElement.classList.contains('dark');
                const zurueck = function () {
                    window.npDarstellung = vorher;
                    window.npDarstellungAnwenden();
                    try {
                        if (vorher === 'system') localStorage.removeItem('theme');
                        else localStorage.setItem('theme', vorher === 'dunkel' ? 'dark' : 'light');
                    } catch (e) {}
                    npFehlermeldung();
                };
                window.npDarstellung = dunkel ? 'dunkel' : 'hell';
                window.npDarstellungAnwenden();
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
                    if (!antwort.ok) zurueck();
                }).catch(zurueck);
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
                // data-navigation steht immer, denn Seitenleiste und Tableiste sind beide eigene Zustände.
                const neutral = { schrift: 'normal', dichte: 'normal', diagramm: 'standard' };

                const vorher = {
                    darstellung: window.npDarstellung,
                    theme: root.dataset.theme,
                    schrift: root.dataset.schrift,
                    dichte: root.dataset.dichte,
                    diagramm: root.dataset.diagramm,
                    navigation: root.dataset.navigation,
                };
                const zuruecksetzen = function () {
                    window.npDarstellung = vorher.darstellung;
                    window.npDarstellungAnwenden();
                    try {
                        if (vorher.darstellung === 'system') localStorage.removeItem('theme');
                        else localStorage.setItem('theme', vorher.darstellung === 'dunkel' ? 'dark' : 'light');
                    } catch (e) {}
                    if (vorher.theme === undefined) delete root.dataset.theme; else root.dataset.theme = vorher.theme;
                    if (vorher.schrift === undefined) delete root.dataset.schrift; else root.dataset.schrift = vorher.schrift;
                    if (vorher.dichte === undefined) delete root.dataset.dichte; else root.dataset.dichte = vorher.dichte;
                    if (vorher.diagramm === undefined) delete root.dataset.diagramm; else root.dataset.diagramm = vorher.diagramm;
                    if (vorher.navigation === undefined) delete root.dataset.navigation; else root.dataset.navigation = vorher.navigation;
                    window.dispatchEvent(new CustomEvent('np-navigation'));
                    npFehlermeldung();
                };

                if (art === 'darstellung') {
                    window.npDarstellung = wert;
                    window.npDarstellungAnwenden();
                    try {
                        if (wert === 'system') localStorage.removeItem('theme');
                        else localStorage.setItem('theme', wert === 'dunkel' ? 'dark' : 'light');
                    } catch (e) {}
                } else if (art === 'theme') {
                    root.dataset.theme = wert;
                } else if (art === 'navigation') {
                    root.dataset.navigation = wert;
                    window.dispatchEvent(new CustomEvent('np-navigation'));
                } else if (art === 'schrift' || art === 'dichte' || art === 'diagramm') {
                    if (wert === neutral[art]) delete root.dataset[art];
                    else root.dataset[art] = wert;
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
            // Der Titel kommt als Slot, also als HTML, das {{ }} genau einmal maskiert hat: Tags weg, einmal
            // dekodieren, unten einmal maskieren. Nicht öfter dekodieren – ein Name wie «QV &amp; B» ist Text.
            $seitentitel = isset($title) ? trim(html_entity_decode(strip_tags((string) $title), ENT_QUOTES | ENT_HTML5, 'UTF-8')) : '';
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
        {{-- Ruhiger Grund hinter Seitenleiste und Inhalt: statischer Verlauf aus Tokens --}}
        <div class="np-grund" aria-hidden="true"></div>

        <a href="#inhalt" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[90] focus:inline-flex focus:h-9 focus:items-center focus:rounded-lg focus:px-3.5 focus:text-sm focus:font-medium focus:text-text glass-overlay">{{ __('Zum Inhalt springen') }}</a>

        {{-- Page-Progress-Bar (accent, 2px, oben) --}}
        <div id="np-progress"></div>

        {{-- Fenster: schwebende Seitenleiste (fixed) und rechts davon die Hauptspalte mit Symbolleiste, grossem Titel und Inhalt --}}
        <div class="np-hauptspalte">
            @include('layouts.navigation')

            @if(Route::has('system-notice.dismiss') && ($systemhinweis ?? null))
                @include('layouts._systemhinweis')
            @endif

            {{-- Der Tipp nennt den Feedback-Knopf in der Symbolleiste, wenn er eingeschaltet ist --}}
            @php
                $feedbackKnopfAktiv = auth()->check() && \App\Support\Einstellungen::get(\App\Support\Einstellungen::FEEDBACK_KNOPF, '1') !== '0';
            @endphp
            @if(\App\Http\Controllers\FeedbackController::hinweisOffen())
                @include('layouts._feedback-tipp')
            @endif

            {{-- Grosser Titel im selben Container wie der Inhalt: eine bündige Achse --}}
            @isset($header)
                <div class="mx-auto w-full np-seite px-8 pt-2">
                    {{ $header }}
                </div>
            @endisset

            <main id="inhalt" tabindex="-1" class="flex-1 pb-10 focus:outline-none">
                {{ $slot }}
            </main>
        </div>

        {{-- Mitteilungen (Toast) oben rechts unter der Symbolleiste, übereinander: höchstens drei gleichzeitig (Erfolg,
             Fehler aus der Sitzung, Meldung aus JS). Flash aus der Sitzung steht serverseitig im Markup. --}}
        @php
            $flashSuccess = session('success') ?? session('status');
            $flashError   = session('error');
        @endphp
        <div class="pointer-events-none fixed right-4 top-[calc(var(--np-symbolleiste-hoehe)+0.5rem)] z-[60] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-2 print:hidden">
            @if($flashSuccess)
                <x-toast art="erfolg" :meldung="$flashSuccess" />
            @endif
            @if($flashError)
                <x-toast art="fehler" :meldung="$flashError" />
            @endif

            {{-- JS-ausgelöster Toast: window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: '...', art: 'fehler' } })), art optional --}}
            <x-toast art="erfolg" />
        </div>

        <x-bestaetigung />

        @auth
            @include('layouts._sitzung')
        @endauth

        <script>
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

                document.addEventListener('submit', (e) => { if (!e.defaultPrevented) start(); });

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