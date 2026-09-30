<x-app-layout>
    <x-slot name="title">{{ __('Modulkatalog einlesen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Modulkatalog einlesen')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.modules.index') }}"
                   class="np-knopf np-knopf-sekundaer">
                    {{ __('Zur Modulliste') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $feld = 'np-feld mt-1';
        $karte = 'np-karte p-6 flex flex-col gap-4';
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            @if(! $bereit)
                <section class="{{ $karte }}">
                    <h2 class="text-sm font-semibold text-text">{{ __('Noch nicht bereit') }}</h2>
                    <p class="text-sm text-muted">
                        {{ __('Die Katalogspalten fehlen in der Datenbank. Einmalig auf dem Server ausführen:') }}
                    </p>
                    <code class="rounded-lg bg-surface-2 px-3 py-2 font-mono text-sm text-text">php artisan notenportal:migrate</code>
                </section>
            @elseif($vorschau === null)
                <section class="{{ $karte }}">
                    <h2 class="text-sm font-semibold text-text">{{ __('Ernte hochladen') }}</h2>
                    <p class="text-sm text-muted">
                        {{ __('Eine Ernte ist eine JSON-Datei mit Modulnummern, Titeln, Versionen, Handlungszielen und der Zuordnung Abschluss zu Modul. Erzeugt wird sie mit dem Werkzeug tools/modulkatalog-ernte.mjs; der Aufbau steht in docs/modulkatalog.md.') }}
                    </p>

                    <form method="POST" action="{{ route('admin.master-data.modules.catalog.read') }}"
                          enctype="multipart/form-data" class="flex flex-col gap-4"
                          x-data="{ laeuft: false }" @submit="laeuft = true">
                        @csrf

                        <div>
                            <label for="datei" class="text-sm font-medium text-text">{{ __('Datei') }} *</label>
                            <x-datei-feld id="datei" name="datei" accept=".json,application/json" required />
                            @error('datei')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>

                        <label class="flex items-start gap-2.5 text-sm text-text">
                            <input type="checkbox" name="ohne_berufe" value="1" @checked(old('ohne_berufe'))
                                   class="np-haken mt-0.5">
                            <span>
                                {{ __('Keine neuen Lehrberufe anlegen') }}
                                <span class="block text-xs text-muted">{{ __('Module werden dann nur bestehenden Lehrberufen zugeordnet.') }}</span>
                            </span>
                        </label>

                        <label class="flex items-start gap-2.5 text-sm text-text">
                            <input type="checkbox" name="eigene_uebernehmen" value="1" @checked(old('eigene_uebernehmen'))
                                   class="np-haken mt-0.5">
                            <span>
                                {{ __('Eigene Module überschreiben') }}
                                <span class="block text-xs text-muted">{{ __('Sonst bleiben selbst erfasste Module mit derselben Nummer unberührt.') }}</span>
                            </span>
                        </label>

                        <div>
                            <button type="submit" x-bind:disabled="laeuft"
                                    class="np-knopf np-knopf-primaer np-knopf-gross">
                                {{ __('Vorschau erstellen') }}
                            </button>
                        </div>
                        <p class="text-xs text-muted">{{ __('Der Upload schreibt noch nichts. Erst die Vorschau zeigt, was sich ändern würde.') }}</p>
                    </form>
                </section>
            @else
                @php $zahlen = $vorschau['zahlen']; @endphp

                <section class="{{ $karte }}">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 class="text-sm font-semibold text-text">{{ __('Vorschau') }}</h2>
                        <p class="text-xs text-muted">
                            {{ $vorschau['name'] }} · {{ __('Ernte vom') }} {{ $vorschau['stand'] ?? __('unbekannt') }}
                        </p>
                    </div>

                    <div class="overflow-x-auto px-2 pb-2">
                        <table class="np-tabelle text-sm">
                            <caption class="sr-only">{{ __('Was der Import schreiben würde') }}</caption>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Was') }}</th>
                                    <th scope="col" class="text-right">{{ __('Anzahl') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach([
                                    __('Module neu') => $zahlen['module_neu'],
                                    __('Module aktualisiert') => $zahlen['module_alt'],
                                    __('Handlungsziele') => $zahlen['ziele'],
                                    __('LBV-Elemente') => $zahlen['lbv'],
                                    __('Abschlüsse') => $zahlen['abschluesse'],
                                    __('Lehrberufe neu') => $zahlen['berufe_neu'],
                                    __('Zuordnungen Beruf zu Modul') => $zahlen['zuordnungen'],
                                    __('Eigene Module übersprungen') => $zahlen['konflikte'],
                                ] as $was => $anzahl)
                                    <tr>
                                        <td class="text-text">{{ $was }}</td>
                                        <td class="text-right text-text">{{ $anzahl }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>

                @if($vorschau['konflikte'] !== [])
                    <section class="{{ $karte }}">
                        <h2 class="text-sm font-semibold text-text">{{ __('Eigene Module bleiben unberührt') }}</h2>
                        <p class="text-sm text-muted">{{ __('Diese Nummern stehen im Portal bereits als eigenes Modul:') }}</p>
                        <ul class="flex flex-col gap-1 text-sm">
                            @foreach($vorschau['konflikte'] as $nummer => $titel)
                                <li class="flex gap-3 min-w-0">
                                    <span class="font-mono text-muted w-16 shrink-0">{{ $nummer }}</span>
                                    <span class="truncate text-text">{{ $titel }}</span>
                                </li>
                            @endforeach
                        </ul>
                        @if($zahlen['konflikte'] > count($vorschau['konflikte']))
                            <p class="text-xs text-muted">{{ __('… und :rest weitere.', ['rest' => $zahlen['konflikte'] - count($vorschau['konflikte'])]) }}</p>
                        @endif
                        <p class="text-xs text-muted">
                            {{ __('Entweder dem eigenen Modul eine eigene Nummer geben (etwa ABU01 statt 801) oder oben «Eigene Module überschreiben» wählen, wenn es dieselben Module sind.') }}
                        </p>
                    </section>
                @endif

                @if($vorschau['berufe_neu'] !== [])
                    <section class="{{ $karte }}">
                        <h2 class="text-sm font-semibold text-text">
                            {{ $vorschau['ohne_berufe'] ? __('Übersprungene Lehrberufe') : __('Neue Lehrberufe') }}
                        </h2>
                        <p class="text-sm text-text">{{ implode(', ', $vorschau['berufe_neu']) }}</p>
                        @if($vorschau['ohne_berufe'])
                            <p class="text-xs text-muted">{{ __('Diese Lehrberufe gibt es im Portal nicht und sie werden auf Wunsch nicht angelegt – ihre Module bleiben ohne Zuordnung.') }}</p>
                        @endif
                    </section>
                @endif

                @if($vorschau['fehler'] !== [] || $vorschau['ohne_lernort'])
                    <section class="{{ $karte }}">
                        <h2 class="text-sm font-semibold text-text">{{ __('Hinweise') }}</h2>
                        <ul class="flex flex-col gap-1 text-sm text-muted">
                            @if($vorschau['ohne_lernort'])
                                <li>{{ __('Kein Lernort erfasst – neue Zuordnungen bleiben ohne Lernort.') }}</li>
                            @endif
                            @foreach($vorschau['fehler'] as $hinweis)
                                <li>{{ $hinweis }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="{{ $karte }}">
                    <h2 class="text-sm font-semibold text-text">{{ __('Übernehmen') }}</h2>
                    <p class="text-sm text-muted">
                        {{ __('Bestehende Module mit derselben Nummer werden aktualisiert, nichts wird gelöscht. Vor dem ersten grossen Import lohnt sich eine Sicherung.') }}
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <form method="POST" action="{{ route('admin.master-data.modules.catalog.apply') }}"
                              x-data="{ laeuft: false }" @submit="laeuft = true">
                            @csrf
                            <input type="hidden" name="token" value="{{ $vorschau['token'] }}">
                            <button type="submit" x-bind:disabled="laeuft"
                                    class="np-knopf np-knopf-primaer np-knopf-gross">
                                {{ __('Katalog übernehmen') }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.master-data.modules.catalog.discard') }}">
                            @csrf
                            <button type="submit" class="np-knopf np-knopf-sekundaer np-knopf-gross">
                                {{ __('Verwerfen') }}
                            </button>
                        </form>
                    </div>
                </section>
            @endif

            @if($katalogModule > 0)
                <section class="{{ $karte }}">
                    <h2 class="text-sm font-semibold text-text">{{ __('Katalog weitergeben') }}</h2>
                    <p class="text-sm text-muted">
                        {{ __('Diese Instanz hat :anzahl Module aus dem Katalog. Der Download erzeugt genau die Datei, die oben wieder eingelesen werden kann – so kommt eine zweite Installation ohne neue Ernte zum selben Stand.', ['anzahl' => $katalogModule]) }}
                    </p>
                    <div>
                        <a href="{{ route('admin.master-data.modules.catalog.export') }}"
                           class="np-knopf np-knopf-sekundaer np-knopf-gross">
                            {{ __('Katalog herunterladen') }}
                        </a>
                    </div>
                    <p class="text-xs text-muted">
                        {{ __('Selbst erfasste Module sind nicht dabei; dafür gibt es auf dem Server notenportal:modulkatalog-export --mit-eigenen.') }}
                    </p>
                </section>
            @endif

        </div>
    </div>
</x-app-layout>
