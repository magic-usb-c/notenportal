@php
    $u = auth()->user();
    $eintraege = \App\Support\Navigation::fuer($u, $feedbackOffenCount ?? 0);
    $palette = collect($eintraege)
        ->flatMap(fn ($e) => isset($e['kinder'])
            ? collect($e['kinder'])->map(fn ($k) => ['label' => $k['label'], 'url' => $k['url'], 'gruppe' => $e['label']])
            : [['label' => $e['label'], 'url' => $e['url'], 'gruppe' => __('Seite')]])
        ->concat($u ? \App\Support\Navigation::befehle($u) : [])
        ->values();
    $suchUrl = $u ? route('search') : null;
    $name = trim(($u->vorname ?? '').' '.($u->nachname ?? '')) ?: ($u->email ?? '');
    $initialen = mb_strtoupper(mb_substr($u->vorname ?? '', 0, 1).mb_substr($u->nachname ?? '', 0, 1)) ?: mb_strtoupper(mb_substr($name, 0, 1));
    // Einmal bestimmen: Menüpunkt und Dialog hängen beide davon ab (Profil «Darstellung»).
    $tastenkuerzelAktiv = $u && \App\Support\Darstellung::fuer($u)['tastenkuerzel'] === \App\Support\Darstellung::TASTENKUERZEL_AN;

    // Seitenleiste: erst die Bereiche, dann die Abschnitte mit Titel (HIG «Sidebars»: höchstens zwei Ebenen)
    $bereiche = array_values(array_filter($eintraege, fn ($e) => ! isset($e['kinder'])));
    $abschnitte = array_values(array_filter($eintraege, fn ($e) => isset($e['kinder'])));

    // Inhalt der Symbolleiste, den die Seite über <x-seitenkopf> schiebt (Titel, Zurück, Aktionen)
    $titelKlein = trim($__env->yieldPushContent('np-titel'));
    $zurueck = trim($__env->yieldPushContent('np-zurueck'));
    $aktionen = trim($__env->yieldPushContent('np-aktionen'));
@endphp

{{-- Seitenleiste: schwebt als Glasfläche über dem Fensterrand. Ab 1024 px mit Navigation «Seite» fest, sonst
     eine Schublade. Liegt vor der Symbolleiste im DOM, damit die Tabulatorreihenfolge oben links beginnt. --}}
<aside id="np-seitenleiste" class="np-seitenleiste np-glas" x-data="npSeitenleiste" @keydown.escape="zu()" aria-label="{{ __('Seitenleiste') }}">
    <div class="flex h-10 shrink-0 items-center gap-1 pl-3 pr-1.5">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 flex-1 items-center gap-2.5 rounded-lg py-1 focus-visible:outline-offset-0" aria-label="{{ __('Zur Übersicht') }}">
            <x-application-logo class="h-6 max-w-24 shrink-0" />
            <span class="min-w-0 truncate text-sm font-semibold text-text">{{ $betriebName ?: 'Notenportal' }}</span>
        </a>
        <button type="button" @click="ausblenden()" class="np-knopf np-knopf-symbol"
                aria-label="{{ __('Seitenleiste ausblenden') }}" title="{{ __('Seitenleiste ausblenden') }}">
            <x-symbol name="sidebar" />
        </button>
    </div>

    <nav class="np-scroll-edge min-h-0 flex-1 overflow-y-auto overscroll-contain px-2.5 pb-4 pt-3" aria-label="{{ __('Hauptnavigation') }}">
        <ul role="list" class="flex flex-col gap-px">
            @foreach($bereiche as $e)
                <li>
                    <a href="{{ $e['url'] }}" @if($e['aktiv']) aria-current="page" @endif class="np-leistenzeile">
                        <x-symbol :name="$e['symbol']" />
                        <span class="min-w-0 flex-1 truncate">{{ $e['label'] }}</span>
                        @if(($e['badge'] ?? 0) > 0)
                            <span class="text-xs font-medium tabular-nums text-muted">{{ $e['badge'] }}</span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>

        @foreach($abschnitte as $e)
            <div class="mt-4" x-data="npSeitenleisteGruppe(@js($e['label']), @js($e['aktiv']))">
                <button type="button" @click="umschalten()" :aria-expanded="auf.toString()" aria-expanded="true" aria-controls="np-abschnitt-{{ $loop->index }}" class="np-leistenabschnitt">
                    {{ $e['label'] }}
                    <x-symbol name="chevron-right" strich="2" />
                </button>
                <ul role="list" id="np-abschnitt-{{ $loop->index }}" x-show="auf" class="mt-0.5 flex flex-col gap-px">
                    @foreach($e['kinder'] as $k)
                        <li>
                            <a href="{{ $k['url'] }}" @if($k['aktiv']) aria-current="page" @endif class="np-leistenzeile">
                                <x-symbol :name="$k['symbol']" />
                                <span class="min-w-0 flex-1 truncate">{{ $k['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </nav>
</aside>
<div class="np-schublade-scrim glass-scrim" x-data @click="$dispatch('np-schublade-zu')" aria-hidden="true"></div>

{{-- Symbolleiste (HIG «Toolbars»): vorne Seitenleiste und Zurück mit dem kleinen Titel, in der Mitte die Tableiste
     (Navigation «oben»), hinten die Aktionen der Seite, die Suche und das Konto. Durchsichtig; erst wenn Inhalt darunter
     liegt, trennt ein weicher Rand (Scroll Edge) sie vom Inhalt. --}}
<header class="np-symbolleiste print:hidden" x-data="npSymbolleiste">
    <div data-symbolleiste-zeile class="relative mx-auto flex min-h-(--np-symbolleiste-hoehe) np-seite flex-wrap items-center gap-x-3 gap-y-2 px-8 py-2.5">
        <div data-symbolleiste-anfang class="-ml-1.5 flex min-w-0 flex-1 basis-0 items-center gap-1">
            <button type="button" data-seitenleiste-zeigen x-data @click="$dispatch('np-seitenleiste-zeigen')" class="np-knopf np-knopf-symbol seite:lg:hidden"
                    aria-controls="np-seitenleiste" aria-label="{{ __('Seitenleiste einblenden') }}" title="{{ __('Seitenleiste einblenden') }}">
                <x-symbol name="sidebar" />
            </button>
            {!! $zurueck !!}
            @if($titelKlein !== '')
                <p data-kuerzbar class="ml-1.5 min-w-0 truncate text-base font-semibold text-text opacity-0 transition-opacity duration-200 data-sichtbar:opacity-100" :data-sichtbar="titelKlein" aria-hidden="true">{!! $titelKlein !!}</p>
            @endif
        </div>

        {{-- Tableiste (Navigation «oben», ab 1024 px). Priority+ (npLeistenUeberlauf): was nicht passt, wandert von
             hinten in «Mehr» – bei jeder Schriftgrösse aus dem Profil, in jeder Sprache und neben jedem Firmennamen. --}}
        <nav class="hidden min-w-0 shrink-0 oben:lg:flex" aria-label="{{ __('Hauptnavigation') }}">
            <div class="np-glas-gruppe gap-0.5" x-data="npLeistenUeberlauf">
                @foreach($eintraege as $i => $e)
                    @if(isset($e['kinder']))
                        <div data-ueberlauf="{{ $i }}" @if($e['aktiv']) data-aktiv @endif class="relative"
                             x-data="npLeistenMenue" @pointerenter="rein($event)" @pointerleave="raus($event)" @click.outside="zu()" @keydown.escape="escape()" @focusout="fokusRaus($event)">
                            <button type="button" x-ref="knopf" @click="klick()" :aria-expanded="auf" class="np-tab" @if($e['aktiv']) data-aktiv @endif>
                                {{ $e['label'] }}
                                <x-symbol name="chevron-down" strich="2" />
                            </button>
                            <div x-show="auf" x-cloak
                                 x-transition:enter="transition-opacity ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                 x-transition:leave="transition-opacity ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                 class="absolute left-0 top-full z-50 w-60 pt-2">
                                <ul role="list" class="rounded-xl p-1.5 glass-overlay">
                                    @foreach($e['kinder'] as $k)
                                        <li>
                                            <a href="{{ $k['url'] }}" @if($k['aktiv']) aria-current="page" @endif class="np-menue-eintrag aria-[current=page]:font-semibold">
                                                <x-symbol :name="$k['symbol']" class="size-4 text-accent-text" />{{ $k['label'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    @else
                        <a href="{{ $e['url'] }}" data-ueberlauf="{{ $i }}" data-badge="{{ (int) ($e['badge'] ?? 0) }}" @if($e['aktiv']) data-aktiv aria-current="page" @endif class="np-tab">
                            {{ $e['label'] }}
                            @if(($e['badge'] ?? 0) > 0)
                                <span class="np-marke h-4.5 bg-accent px-1.5 text-accent-contrast tabular-nums">{{ $e['badge'] }}</span>
                            @endif
                        </a>
                    @endif
                @endforeach
                <div data-mehr-menue class="relative" hidden
                     x-data="npLeistenMenue" @pointerenter="rein($event)" @pointerleave="raus($event)" @click.outside="zu()" @keydown.escape="escape()" @focusout="fokusRaus($event)">
                    <button type="button" x-ref="knopf" @click="klick()" :aria-expanded="auf" class="np-tab">
                        {{ __('Mehr') }}
                        <span data-mehr-badge class="np-marke h-4.5 bg-accent px-1.5 text-accent-contrast tabular-nums" hidden>0</span>
                        <x-symbol name="chevron-down" strich="2" />
                    </button>
                    <div x-show="auf" x-cloak
                         x-transition:enter="transition-opacity ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                         x-transition:leave="transition-opacity ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                         class="absolute right-0 top-full z-50 max-h-[calc(100dvh-5rem)] w-60 overflow-y-auto pt-2">
                        <div class="rounded-xl p-1.5 glass-overlay">
                            @foreach($eintraege as $i => $e)
                                @if(isset($e['kinder']))
                                    <div data-mehr="{{ $i }}" role="group" aria-label="{{ $e['label'] }}" hidden>
                                        <p class="px-2.5 pb-1 pt-2 text-2xs font-semibold text-muted" aria-hidden="true">{{ $e['label'] }}</p>
                                        @foreach($e['kinder'] as $k)
                                            <a href="{{ $k['url'] }}" @if($k['aktiv']) aria-current="page" @endif class="np-menue-eintrag aria-[current=page]:font-semibold">
                                                <x-symbol :name="$k['symbol']" class="size-4 text-accent-text" />{{ $k['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <a href="{{ $e['url'] }}" data-mehr="{{ $i }}" @if($e['aktiv']) aria-current="page" @endif hidden class="np-menue-eintrag aria-[current=page]:font-semibold">
                                        <x-symbol :name="$e['symbol']" class="size-4 text-accent-text" />
                                        <span class="flex-1">{{ $e['label'] }}</span>
                                        @if(($e['badge'] ?? 0) > 0)<span class="text-xs tabular-nums">{{ $e['badge'] }}</span>@endif
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </nav>

        {{-- Alle Elemente der Symbolleiste in einer Höhe wie Suchfeld und Konto (36 px) --}}
        <div data-symbolleiste-ende class="flex flex-1 basis-0 items-center justify-end gap-2 [&_.np-knopf]:h-9 [&_.np-knopf-symbol]:w-9">
            @if($aktionen !== '')
                <div class="flex items-center gap-2">{!! $aktionen !!}</div>
            @endif

            {{-- Feedback (Schalter in Betrieb › Bedienung); das Popover hängt am rechten Rand dieser Zeile --}}
            <x-feedback-widget />

            {{-- Suche (HIG «Search fields»: globale Suche hinten in der Symbolleiste) mit der Befehlspalette --}}
            <div x-data="npSuche({{ \Illuminate\Support\Js::from(['eintraege' => $palette, 'url' => $suchUrl]) }})" class="flex">
                <button type="button" @click="oeffnen()" class="np-glas-gruppe gap-2 pl-3 pr-2 text-sm text-muted transition-colors duration-100 hover:text-text xl:w-56"
                        aria-label="{{ __('Suchen') }}" aria-keyshortcuts="Control+K Meta+K">
                    <x-symbol name="magnifying-glass" class="size-4" strich="2" />
                    <span class="max-xl:sr-only">{{ __('Suchen') }}</span>
                    <kbd class="np-taste ml-auto max-xl:hidden" x-data x-text="/Mac|iPhone|iPad/.test(navigator.platform) ? '⌘ K' : 'Ctrl K'">Ctrl K</kbd>
                </button>

                <template x-teleport="body">
                    <div x-show="offen" x-cloak class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[14vh]" @keydown.escape.window="schliessen()"
                         @keydown.tab.window="if (offen) { $event.preventDefault(); $refs.eingabe?.focus(); }">
                        <div class="absolute inset-0 glass-scrim" @click="schliessen()" x-show="offen" x-transition.opacity.duration.200ms aria-hidden="true"></div>
                        <div class="relative w-full max-w-2xl overflow-hidden rounded-2xl glass-overlay shadow-e3" role="dialog" aria-modal="true" aria-label="{{ __('Suchen') }}"
                             x-show="offen" x-transition.opacity.duration.150ms>
                            <div class="flex items-center gap-3 px-4">
                                <x-symbol name="magnifying-glass" class="size-5 text-muted" strich="2" />
                                <input x-ref="eingabe" x-model="q" @keydown="taste($event)" type="text" autocomplete="off"
                                       role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="np-palette-treffer"
                                       :aria-activedescendant="liste[index] ? 'np-palette-treffer-' + index : null"
                                       placeholder="{{ $u?->hasRole('Lernender') ? __('Seite, Fach oder Note') : __('Seite, Aktion oder Name') }}"
                                       class="h-14 flex-1 border-0 bg-transparent px-0 text-lg text-text placeholder:text-muted focus:ring-0" aria-label="{{ __('Suchbegriff') }}">
                                <kbd class="np-taste">Esc</kbd>
                            </div>
                            <div id="np-palette-treffer" role="listbox" aria-label="{{ __('Suchen') }}" class="max-h-[52vh] overflow-y-auto border-t border-border p-1.5">
                                <template x-for="(t, i) in liste" :key="t.url + t.label">
                                    <a :href="t.url" tabindex="-1" role="option" :id="'np-palette-treffer-' + i" :aria-selected="i === index ? 'true' : 'false'" @mouseenter="index = i"
                                       @click="t.url.startsWith('#') ? ($event.preventDefault(), gehe(t)) : null"
                                       class="flex min-h-9 items-center justify-between gap-3 rounded-lg px-3 py-1.5"
                                       :class="i === index ? 'bg-accent text-accent-contrast' : 'text-text'">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm" x-text="t.label"></span>
                                            <span class="block truncate text-xs" :class="i === index ? 'text-accent-contrast/80' : 'text-muted'" x-show="t.sub" x-text="t.sub"></span>
                                        </span>
                                        <span class="shrink-0 text-xs" :class="i === index ? 'text-accent-contrast/80' : 'text-muted'" x-text="t.gruppe"></span>
                                    </a>
                                </template>
                                <div x-show="!liste.length" class="px-3 py-10 text-center text-sm text-muted">{{ __('Keine Treffer') }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Konto: Name, Darstellung, Einstellungen, Feedback, Abmelden --}}
            <x-dropdown align="right" width="w-64" content-classes="p-1.5 text-text">
                <x-slot name="trigger">
                    <button type="button" class="inline-flex size-9 items-center justify-center rounded-full transition-opacity duration-100 hover:opacity-85"
                            aria-label="{{ __('Konto') }}: {{ $name }}">
                        <span class="np-monogramm size-8 text-xs" aria-hidden="true">{{ $initialen }}</span>
                    </button>
                </x-slot>
                <x-slot name="content">
                    <div class="flex items-center gap-3 px-2 pb-3 pt-1.5">
                        <span class="np-monogramm size-10 text-sm" aria-hidden="true">{{ $initialen }}</span>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-text">{{ $name }}</div>
                            <div class="truncate text-xs text-muted" title="{{ $u->email ?? '' }}">{{ $u->email ?? '' }}</div>
                        </div>
                    </div>
                    <div class="px-2 pb-2" x-data="{ wert: window.npDarstellung || 'system', setzen(w) { this.wert = w; window.npDarstellung = w; window.npBefehl('#darstellung:' + w); } }" @click.stop>
                        <div class="np-segment flex w-full" role="radiogroup" aria-label="{{ __('Darstellung') }}" x-radiogroup>
                            <button type="button" role="radio" :aria-checked="(wert === 'hell').toString()" @click="setzen('hell')" class="flex-1" title="{{ __('Hell') }}">
                                <x-symbol name="sun" class="size-4" /><span class="sr-only">{{ __('Hell') }}</span>
                            </button>
                            <button type="button" role="radio" :aria-checked="(wert === 'dunkel').toString()" @click="setzen('dunkel')" class="flex-1" title="{{ __('Dunkel') }}">
                                <x-symbol name="moon" class="size-4" /><span class="sr-only">{{ __('Dunkel') }}</span>
                            </button>
                            <button type="button" role="radio" :aria-checked="(wert === 'system').toString()" @click="setzen('system')" class="flex-1" title="{{ __('Wie Gerät') }}">
                                <x-symbol name="computer-desktop" class="size-4" /><span class="sr-only">{{ __('Wie Gerät') }}</span>
                            </button>
                        </div>
                    </div>
                    <div class="-mx-1.5 my-1 border-t border-border"></div>
                    <div id="np-benutzermenue">
                        <a href="{{ route('settings.profile') }}" class="np-menue-eintrag"><x-symbol name="cog-6-tooth" class="size-4" />{{ __('Einstellungen') }}</a>
                        <a href="{{ route('feedback.index') }}" class="np-menue-eintrag"><x-symbol name="chat-bubble-left-ellipsis" class="size-4" />{{ __('Feedback') }}</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="np-menue-eintrag"><x-symbol name="arrow-right-start-on-rectangle" class="size-4" />{{ __('Abmelden') }}</button>
                        </form>
                    </div>
                </x-slot>
            </x-dropdown>
        </div>
    </div>

    <x-tastenkuerzel :eintraege="$eintraege" :aktiv="$tastenkuerzelAktiv" />
</header>
