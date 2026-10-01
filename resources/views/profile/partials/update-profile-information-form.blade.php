{{-- Profil und persönliche Darstellung als gruppierte Einstellungsliste (macOS Systemeinstellungen).
     Die Darstellung wirkt sofort als Vorschau (anwenden()), gespeichert wird mit «Speichern». --}}
@php
    $gruppe = 'np-karte np-gruppe';
    $kopf = 'mb-2 px-1 text-sm font-semibold text-text';
    $feld = 'np-feld w-72';
@endphp
<form method="POST" action="{{ route('profile.update') }}" class="flex flex-col gap-8"
      x-data="{ loading: false, email: @js(old('email', $user->email)), original: @js($user->email) }"
      @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @method('patch')

    <section>
        <h2 class="{{ $kopf }}">{{ __('Konto') }}</h2>
        <div class="{{ $gruppe }}">
            @if($lernender)
                <x-einstellung :label="__('Name')">
                    <span class="text-sm text-muted">{{ $user->vorname }} {{ $user->nachname }}</span>
                </x-einstellung>
            @else
                <x-einstellung :label="__('Vorname')" fuer="vorname" name="vorname">
                    <input id="vorname" name="vorname" type="text" value="{{ old('vorname', $user->vorname) }}" required autocomplete="given-name"
                           class="{{ $feld }}" @error('vorname') aria-invalid="true" @enderror>
                </x-einstellung>
                <x-einstellung :label="__('Nachname')" fuer="nachname" name="nachname">
                    <input id="nachname" name="nachname" type="text" value="{{ old('nachname', $user->nachname) }}" required autocomplete="family-name"
                           class="{{ $feld }}" @error('nachname') aria-invalid="true" @enderror>
                </x-einstellung>
            @endif
            <x-einstellung :label="__('E-Mail')" fuer="email" name="email">
                <input id="email" name="email" type="email" x-model="email" required autocomplete="email"
                       class="{{ $feld }}" @error('email') aria-invalid="true" @enderror>
            </x-einstellung>
            <div x-show="email !== original" x-cloak>
                <x-einstellung :label="__('Aktuelles Passwort')" :hinweis="__('Zur Bestätigung der neuen Adresse')" fuer="current_password" name="current_password">
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                           class="{{ $feld }}" @error('current_password') aria-invalid="true" @enderror>
                </x-einstellung>
            </div>
            @if($lernender)
                <x-einstellung :label="__('Klasse Berufsfachschule')" fuer="klasse_schule" name="klasse_schule">
                    <input id="klasse_schule" name="klasse_schule" type="text" maxlength="30"
                           value="{{ old('klasse_schule', $lernender->klasse_schule) }}" placeholder="{{ __('z. B. INF24b') }}"
                           class="{{ $feld }}" @error('klasse_schule') aria-invalid="true" @enderror>
                </x-einstellung>
                @if($bmsAktiv)
                    <x-einstellung :label="__('Klasse BMS')" fuer="klasse_bms" name="klasse_bms">
                        <input id="klasse_bms" name="klasse_bms" type="text" maxlength="30"
                               value="{{ old('klasse_bms', $lernender->klasse_bms) }}"
                               class="{{ $feld }}" @error('klasse_bms') aria-invalid="true" @enderror>
                    </x-einstellung>
                @endif
            @endif
        </div>
    </section>

    @if($praeferenzenOption ?? false)
        @php
            // Abgewiesene Werte (Validierungsfehler) zeigen wieder die gespeicherte Wahl statt gar keine
            $altTheme = old('theme', $praeferenzen['theme'] ?? '') ?: null;
            $altTheme = $altTheme === null || array_key_exists($altTheme, \App\Support\Theme::THEMES) ? $altTheme : ($praeferenzen['theme'] ?? null);
            $altAkzent = old('akzent', $praeferenzen['akzent'] ?? '') ?: null;
            $altAkzent = $altAkzent === null || array_key_exists($altAkzent, \App\Support\Darstellung::AKZENTE) || $altAkzent === 'eigen' ? $altAkzent : ($praeferenzen['akzent'] ?? null);
            $altSchrift = old('schrift', $praeferenzen['schrift'] ?? 'normal');
            $altSchrift = in_array($altSchrift, \App\Support\Darstellung::SCHRIFTGROESSEN, true) ? $altSchrift : ($praeferenzen['schrift'] ?? 'normal');
            $altDichte = old('dichte', $praeferenzen['dichte'] ?? 'normal');
            $altDichte = in_array($altDichte, \App\Support\Darstellung::DICHTEN, true) ? $altDichte : ($praeferenzen['dichte'] ?? 'normal');
            $altDiagramm = old('diagramm', $praeferenzen['diagramm'] ?? 'standard');
            $altDiagramm = in_array($altDiagramm, \App\Support\Darstellung::DIAGRAMME, true) ? $altDiagramm : ($praeferenzen['diagramm'] ?? 'standard');
            $altNotenanzeige = old('notenanzeige', $praeferenzen['notenanzeige'] ?? '1');
            $altNotenanzeige = in_array($altNotenanzeige, \App\Support\Darstellung::NOTENANZEIGEN, true) ? $altNotenanzeige : ($praeferenzen['notenanzeige'] ?? '1');
            $altBewegungReduziert = (bool) old('bewegung_reduziert', ($praeferenzen['bewegung'] ?? 'normal') === 'reduziert');
            $altAkzentEigen = old('akzent_eigen', $praeferenzen['akzent_eigen'] ?? '') ?: null;
            $altAkzentEigen = \App\Support\Farbe::istGueltigerHex($altAkzentEigen) ? strtolower($altAkzentEigen) : ($praeferenzen['akzent_eigen'] ?? null);
            $altSchriftart = old('schriftart', $praeferenzen['schriftart'] ?? 'standard');
            $altSchriftart = in_array($altSchriftart, \App\Support\Darstellung::SCHRIFTARTEN, true) ? $altSchriftart : ($praeferenzen['schriftart'] ?? 'standard');
            $altEcken = old('ecken', $praeferenzen['ecken'] ?? 'rund');
            $altEcken = in_array($altEcken, \App\Support\Darstellung::ECKEN, true) ? $altEcken : ($praeferenzen['ecken'] ?? 'rund');
            $altTransparenz = old('transparenz', $praeferenzen['transparenz'] ?? 'normal');
            $altTransparenz = in_array($altTransparenz, \App\Support\Darstellung::TRANSPARENZEN, true) ? $altTransparenz : ($praeferenzen['transparenz'] ?? 'normal');
            $altTastenkuerzel = old('tastenkuerzel', $praeferenzen['tastenkuerzel'] ?? 'an');
            $altTastenkuerzel = in_array($altTastenkuerzel, \App\Support\Darstellung::TASTENKUERZEL, true) ? $altTastenkuerzel : ($praeferenzen['tastenkuerzel'] ?? 'an');
            $altNavigation = old('navigation', $praeferenzen['navigation'] ?? 'seite');
            $altNavigation = in_array($altNavigation, \App\Support\Darstellung::NAVIGATIONEN, true) ? $altNavigation : ($praeferenzen['navigation'] ?? 'seite');
            $startseitenOptionen = \App\Support\Darstellung::STARTSEITEN[$dashboardRolle ?? null] ?? null;
            $altStartseite = old('startseite', $praeferenzen['startseite'] ?? 'dashboard');
            $altStartseite = $startseitenOptionen && array_key_exists($altStartseite, $startseitenOptionen) ? $altStartseite : ($praeferenzen['startseite'] ?? 'dashboard');
            $swatch = 'block size-6 rounded-full ring-1 ring-inset ring-border-strong/40';
            $swatchWahl = 'flex cursor-pointer rounded-full p-0.5 ring-2 ring-transparent transition-shadow duration-150 has-checked:ring-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';
        @endphp
        <div class="flex flex-col gap-8"
             x-data="{
                 theme: @js($altTheme ?? ''),
                 akzent: @js($altAkzent ?? ''),
                 akzentEigen: @js($altAkzentEigen ?? '#2563eb'),
                 schrift: @js($altSchrift),
                 schriftart: @js($altSchriftart),
                 dichte: @js($altDichte),
                 diagramm: @js($altDiagramm),
                 ecken: @js($altEcken),
                 transparenz: @js($altTransparenz),
                 bewegungReduziert: @js($altBewegungReduziert),
                 dunkel: document.documentElement.classList.contains('dark'),
                 betriebTheme: @js($betriebTheme),
                 effektivTheme() { return this.theme || this.betriebTheme; },
                 {{-- Vorschau der eigenen Farbe: sofort sichtbar (Farbton), aber ohne die
                      serverseitige Kontrastgarantie (App\Support\Farbe) – die gilt erst nach dem
                      Speichern/Neuladen über die vom Server gerenderte <x-akzent-eigen-stil>.
                      Bewusste Vereinfachung, siehe docs/audit-backlog.md «Persönliche Darstellung II». --}}
                 hexZuRgb(hex) {
                     const m = /^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i.exec(hex || '');
                     return m ? [parseInt(m[1], 16), parseInt(m[2], 16), parseInt(m[3], 16)].join(' ') : null;
                 },
                 anwenden() {
                     const d = document.documentElement.dataset;
                     d.theme = this.effektivTheme();
                     if (this.effektivTheme() === 'kontrast' || !this.akzent) {
                         delete d.akzent;
                         document.documentElement.style.removeProperty('--accent');
                     } else if (this.akzent === 'eigen') {
                         d.akzent = 'eigen';
                         const rgb = this.hexZuRgb(this.akzentEigen);
                         if (rgb) document.documentElement.style.setProperty('--accent', rgb);
                     } else {
                         d.akzent = this.akzent;
                         document.documentElement.style.removeProperty('--accent');
                     }
                     const setze = (schluessel, wert, standard) => { if (wert === standard) delete d[schluessel]; else d[schluessel] = wert; };
                     setze('schrift', this.schrift, 'normal');
                     setze('schriftart', this.schriftart, 'standard');
                     setze('dichte', this.dichte, 'normal');
                     setze('diagramm', this.diagramm, 'standard');
                     setze('ecken', this.ecken, 'rund');
                     setze('transparenz', this.transparenz, 'normal');
                     setze('bewegung', this.bewegungReduziert ? 'reduziert' : 'normal', 'normal');
                 },
             }"
             x-init="new MutationObserver(() => dunkel = document.documentElement.classList.contains('dark'))
                         .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })">

            <section>
                <h2 class="{{ $kopf }}">{{ __('Darstellung') }}</h2>
                <div class="{{ $gruppe }}">
                    <x-einstellung :label="__('Erscheinungsbild')" name="darstellung">
                        <x-segment-auswahl name="darstellung" :wert="old('darstellung', $user->darstellung)"
                                           :optionen="['system' => __('Wie Gerät'), 'hell' => __('Hell'), 'dunkel' => __('Dunkel')]" />
                    </x-einstellung>

                    {{-- Farbthema: Miniaturen wie «Erscheinungsbild» in den Systemeinstellungen --}}
                    <div class="px-4 py-3">
                        <span id="theme-bez" class="text-sm text-text">{{ __('Farbthema') }}</span>
                        <div class="mt-3 grid grid-cols-5 gap-x-3 gap-y-4" role="radiogroup" aria-labelledby="theme-bez">
                            <label class="group cursor-pointer text-center">
                                <input type="radio" name="theme" value="" class="peer sr-only" x-model="theme" @change="anwenden()" @checked($altTheme === null)>
                                <x-theme-vorschau :theme="$betriebTheme" x-bind:class="{ 'dark': dunkel }"
                                                  class="ring-2 ring-transparent ring-offset-2 ring-offset-card transition-shadow duration-150 peer-checked:ring-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-ring" />
                                <span class="mt-1.5 block truncate text-xs text-muted peer-checked:font-medium peer-checked:text-text">{{ __('Wie Betrieb (:name)', ['name' => \App\Support\Theme::THEMES[$betriebTheme] ?? $betriebTheme]) }}</span>
                            </label>
                            @foreach(\App\Support\Theme::THEMES as $wert => $name)
                                <label class="group cursor-pointer text-center">
                                    <input type="radio" name="theme" value="{{ $wert }}" class="peer sr-only" x-model="theme" @change="anwenden()" @checked($altTheme === $wert)>
                                    <x-theme-vorschau :theme="$wert" x-bind:class="{ 'dark': dunkel }"
                                                      class="ring-2 ring-transparent ring-offset-2 ring-offset-card transition-shadow duration-150 peer-checked:ring-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-ring" />
                                    <span class="mt-1.5 block truncate text-xs text-muted peer-checked:font-medium peer-checked:text-text">{{ $name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('theme')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    {{-- Akzentfarbe: ohne Wirkung beim Theme «Kontrast» --}}
                    <x-einstellung :label="__('Akzentfarbe')" name="akzent" :fehler="['akzent', 'akzent_eigen']" x-bind:class="{ 'opacity-40': effektivTheme() === 'kontrast' }">
                        <div class="flex items-center gap-1.5" role="radiogroup" aria-labelledby="akzent-bez">
                            <label class="{{ $swatchWahl }}" title="{{ __('Theme-Farbe') }}">
                                <input type="radio" name="akzent" value="" class="sr-only" x-model="akzent" @change="anwenden()"
                                       x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === null)>
                                <span x-bind:class="{ 'dark': dunkel }" class="{{ $swatch }} bg-accent"></span>
                                <span class="sr-only">{{ __('Theme-Farbe') }}</span>
                            </label>
                            @foreach(\App\Support\Darstellung::AKZENTE as $wert => $name)
                                <label class="{{ $swatchWahl }}" title="{{ __($name) }}">
                                    <input type="radio" name="akzent" value="{{ $wert }}" class="sr-only" x-model="akzent" @change="anwenden()"
                                           x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === $wert)>
                                    <span data-akzent="{{ $wert }}" x-bind:class="{ 'dark': dunkel }" class="{{ $swatch }} bg-accent"></span>
                                    <span class="sr-only">{{ __($name) }}</span>
                                </label>
                            @endforeach
                            <label class="{{ $swatchWahl }}" title="{{ __('Eigene Farbe') }}">
                                <input type="radio" name="akzent" value="eigen" class="sr-only" x-model="akzent" @change="anwenden()"
                                       x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === 'eigen')>
                                <input type="color" name="akzent_eigen" x-model="akzentEigen" @input="akzent = 'eigen'; anwenden()"
                                       x-bind:disabled="effektivTheme() === 'kontrast'"
                                       class="{{ $swatch }} cursor-pointer border-0 bg-transparent p-0"
                                       aria-label="{{ __('Eigene Farbe wählen') }}">
                            </label>
                        </div>
                    </x-einstellung>

                    <x-einstellung :label="__('Schriftgrösse')" name="schrift">
                        <x-segment-auswahl name="schrift" :wert="$altSchrift" x-model="schrift" x-on:change="anwenden()"
                                           :optionen="['normal' => __('Normal'), 'gross' => __('Gross'), 'sehr-gross' => __('Sehr gross')]" />
                    </x-einstellung>
                    {{-- Schriftart: reine System-Schriftstapel (kein Fremdladen) --}}
                    <x-einstellung :label="__('Schriftart')" name="schriftart">
                        <x-segment-auswahl name="schriftart" :wert="$altSchriftart" x-model="schriftart" x-on:change="anwenden()"
                                           :optionen="['standard' => __('Standard'), 'serif' => __('Serif'), 'lesefreundlich' => __('Lesefreundlich')]" />
                    </x-einstellung>
                    {{-- Dichte: Tabellenzeilen, Karten-Padding und -Abstände --}}
                    <x-einstellung :label="__('Dichte')" name="dichte">
                        <x-segment-auswahl name="dichte" :wert="$altDichte" x-model="dichte" x-on:change="anwenden()"
                                           :optionen="['normal' => __('Normal'), 'kompakt' => __('Kompakt')]" />
                    </x-einstellung>
                    {{-- Ecken: Rundungen der Flächen (Karten, Buttons, Felder) --}}
                    <x-einstellung :label="__('Ecken')" name="ecken">
                        <x-segment-auswahl name="ecken" :wert="$altEcken" x-model="ecken" x-on:change="anwenden()"
                                           :optionen="['rund' => __('Rund'), 'eckig' => __('Eckig')]" />
                    </x-einstellung>
                    {{-- Diagrammfarben: «Farbenblind» ersetzt nur --chart-1..6 (Okabe-Ito-Palette) --}}
                    <x-einstellung :label="__('Diagrammfarben')" name="diagramm">
                        <x-segment-auswahl name="diagramm" :wert="$altDiagramm" x-model="diagramm" x-on:change="anwenden()"
                                           :optionen="['standard' => __('Standard'), 'farbenblind' => __('Farbenblind')]" />
                    </x-einstellung>
                    {{-- Notenanzeige: Nachkommastellen der Durchschnitte auf interaktiven Seiten (<x-note>);
                         Notenblatt/Exporte/Mails bleiben unverändert, daher keine Live-Vorschau. --}}
                    <x-einstellung :label="__('Notenanzeige')" name="notenanzeige"
                                   :hinweis="__('Nachkommastellen bei Notendurchschnitten (nur Anzeige, nicht bei Notenblatt und Exporten).')">
                        <x-segment-auswahl name="notenanzeige" :wert="$altNotenanzeige"
                                           :optionen="['1' => __('1 Nachkommastelle'), '2' => __('2 Nachkommastellen')]" />
                    </x-einstellung>
                </div>
            </section>

            <section>
                <h2 class="{{ $kopf }}">{{ __('Bedienung') }}</h2>
                <div class="{{ $gruppe }}">
                    {{-- Navigation: Seitenleiste (Standard) oder Tableiste oben --}}
                    <x-einstellung :label="__('Navigation')" name="navigation">
                        <x-segment-auswahl name="navigation" :wert="$altNavigation"
                                           :optionen="['seite' => __('Seitenleiste'), 'oben' => __('Oben')]" />
                    </x-einstellung>
                    @if($startseitenOptionen)
                        {{-- Startseite: nur die für die eigene Rolle gültigen Ziele (App\Support\Darstellung::STARTSEITEN) --}}
                        <x-einstellung :label="__('Startseite')" name="startseite"
                                       :hinweis="__('Ziel nach der Anmeldung, sofern kein Link direkt auf eine andere Seite führte.')">
                            <x-segment-auswahl name="startseite" :wert="$altStartseite"
                                               :optionen="collect($startseitenOptionen)->mapWithKeys(fn ($_, $w) => [$w => __(\App\Support\Darstellung::STARTSEITE_BEZEICHNUNG[$w] ?? $w)])->all()" />
                        </x-einstellung>
                    @endif
                    {{-- Transparenz: schaltet Blur auf Leiste und Overlay ab (deckende Fläche) --}}
                    <x-einstellung :label="__('Transparenz reduzieren')" fuer="transparenz" name="transparenz">
                        <input type="hidden" name="transparenz" value="normal">
                        <input id="transparenz" type="checkbox" role="switch" name="transparenz" value="reduziert" class="np-schalter"
                               x-bind:checked="transparenz === 'reduziert'" @change="transparenz = $event.target.checked ? 'reduziert' : 'normal'; anwenden()"
                               @checked($altTransparenz === 'reduziert')>
                    </x-einstellung>
                    <x-einstellung :label="__('Bewegungen reduzieren')" fuer="bewegung_reduziert" name="bewegung_reduziert">
                        <input id="bewegung_reduziert" name="bewegung_reduziert" type="checkbox" role="switch" value="1" x-model="bewegungReduziert" @change="anwenden()"
                               class="np-schalter">
                    </x-einstellung>
                    {{-- Tastenkürzel: schaltet sowohl den Dialog als auch dessen Listener ab. Das versteckte Feld liefert
                         «aus», das Häkchen überschreibt es mit «an» (gleicher Name, das letzte Feld gewinnt). --}}
                    <x-einstellung :label="__('Tastenkürzel')" fuer="tastenkuerzel" name="tastenkuerzel">
                        @if($altTastenkuerzel === 'an')
                            <button type="button" x-data x-on:click="$dispatch('open-tastenkuerzel')"
                                    class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Anzeigen') }}</button>
                        @endif
                        <input type="hidden" name="tastenkuerzel" value="aus">
                        <input id="tastenkuerzel" type="checkbox" role="switch" name="tastenkuerzel" value="an" class="np-schalter"
                               @checked($altTastenkuerzel === 'an')>
                    </x-einstellung>
                </div>
            </section>

            {{-- Übersicht: Dashboard-Karten der eigenen Rolle ein-/ausblenden (App\Support\DashboardKarten) --}}
            @if(($dashboardRolle ?? null) && ($dashboardKarten ?? []))
                @php
                    $kartenStandardSichtbar = array_values(array_diff(array_keys($dashboardKarten), $praeferenzen['karten_ausgeblendet'] ?? []));
                    $altKartenSichtbar = (array) old('karten', $kartenStandardSichtbar);
                @endphp
                <section>
                    <h2 class="{{ $kopf }}">{{ __('Übersicht') }}</h2>
                    <input type="hidden" name="karten_uebermittelt" value="1">
                    <div class="{{ $gruppe }}">
                        @foreach($dashboardKarten as $schluessel => $bezeichnung)
                            <x-einstellung :label="__($bezeichnung)" :fuer="'karte-'.$schluessel">
                                <input id="karte-{{ $schluessel }}" type="checkbox" role="switch" name="karten[]" value="{{ $schluessel }}" class="np-schalter"
                                       aria-label="{{ __(':karte anzeigen', ['karte' => __($bezeichnung)]) }}"
                                       @checked(in_array($schluessel, $altKartenSichtbar, true))>
                            </x-einstellung>
                        @endforeach
                    </div>
                    <p class="mt-2 px-1 text-xs text-muted">{{ __('Karten auf dem Dashboard ein- oder ausblenden.') }}</p>
                    @error('karten')<p class="mt-1 px-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    @error('karten.*')<p class="mt-1 px-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </section>
            @endif
        </div>
    @else
        <section>
            <h2 class="{{ $kopf }}">{{ __('Darstellung') }}</h2>
            <div class="{{ $gruppe }}">
                <x-einstellung :label="__('Erscheinungsbild')" name="darstellung">
                    <x-segment-auswahl name="darstellung" :wert="old('darstellung', $user->darstellung)"
                                       :optionen="['system' => __('Wie Gerät'), 'hell' => __('Hell'), 'dunkel' => __('Dunkel')]" />
                </x-einstellung>
                @if($kontrastOption ?? false)
                    <x-einstellung :label="__('Hoher Kontrast')" fuer="kontrast" name="kontrast">
                        <input id="kontrast" name="kontrast" type="checkbox" role="switch" value="1" @checked(old('kontrast', $user->kontrast))
                               class="np-schalter">
                    </x-einstellung>
                @endif
            </div>
        </section>
    @endif

    <div class="flex items-center justify-end gap-2">
        @if($praeferenzenOption ?? false)
            {{-- gehört zum eigenen Formular unten (keine verschachtelten <form>), steht aber in derselben Aktionszeile --}}
            <button type="submit" form="praeferenzen-zuruecksetzen" class="np-knopf np-knopf-schlicht mr-auto">
                {{ __('Auf Standard zurücksetzen') }}
            </button>
        @endif
        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer min-w-24">
            {{ __('Speichern') }}
        </button>
    </div>
</form>

@if($praeferenzenOption ?? false)
    <form id="praeferenzen-zuruecksetzen" method="POST" action="{{ route('profile.preferences.reset') }}" class="hidden"
          data-bestaetigen="{{ __('Darstellung auf Standard zurücksetzen?') }}" data-bestaetigen-knopf="{{ __('Zurücksetzen') }}">
        @csrf
        @method('delete')
    </form>
@endif
