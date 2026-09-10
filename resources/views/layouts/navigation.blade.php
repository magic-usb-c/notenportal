<nav x-data="{ open: false }" @keydown.escape.window="open = false" class="glass-subtle sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-text" />
                    </a>
                </div>

                <div class="hidden space-x-1 sm:-my-px sm:ms-8 sm:flex sm:items-stretch">
                    {{-- Dashboard aktiv auch auf /lernender, /berufsbildner, /admin --}}
                    <x-nav-link
                        :href="route('dashboard')"
                        :active="
                            request()->routeIs('dashboard')
                            || request()->routeIs('lernender.dashboard')
                            || request()->routeIs('berufsbildner.dashboard')
                            || request()->routeIs('admin.dashboard')
                        ">
                        Dashboard
                    </x-nav-link>

                    @auth
                        {{-- Lernender: direkt zu den eigenen Noten --}}
                        @if(auth()->user()->hasRole('Lernender'))
                            <x-nav-link :href="route('lernender.noten.index')" :active="request()->routeIs('lernender.noten.*')">
                                Noten
                            </x-nav-link>
                        @endif

                        {{-- Berufsbildner/Admin: Einstieg über Lernenden-Auswahl --}}
                        @if(auth()->user()->hasRole('Berufsbildner'))
                            <x-nav-link :href="route('berufsbildner.lernende.index')" :active="request()->routeIs('berufsbildner.lernende.*') || request()->routeIs('berufsbildner.lernende.noten.*')">
                                Lernende
                            </x-nav-link>
                        @endif

                        @if(auth()->user()->hasRole('Admin'))
                            {{-- Dropdown: Benutzer --}}
                            <div class="relative flex" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false" @keydown.escape.window="open = false">
                                <button type="button"
                                        @click="open = !open"
                                        :aria-expanded="open"
                                        aria-haspopup="true"
                                        class="inline-flex items-center gap-1 h-full px-3 border-b-2 text-sm font-medium transition-colors duration-150 focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg rounded-xs
                                    {{ request()->routeIs('admin.lernende.*') || request()->routeIs('admin.berufsbildner.*') || request()->routeIs('admin.benutzer.*') ? 'border-accent text-text bg-accent/10' : 'border-transparent text-muted hover:text-text hover:border-border hover:bg-accent/5' }}">
                                    Benutzer
                                    <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 8l4 4 4-4"/>
                                    </svg>
                                </button>
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                     class="absolute left-0 w-48 rounded-xl bg-card/70 backdrop-blur-xl backdrop-saturate-150 border border-border/60 shadow-2xl z-50 p-1"
                                     style="top: calc(100% + 2px);">
                                    <a href="{{ route('admin.lernende.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.lernende.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Lernende
                                    </a>
                                    <a href="{{ route('admin.berufsbildner.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.berufsbildner.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Berufsbildner
                                    </a>
                                    <a href="{{ route('admin.benutzer.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.benutzer.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Systembenutzer
                                    </a>
                                </div>
                            </div>

                            {{-- Dropdown: Stammdaten --}}
                            <div class="relative flex" x-data="{ open: false }" @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false" @keydown.escape.window="open = false">
                                <button type="button"
                                        @click="open = !open"
                                        :aria-expanded="open"
                                        aria-haspopup="true"
                                        class="inline-flex items-center gap-1 h-full px-3 border-b-2 text-sm font-medium transition-colors duration-150 focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg rounded-xs
                                    {{ request()->routeIs('admin.stammdaten.*') ? 'border-accent text-text bg-accent/10' : 'border-transparent text-muted hover:text-text hover:border-border hover:bg-accent/5' }}">
                                    Stammdaten
                                    <svg class="h-3.5 w-3.5 transition-transform duration-150" :class="{ 'rotate-180': open }" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 8l4 4 4-4"/>
                                    </svg>
                                </button>
                                <div x-show="open"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                     class="absolute left-0 w-48 rounded-xl bg-card/70 backdrop-blur-xl backdrop-saturate-150 border border-border/60 shadow-2xl z-50 p-1"
                                     style="top: calc(100% + 2px);">
                                    <a href="{{ route('admin.stammdaten.lehrberufe.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.stammdaten.lehrberufe.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Lehrberufe
                                    </a>
                                    <a href="{{ route('admin.stammdaten.module.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.stammdaten.module.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Module
                                    </a>
                                    <a href="{{ route('admin.stammdaten.faecher.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.stammdaten.faecher.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Fächer
                                    </a>
                                    <a href="{{ route('admin.stammdaten.semester.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.stammdaten.semester.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Semester
                                    </a>
                                    <a href="{{ route('admin.stammdaten.kategorien.index') }}"
                                       class="block px-3 py-2 text-sm rounded-lg transition-colors duration-150 {{ request()->routeIs('admin.stammdaten.kategorien.*') ? 'text-accent font-medium bg-accent/15' : 'text-text hover:bg-accent/8 hover:text-accent' }}">
                                        Kategorien
                                    </a>
                                </div>
                            </div>

                            {{-- Berichte (Direktlink) --}}
                            <x-nav-link :href="route('admin.berichte.noten')" :active="request()->routeIs('admin.berichte.*')">
                                Berichte
                            </x-nav-link>

                            {{-- Feedback (Direktlink) --}}
                            <x-nav-link :href="route('admin.feedback.index')" :active="request()->routeIs('admin.feedback.*')">
                                Feedback
                                @if(($feedbackOffenCount ?? 0) > 0)
                                    <span class="ml-1.5 inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-accent text-white text-[10px] font-bold">
                                        {{ $feedbackOffenCount }}
                                    </span>
                                @endif
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            {{-- Settings Dropdown --}}
            <div class="hidden sm:flex sm:items-center sm:ms-6 gap-2">
                {{-- Schnellsuche (nur Admin): springt zur Benutzerliste mit Fokus aufs Suchfeld --}}
                @auth
                    @if(auth()->user()->hasRole('Admin'))
                        <a href="{{ route('admin.benutzer.index') }}#suche"
                           class="hidden md:inline-flex items-center gap-2 px-3 h-9 rounded-xl glass-btn text-muted hover:text-text text-sm"
                           title="Benutzer suchen (Ctrl+K)">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                            </svg>
                            <span class="text-xs">Suchen</span>
                            <kbd class="text-[10px] font-mono border border-border rounded-sm px-1 py-0.5 leading-none">Ctrl K</kbd>
                        </a>
                    @endif
                @endauth

                {{-- Dark-Mode-Schalter --}}
                <button id="theme-toggle"
                        onclick="(function(){const html=document.documentElement;const isDark=html.classList.toggle('dark');try{localStorage.setItem('theme',isDark?'dark':'light');}catch(e){}})()"
                        class="p-2 rounded-xl text-muted hover:text-text hover:bg-bg transition-colors"
                        title="Hell / Dunkel wechseln">
                    <svg class="w-5 h-5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 1 1 0 10A5 5 0 0 1 12 7z"/>
                    </svg>
                    <svg class="w-5 h-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                </button>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 text-sm leading-4 font-medium rounded-xl
                                   text-muted bg-card hover:text-text
                                   focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg
                                   transition ease-in-out duration-150">
                            @php
                                $u = Auth::user();
                                $displayName = trim(($u->vorname ?? '') . ' ' . ($u->nachname ?? ''));
                                if ($displayName === '') $displayName = $u->benutzername ?? $u->email;
                            @endphp

                            <div>{{ $displayName }}</div>

                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            Profil
                        </x-dropdown-link>

                        <x-dropdown-link :href="route('feedback.index')">
                            Meine Meldungen
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                Abmelden
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            {{-- Hamburger --}}
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-xl
                           text-muted hover:text-text hover:bg-card
                           focus:outline-hidden focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg
                           transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Responsive Navigation Menu --}}
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link
                :href="route('dashboard')"
                :active="
                    request()->routeIs('dashboard')
                    || request()->routeIs('lernender.dashboard')
                    || request()->routeIs('berufsbildner.dashboard')
                    || request()->routeIs('admin.dashboard')
                ">
                Dashboard
            </x-responsive-nav-link>

            @auth
                @if(auth()->user()->hasRole('Lernender'))
                    <x-responsive-nav-link :href="route('lernender.noten.index')" :active="request()->routeIs('lernender.noten.index') || request()->routeIs('lernender.noten.create') || request()->routeIs('lernender.noten.edit')">
                        Noten
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('lernender.noten.rechner')" :active="request()->routeIs('lernender.noten.rechner')">
                        Rechner
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->hasRole('Berufsbildner'))
                    <x-responsive-nav-link :href="route('berufsbildner.lernende.index')" :active="request()->routeIs('berufsbildner.lernende.*') || request()->routeIs('berufsbildner.lernende.noten.*')">
                        Lernende
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->hasRole('Admin'))
                    <x-responsive-nav-link :href="route('admin.benutzer.index') . '#suche'">
                        Benutzer suchen
                    </x-responsive-nav-link>
                    <div class="px-4 py-1 text-xs font-semibold text-muted uppercase tracking-wide mt-1">Benutzer</div>
                    <x-responsive-nav-link :href="route('admin.lernende.index')" :active="request()->routeIs('admin.lernende.*')">
                        Lernende
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.berufsbildner.index')" :active="request()->routeIs('admin.berufsbildner.*')">
                        Berufsbildner
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.benutzer.index')" :active="request()->routeIs('admin.benutzer.*')">
                        Systembenutzer
                    </x-responsive-nav-link>
                    <div class="border-t border-border pt-1 mt-1">
                        <div class="px-4 py-1 text-xs font-semibold text-muted uppercase tracking-wide">Stammdaten</div>
                        <x-responsive-nav-link :href="route('admin.stammdaten.lehrberufe.index')" :active="request()->routeIs('admin.stammdaten.lehrberufe.*')">
                            Lehrberufe
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.stammdaten.module.index')" :active="request()->routeIs('admin.stammdaten.module.*')">
                            Module
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.stammdaten.faecher.index')" :active="request()->routeIs('admin.stammdaten.faecher.*')">
                            Fächer
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.stammdaten.semester.index')" :active="request()->routeIs('admin.stammdaten.semester.*')">
                            Semester
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.stammdaten.kategorien.index')" :active="request()->routeIs('admin.stammdaten.kategorien.*')">
                            Kategorien
                        </x-responsive-nav-link>
                    </div>
                    <div class="border-t border-border pt-1 mt-1">
                        <x-responsive-nav-link :href="route('admin.berichte.noten')" :active="request()->routeIs('admin.berichte.*')">
                            Berichte
                        </x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('admin.feedback.index')" :active="request()->routeIs('admin.feedback.*')">
                            Feedback
                            @if(($feedbackOffenCount ?? 0) > 0)
                                <span class="ml-1.5 inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-accent text-white text-[10px] font-bold">
                                    {{ $feedbackOffenCount }}
                                </span>
                            @endif
                        </x-responsive-nav-link>
                    </div>
                @endif
            @endauth
        </div>

        {{-- Responsive Settings Options --}}
        <div class="pt-4 pb-1 border-t border-border">
            <div class="px-4">
                @php
                    $u = Auth::user();
                    $displayName = trim(($u->vorname ?? '') . ' ' . ($u->nachname ?? ''));
                    if ($displayName === '') $displayName = $u->benutzername ?? $u->email;
                @endphp

                <div class="font-medium text-base text-text">{{ $displayName }}</div>
                <div class="font-medium text-sm text-muted">{{ $u->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                {{-- Dark-Mode-Schalter (Mobile) --}}
                <button type="button"
                        onclick="(function(){const html=document.documentElement;const isDark=html.classList.toggle('dark');try{localStorage.setItem('theme',isDark?'dark':'light');}catch(e){}})()"
                        class="w-full flex items-center gap-2 ps-4 pe-4 py-2 text-start text-base font-medium text-muted hover:text-text hover:bg-bg transition duration-150 ease-in-out">
                    <svg class="w-5 h-5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 1 1 0 10A5 5 0 0 1 12 7z"/>
                    </svg>
                    <svg class="w-5 h-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    </svg>
                    <span class="block dark:hidden">Dunkles Design</span>
                    <span class="hidden dark:block">Helles Design</span>
                </button>

                <x-responsive-nav-link :href="route('profile.edit')">
                    Profil
                </x-responsive-nav-link>

                <x-responsive-nav-link :href="route('feedback.index')">
                    Meine Meldungen
                </x-responsive-nav-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-responsive-nav-link :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();">
                        Abmelden
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>