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
    $icon = fn (string $n) => \App\Support\Navigation::icon($n);
    // Einmal bestimmen: Menüpunkt und Dialog hängen beide davon ab (Profil «Darstellung»).
    $tastenkuerzelAktiv = $u && \App\Support\Darstellung::fuer($u)['tastenkuerzel'] === \App\Support\Darstellung::TASTENKUERZEL_AN;

    // Ruhige Leiste: inaktiv gedimmt, aktiv Textfarbe + 2-px-Unterstrich in Accent.
    // Zwischen lg und xl kompakter (Logo-Text, Feedback-Knopf aus, weniger Abstand), sonst läuft die Admin-Leiste bei 1024 px über.
    $punkt = 'relative inline-flex h-14 items-center gap-1.5 px-2 xl:px-3 text-sm font-medium transition-colors duration-100 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-ring';
    $aktiv = 'text-text after:absolute after:inset-x-2 xl:after:inset-x-3 after:bottom-0 after:h-0.5 after:rounded-full after:bg-accent';
    $inaktiv = 'text-muted hover:text-text';
    $badge = 'inline-flex h-5 min-w-5 items-center justify-center rounded-md bg-accent/12 px-1 text-2xs font-semibold tabular-nums text-accent-text';
    $werkzeug = 'inline-flex size-9 items-center justify-center rounded-lg text-muted transition-colors duration-100 hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring';
    $mobil = 'flex min-h-11 items-center gap-3 rounded-lg px-3 text-base transition-colors duration-100';
@endphp

<nav x-data="{ open: false }" @keydown.escape.window="open = false" class="glass-bar sticky top-0 z-50 print:hidden" aria-label="{{ __('Hauptnavigation') }}">
    <div class="mx-auto flex h-14 max-w-7xl items-center gap-3 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('dashboard') }}" class="mr-3 flex shrink-0 items-center gap-2.5 rounded-lg focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring" aria-label="{{ __('Zur Übersicht') }}">
            <x-application-logo class="h-8 max-w-40" />
            <span class="hidden leading-tight md:block lg:hidden xl:block">
                <span class="block text-sm font-semibold text-text">Notenportal</span>
                @if($betriebName ?? null)<span class="block text-2xs text-muted">{{ $betriebName }}</span>@endif
            </span>
        </a>

        {{-- Desktop --}}
        <div class="hidden flex-1 items-center lg:flex">
            @foreach($eintraege as $e)
                @if(isset($e['kinder']))
                    <div class="relative" x-data="{ auf: false }" @mouseenter="auf = true" @mouseleave="auf = false" @click.outside="auf = false" @keydown.escape="auf = false">
                        <button type="button" @click="auf = !auf" :aria-expanded="auf" aria-haspopup="true"
                                class="{{ $punkt }} {{ $e['aktiv'] ? $aktiv : $inaktiv }}">
                            {{ $e['label'] }}
                            <svg class="size-3.5 transition-transform duration-150" :class="auf && 'rotate-180'" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 8l4 4 4-4"/></svg>
                        </button>
                        <div x-show="auf" x-cloak
                             x-transition:enter="transition-opacity ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                             class="absolute left-0 top-full z-50 w-56 pt-1">
                            <div class="rounded-xl p-1 glass-overlay">
                                @foreach($e['kinder'] as $k)
                                    <a href="{{ $k['url'] }}" @if($k['aktiv']) aria-current="page" @endif
                                       @class(['flex min-h-9 items-center rounded-lg px-3 text-sm transition-colors duration-100',
                                           'bg-surface-2 font-medium text-text' => $k['aktiv'], 'text-muted hover:bg-surface-2 hover:text-text' => ! $k['aktiv']])>{{ $k['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ $e['url'] }}" @if($e['aktiv']) aria-current="page" @endif class="{{ $punkt }} {{ $e['aktiv'] ? $aktiv : $inaktiv }}">
                        {{ $e['label'] }}
                        @if(($e['badge'] ?? 0) > 0)
                            <span class="{{ $badge }}">{{ $e['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>

        <div class="ml-auto flex items-center gap-1.5">
            {{-- Befehlspalette --}}
            <div x-data="npSuche({{ \Illuminate\Support\Js::from(['eintraege' => $palette, 'url' => $suchUrl]) }})">
                <button type="button" @click="oeffnen()"
                        class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-2.5 text-sm text-muted hover:text-text md:px-3" aria-label="{{ __('Suchen') }}">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span class="hidden md:inline">{{ __('Suchen') }}</span>
                    <kbd class="hidden rounded-md border border-border px-1.5 py-0.5 font-sans text-2xs text-muted md:inline lg:hidden xl:inline">Ctrl K</kbd>
                </button>

                <template x-teleport="body">
                    <div x-show="offen" x-cloak class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]" @keydown.escape.window="offen = false">
                        <div class="absolute inset-0 glass-scrim" @click="offen = false" x-show="offen" x-transition.opacity.duration.200ms aria-hidden="true"></div>
                        <div class="relative w-full max-w-xl overflow-hidden rounded-2xl glass-overlay shadow-e3" role="dialog" aria-modal="true" aria-label="{{ __('Suchen') }}"
                             x-show="offen" x-transition.opacity.duration.200ms>
                            <div class="flex items-center gap-3 border-b border-border px-4">
                                <svg class="size-5 shrink-0 text-muted" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input x-ref="eingabe" x-model="q" @keydown="taste($event)" type="text"
                                       placeholder="{{ $u?->hasRole('Lernender') ? __('Seite, Fach oder Note') : __('Seite, Aktion oder Name') }}"
                                       class="h-14 flex-1 border-0 bg-transparent text-base text-text placeholder:text-muted focus:ring-0" aria-label="{{ __('Suchbegriff') }}">
                                <kbd class="rounded-md border border-border px-1.5 py-0.5 text-2xs text-muted">Esc</kbd>
                            </div>
                            <div class="max-h-[50vh] overflow-y-auto p-1.5">
                                <template x-for="(t, i) in liste" :key="t.url + t.label">
                                    <a :href="t.url" @mouseenter="index = i"
                                       @click="t.url === '#feedback-modal' ? ($event.preventDefault(), gehe(t)) : null"
                                       class="flex items-center justify-between gap-3 rounded-lg px-3 py-2"
                                       :class="i === index ? 'bg-surface-2 text-text' : 'text-text'">
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm" x-text="t.label"></span>
                                            <span class="block truncate text-xs text-muted" x-show="t.sub" x-text="t.sub"></span>
                                        </span>
                                        <span class="shrink-0 text-2xs text-muted" x-text="t.gruppe"></span>
                                    </a>
                                </template>
                                <div x-show="!liste.length" class="px-3 py-8 text-center text-sm text-muted">{{ __('Keine Treffer') }}</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'feedback' }))"
                    class="{{ $werkzeug }} hidden xl:inline-flex" aria-label="{{ __('Feedback melden') }}" title="{{ __('Feedback melden') }}">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </button>

            <button type="button" onclick="window.npToggleTheme()" class="{{ $werkzeug }}" aria-label="{{ __('Hell oder dunkel') }}">
                <svg class="block size-5 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 1 1 0 10A5 5 0 0 1 12 7z"/></svg>
                <svg class="hidden size-5 dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>

            <div class="hidden lg:block">
                <x-dropdown align="right" width="56">
                    <x-slot name="trigger">
                        <button class="inline-flex h-9 items-center gap-2 rounded-lg pl-1 pr-2 text-sm text-muted transition-colors duration-100 hover:bg-surface-2 hover:text-text focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring">
                            <span class="inline-flex size-7 items-center justify-center rounded-full bg-surface-2 text-xs font-semibold text-text">{{ mb_strtoupper(mb_substr($u->vorname ?? '', 0, 1).mb_substr($u->nachname ?? '', 0, 1)) }}</span>
                            {{-- Name erst ab 2xl sichtbar (steht auch im Menükopf): bis 1536 px reicht der Platz neben 7 Admin-Einträgen nicht --}}
                            <span class="sr-only 2xl:not-sr-only 2xl:max-w-40 2xl:truncate">{{ $name }}</span>
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-3 pb-2 pt-1.5">
                            <div class="truncate text-sm font-medium text-text">{{ $name }}</div>
                            <div class="truncate text-xs text-muted">{{ $u->email ?? '' }}</div>
                        </div>
                        <div class="my-1 border-t border-border"></div>
                        <div id="np-benutzermenue">
                            <x-dropdown-link :href="route('settings.profile')">{{ __('Einstellungen') }}</x-dropdown-link>
                            <x-dropdown-link :href="route('feedback.index')">{{ __('Feedback') }}</x-dropdown-link>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">{{ __('Abmelden') }}</x-dropdown-link>
                            </form>
                        </div>
                    </x-slot>
                </x-dropdown>
            </div>

            <button @click="open = !open" :aria-expanded="open" aria-label="{{ __('Menü') }}" aria-controls="np-menue-mobil"
                    class="{{ $werkzeug }} size-10 lg:hidden">
                <svg class="size-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobil --}}
    <div id="np-menue-mobil" x-show="open" x-cloak
         x-transition:enter="transition-opacity ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="max-h-[calc(100dvh-3.5rem)] overflow-y-auto overscroll-contain border-t border-border bg-card lg:hidden">
        <div class="flex flex-col gap-0.5 px-4 py-3">
            @foreach($eintraege as $e)
                @if(isset($e['kinder']))
                    <div class="px-3 pb-1 pt-3 text-xs font-medium text-muted">{{ $e['label'] }}</div>
                    @foreach($e['kinder'] as $k)
                        <a href="{{ $k['url'] }}" @if($k['aktiv']) aria-current="page" @endif
                           @class([$mobil, 'bg-surface-2 font-medium text-text' => $k['aktiv'], 'text-muted hover:bg-surface-2 hover:text-text' => ! $k['aktiv']])>{{ $k['label'] }}</a>
                    @endforeach
                @else
                    <a href="{{ $e['url'] }}" @if($e['aktiv']) aria-current="page" @endif
                       @class([$mobil, 'bg-surface-2 font-medium text-text' => $e['aktiv'], 'text-muted hover:bg-surface-2 hover:text-text' => ! $e['aktiv']])>
                        <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon($e['icon']) }}"/></svg>
                        {{ $e['label'] }}
                        @if(($e['badge'] ?? 0) > 0)<span class="ml-auto {{ $badge }}">{{ $e['badge'] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </div>
        <div class="flex flex-col gap-0.5 border-t border-border px-4 py-3">
            <div class="px-3 pb-2 text-sm">
                <div class="font-medium text-text">{{ $name }}</div>
                <div class="text-xs text-muted">{{ $u->email ?? '' }}</div>
            </div>
            <div id="np-benutzermenue-mobil" class="flex flex-col gap-0.5">
                <a href="{{ route('settings.profile') }}" class="{{ $mobil }} text-muted hover:bg-surface-2 hover:text-text">{{ __('Einstellungen') }}</a>
                <a href="{{ route('feedback.index') }}" class="{{ $mobil }} text-muted hover:bg-surface-2 hover:text-text">{{ __('Feedback') }}</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="{{ $mobil }} w-full text-left text-muted hover:bg-surface-2 hover:text-text">{{ __('Abmelden') }}</button>
                </form>
            </div>
        </div>
    </div>

    <x-tastenkuerzel :eintraege="$eintraege" :aktiv="$tastenkuerzelAktiv" />
</nav>
