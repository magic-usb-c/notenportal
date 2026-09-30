<section>
    <h2 class="text-base font-semibold text-text">{{ __('Profildaten') }}</h2>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-5 flex flex-col gap-4"
          x-data="{ loading: false, email: @js(old('email', $user->email)), original: @js($user->email) }"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('patch')

        @if($lernender)
            <div>
                <span class="text-sm font-medium text-text">{{ __('Name') }}</span>
                <p class="mt-1 text-text">{{ $user->vorname }} {{ $user->nachname }}</p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="vorname" class="text-sm font-medium text-text">{{ __('Vorname') }} *</label>
                    <input id="vorname" name="vorname" type="text"
                           value="{{ old('vorname', $user->vorname) }}" required autocomplete="given-name"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-note-ungenuegend! @enderror">
                    @error('vorname')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="nachname" class="text-sm font-medium text-text">{{ __('Nachname') }} *</label>
                    <input id="nachname" name="nachname" type="text"
                           value="{{ old('nachname', $user->nachname) }}" required autocomplete="family-name"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-note-ungenuegend! @enderror">
                    @error('nachname')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif

        <div>
            <label for="email" class="text-sm font-medium text-text">{{ __('E-Mail') }} *</label>
            <input id="email" name="email" type="email" x-model="email" required autocomplete="email"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-note-ungenuegend! @enderror">
            @error('email')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="email !== original" x-cloak>
            <label for="current_password" class="text-sm font-medium text-text">{{ __('Aktuelles Passwort') }} *</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('current_password') border-note-ungenuegend! @enderror">
            @error('current_password')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        @if($lernender)
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="klasse_schule" class="text-sm font-medium text-text">{{ __('Klasse Berufsfachschule') }}</label>
                    <input id="klasse_schule" name="klasse_schule" type="text" maxlength="30"
                           value="{{ old('klasse_schule', $lernender->klasse_schule) }}" placeholder="{{ __('z. B. INF24b') }}"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('klasse_schule') border-note-ungenuegend! @enderror">
                    @error('klasse_schule')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
                @if($bmsAktiv)
                    <div>
                        <label for="klasse_bms" class="text-sm font-medium text-text">{{ __('Klasse BMS') }}</label>
                        <input id="klasse_bms" name="klasse_bms" type="text" maxlength="30"
                               value="{{ old('klasse_bms', $lernender->klasse_bms) }}"
                               class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                      focus:ring-2 focus:ring-ring focus:border-ring @error('klasse_bms') border-note-ungenuegend! @enderror">
                        @error('klasse_bms')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>
        @endif

        <fieldset>
            <legend class="text-sm font-medium text-text">{{ __('Darstellung') }}</legend>
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach(['system' => __('Wie Gerät'), 'hell' => __('Hell'), 'dunkel' => __('Dunkel')] as $wert => $label)
                    <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                  has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                        <input type="radio" name="darstellung" value="{{ $wert }}" class="sr-only"
                               @checked(old('darstellung', $user->darstellung) === $wert)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            @if($praeferenzenOption ?? false)
                @php
                    // Abgewiesene Werte (Validierungsfehler) zeigen wieder die gespeicherte Wahl statt gar keine
                    $altTheme = old('theme', $praeferenzen['theme'] ?? '') ?: null;
                    $altTheme = $altTheme === null || array_key_exists($altTheme, \App\Support\Theme::THEMES) ? $altTheme : ($praeferenzen['theme'] ?? null);
                    $altAkzent = old('akzent', $praeferenzen['akzent'] ?? '') ?: null;
                    $altAkzent = $altAkzent === null || array_key_exists($altAkzent, \App\Support\Darstellung::AKZENTE) ? $altAkzent : ($praeferenzen['akzent'] ?? null);
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
                @endphp
                <div class="mt-5 flex flex-col gap-5"
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
                             document.documentElement.dataset.theme = this.effektivTheme();
                             if (this.effektivTheme() === 'kontrast' || !this.akzent) {
                                 delete document.documentElement.dataset.akzent;
                                 document.documentElement.style.removeProperty('--accent');
                             } else if (this.akzent === 'eigen') {
                                 document.documentElement.dataset.akzent = 'eigen';
                                 const rgb = this.hexZuRgb(this.akzentEigen);
                                 if (rgb) document.documentElement.style.setProperty('--accent', rgb);
                             } else {
                                 document.documentElement.dataset.akzent = this.akzent;
                                 document.documentElement.style.removeProperty('--accent');
                             }
                             if (this.schrift === 'normal') delete document.documentElement.dataset.schrift;
                             else document.documentElement.dataset.schrift = this.schrift;
                             if (this.schriftart === 'standard') delete document.documentElement.dataset.schriftart;
                             else document.documentElement.dataset.schriftart = this.schriftart;
                             if (this.dichte === 'normal') delete document.documentElement.dataset.dichte;
                             else document.documentElement.dataset.dichte = this.dichte;
                             if (this.diagramm === 'standard') delete document.documentElement.dataset.diagramm;
                             else document.documentElement.dataset.diagramm = this.diagramm;
                             if (this.ecken === 'rund') delete document.documentElement.dataset.ecken;
                             else document.documentElement.dataset.ecken = this.ecken;
                             if (this.transparenz === 'normal') delete document.documentElement.dataset.transparenz;
                             else document.documentElement.dataset.transparenz = this.transparenz;
                             if (this.bewegungReduziert) document.documentElement.dataset.bewegung = 'reduziert';
                             else delete document.documentElement.dataset.bewegung;
                         },
                     }"
                     x-init="new MutationObserver(() => dunkel = document.documentElement.classList.contains('dark'))
                                 .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })">

                    {{-- Theme --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Farbthema') }}</legend>
                        <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <label class="cursor-pointer rounded-xl border border-border p-2 transition-colors duration-100 hover:border-border-strong/60
                                          has-checked:border-accent has-checked:ring-2 has-checked:ring-accent/30 has-focus-visible:outline-2 has-focus-visible:outline-ring">
                                <input type="radio" name="theme" value="" class="sr-only" x-model="theme" @change="anwenden()" @checked($altTheme === null)>
                                <x-theme-vorschau :theme="$betriebTheme" x-bind:class="{ 'dark': dunkel }" />
                                <span class="mt-2 block px-0.5 text-sm font-medium text-text">{{ __('Wie Betrieb (:name)', ['name' => \App\Support\Theme::THEMES[$betriebTheme] ?? $betriebTheme]) }}</span>
                            </label>
                            @foreach(\App\Support\Theme::THEMES as $wert => $name)
                                <label class="cursor-pointer rounded-xl border border-border p-2 transition-colors duration-100 hover:border-border-strong/60
                                              has-checked:border-accent has-checked:ring-2 has-checked:ring-accent/30 has-focus-visible:outline-2 has-focus-visible:outline-ring">
                                    <input type="radio" name="theme" value="{{ $wert }}" class="sr-only" x-model="theme" @change="anwenden()" @checked($altTheme === $wert)>
                                    <x-theme-vorschau :theme="$wert" x-bind:class="{ 'dark': dunkel }" />
                                    <span class="mt-2 block px-0.5 text-sm font-medium text-text">{{ $name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('theme')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Akzentfarbe: ohne Wirkung beim Theme «Kontrast» --}}
                    <fieldset :class="{ 'opacity-40': effektivTheme() === 'kontrast' }">
                        <legend class="text-sm font-medium text-text">{{ __('Akzentfarbe') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-3">
                            <label class="flex cursor-pointer flex-col items-center gap-1.5 has-focus-visible:outline-2 has-focus-visible:outline-ring rounded-lg p-1">
                                <input type="radio" name="akzent" value="" class="sr-only" x-model="akzent" @change="anwenden()"
                                       x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === null)>
                                <span x-bind:class="{ 'dark': dunkel, 'ring-2 ring-accent ring-offset-2 ring-offset-bg': akzent === '' }"
                                      class="block h-8 w-8 rounded-full border border-border-strong/40 bg-accent"></span>
                                <span class="sr-only">{{ __('Theme-Farbe') }}</span>
                            </label>
                            @foreach(\App\Support\Darstellung::AKZENTE as $wert => $name)
                                <label class="flex cursor-pointer flex-col items-center gap-1.5 has-focus-visible:outline-2 has-focus-visible:outline-ring rounded-lg p-1">
                                    <input type="radio" name="akzent" value="{{ $wert }}" class="sr-only" x-model="akzent" @change="anwenden()"
                                           x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === $wert)>
                                    <span data-akzent="{{ $wert }}" x-bind:class="{ 'dark': dunkel, 'ring-2 ring-accent ring-offset-2 ring-offset-bg': akzent === @js($wert) }"
                                          class="block h-8 w-8 rounded-full border border-border-strong/40 bg-accent"></span>
                                    <span class="sr-only">{{ __($name) }}</span>
                                </label>
                            @endforeach
                            <label class="flex cursor-pointer flex-col items-center gap-1.5 has-focus-visible:outline-2 has-focus-visible:outline-ring rounded-lg p-1">
                                <input type="radio" name="akzent" value="eigen" class="sr-only" x-model="akzent" @change="anwenden()"
                                       x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === 'eigen')>
                                <span class="relative block h-8 w-8 rounded-full" x-bind:class="{ 'ring-2 ring-accent ring-offset-2 ring-offset-bg': akzent === 'eigen' }">
                                    <input type="color" name="akzent_eigen" x-model="akzentEigen" @input="akzent = 'eigen'; anwenden()"
                                           x-bind:disabled="effektivTheme() === 'kontrast'"
                                           class="block h-8 w-8 cursor-pointer rounded-full border border-border-strong/40 bg-transparent p-0"
                                           aria-label="{{ __('Eigene Farbe wählen') }}">
                                </span>
                                <span class="text-2xs text-muted">{{ __('Eigene Farbe') }}</span>
                            </label>
                        </div>
                        @error('akzent')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        @error('akzent_eigen')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Schriftgrösse --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Schriftgrösse') }}</legend>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            @foreach(['normal' => __('Normal'), 'gross' => __('Gross'), 'sehr-gross' => __('Sehr gross')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="schrift" value="{{ $wert }}" class="sr-only" x-model="schrift" @change="anwenden()"
                                           @checked($altSchrift === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('schrift')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Schriftart: reine System-Schriftstapel (kein Fremdladen) --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Schriftart') }}</legend>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            @foreach(['standard' => __('Standard'), 'serif' => __('Serif'), 'lesefreundlich' => __('Lesefreundlich')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="schriftart" value="{{ $wert }}" class="sr-only" x-model="schriftart" @change="anwenden()"
                                           @checked($altSchriftart === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('schriftart')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Dichte: Tabellenzeilen, Karten-Padding und -Abstände (Live-Vorschau wie Farbthema/Schrift) --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Dichte') }}</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['normal' => __('Normal'), 'kompakt' => __('Kompakt')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="dichte" value="{{ $wert }}" class="sr-only" x-model="dichte" @change="anwenden()"
                                           @checked($altDichte === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('dichte')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Diagrammfarben: «Farbenblind» ersetzt nur --chart-1..6 (Okabe-Ito-Palette) --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Diagrammfarben') }}</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['standard' => __('Standard'), 'farbenblind' => __('Farbenblind')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="diagramm" value="{{ $wert }}" class="sr-only" x-model="diagramm" @change="anwenden()"
                                           @checked($altDiagramm === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('diagramm')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Ecken: Rundungen der Flächen (Karten, Buttons, Felder) --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Ecken') }}</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['rund' => __('Rund'), 'eckig' => __('Eckig')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="ecken" value="{{ $wert }}" class="sr-only" x-model="ecken" @change="anwenden()"
                                           @checked($altEcken === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('ecken')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Transparenz: schaltet Liquid-Glass/Blur auf Leiste und Overlay ab (deckende Fläche) --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Transparenz') }}</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['normal' => __('Normal'), 'reduziert' => __('Reduziert')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="transparenz" value="{{ $wert }}" class="sr-only" x-model="transparenz" @change="anwenden()"
                                           @checked($altTransparenz === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('transparenz')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    <label for="bewegung_reduziert" class="flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
                        <input id="bewegung_reduziert" name="bewegung_reduziert" type="checkbox" value="1" x-model="bewegungReduziert" @change="anwenden()"
                               class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent focus:ring-2 focus:ring-ring/30">
                        {{ __('Bewegungen reduzieren') }}
                    </label>

                    {{-- Navigation: Seitenleiste (Standard) oder Tableiste oben; unter 1024 px ist die Seitenleiste eine Schublade --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Navigation') }}</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['seite' => __('Seitenleiste'), 'oben' => __('Oben')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="navigation" value="{{ $wert }}" class="sr-only" @checked($altNavigation === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('navigation')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Tastenkürzel: schaltet sowohl den Dialog als auch dessen Listener ab --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Tastenkürzel') }}</legend>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['an' => __('An'), 'aus' => __('Aus')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="tastenkuerzel" value="{{ $wert }}" class="sr-only"
                                           @checked($altTastenkuerzel === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('tastenkuerzel')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Notenanzeige: Nachkommastellen der Notendurchschnitte auf interaktiven Seiten
                         (<x-note>); Notenblatt/Exporte/Mails bleiben unverändert, daher keine Live-Vorschau. --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Notenanzeige') }}</legend>
                        <p class="mt-1 text-xs text-muted">{{ __('Nachkommastellen bei Notendurchschnitten (nur Anzeige, nicht bei Notenblatt und Exporten).') }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            @foreach(['1' => __('1 Nachkommastelle'), '2' => __('2 Nachkommastellen')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="notenanzeige" value="{{ $wert }}" class="sr-only"
                                           @checked($altNotenanzeige === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('notenanzeige')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Startseite: nur die für die eigene Rolle gültigen Ziele (App\Support\Darstellung::STARTSEITEN) --}}
                    @if($startseitenOptionen)
                        <fieldset>
                            <legend class="text-sm font-medium text-text">{{ __('Startseite') }}</legend>
                            <p class="mt-1 text-xs text-muted">{{ __('Ziel nach der Anmeldung, sofern kein Link direkt auf eine andere Seite führte.') }}</p>
                            <div class="mt-2 grid grid-cols-3 gap-2">
                                @foreach(array_keys($startseitenOptionen) as $wert)
                                    <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                                  has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                        <input type="radio" name="startseite" value="{{ $wert }}" class="sr-only"
                                               @checked($altStartseite === $wert)>
                                        {{ __(\App\Support\Darstellung::STARTSEITE_BEZEICHNUNG[$wert] ?? $wert) }}
                                    </label>
                                @endforeach
                            </div>
                            @error('startseite')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif

                    {{-- Übersicht: Dashboard-Karten der eigenen Rolle ein-/ausblenden (App\Support\DashboardKarten) --}}
                    @if(($dashboardRolle ?? null) && ($dashboardKarten ?? []))
                        @php
                            $kartenStandardSichtbar = array_values(array_diff(array_keys($dashboardKarten), $praeferenzen['karten_ausgeblendet'] ?? []));
                            $altKartenSichtbar = (array) old('karten', $kartenStandardSichtbar);
                        @endphp
                        <fieldset>
                            <legend class="text-sm font-medium text-text">{{ __('Übersicht') }}</legend>
                            <p class="mt-1 text-xs text-muted">{{ __('Karten auf dem Dashboard ein- oder ausblenden.') }}</p>
                            <input type="hidden" name="karten_uebermittelt" value="1">
                            <div class="mt-2 flex flex-col gap-1.5">
                                @foreach($dashboardKarten as $schluessel => $bezeichnung)
                                    <label class="flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
                                        <input type="checkbox" name="karten[]" value="{{ $schluessel }}"
                                               class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent focus:ring-2 focus:ring-ring/30"
                                               @checked(in_array($schluessel, $altKartenSichtbar, true))>
                                        {{ __(':karte anzeigen', ['karte' => __($bezeichnung)]) }}
                                    </label>
                                @endforeach
                            </div>
                            @error('karten')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                            @error('karten.*')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </fieldset>
                    @endif
                </div>
            @elseif($kontrastOption ?? false)
                <label for="kontrast" class="mt-3 flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
                    <input id="kontrast" name="kontrast" type="checkbox" value="1" @checked(old('kontrast', $user->kontrast))
                           class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent focus:ring-2 focus:ring-ring/30">
                    {{ __('Hoher Kontrast') }}
                </label>
            @endif
        </fieldset>

        <div class="pt-1">
            <button type="submit" :disabled="loading"
                    class="px-5 py-2 h-10 rounded-xl bg-accent text-accent-contrast font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                {{ __('Speichern') }}
            </button>
        </div>
    </form>

    @if($praeferenzenOption ?? false)
        <form method="POST" action="{{ route('profile.preferences.reset') }}" class="mt-3"
              onsubmit="return confirm(@js(__('Darstellung wirklich auf Standard zurücksetzen?')));">
            @csrf
            @method('delete')
            <button type="submit" class="px-4 py-2 h-9 rounded-xl glass-btn text-sm text-text">
                {{ __('Auf Standard zurücksetzen') }}
            </button>
        </form>
    @endif
</section>
