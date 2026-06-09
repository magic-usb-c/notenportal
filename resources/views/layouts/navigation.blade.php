<nav x-data="{ open: false }" class="bg-card border-b border-border">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-text" />
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
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
                            <x-nav-link :href="route('admin.lernende.index')" :active="request()->routeIs('admin.lernende.*') || request()->routeIs('admin.lernende.noten.*')">
                                Lernende
                            </x-nav-link>
                        @endif
                    @endauth
                </div>
            </div>

            {{-- Settings Dropdown --}}
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 text-sm leading-4 font-medium rounded-xl
                                   text-muted bg-card hover:text-text
                                   focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg
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
                           focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg
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
                    <x-responsive-nav-link :href="route('lernender.noten.index')" :active="request()->routeIs('lernender.noten.*')">
                        Noten
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->hasRole('Berufsbildner'))
                    <x-responsive-nav-link :href="route('berufsbildner.lernende.index')" :active="request()->routeIs('berufsbildner.lernende.*') || request()->routeIs('berufsbildner.lernende.noten.*')">
                        Lernende
                    </x-responsive-nav-link>
                @endif

                @if(auth()->user()->hasRole('Admin'))
                    <x-responsive-nav-link :href="route('admin.lernende.index')" :active="request()->routeIs('admin.lernende.*') || request()->routeIs('admin.lernende.noten.*')">
                        Lernende
                    </x-responsive-nav-link>
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
                <x-responsive-nav-link :href="route('profile.edit')">
                    Profil
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