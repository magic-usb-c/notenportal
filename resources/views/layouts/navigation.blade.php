@php
    $u = auth()->user();
    $eintraege = \App\Support\Navigation::fuer($u, $feedbackOffenCount ?? 0);
    $palette = collect($eintraege)
        ->flatMap(fn ($e) => isset($e['kinder'])
            ? collect($e['kinder'])->map(fn ($k) => ['label' => $k['label'], 'url' => $k['url'], 'gruppe' => $e['label']])
            : [['label' => $e['label'], 'url' => $e['url'], 'gruppe' => 'Seite']])
        ->concat($u ? \App\Support\Navigation::befehle($u) : [])
        ->values();
    $suchUrl = $u && ($u->hasRole('Admin') || $u->hasRole('Berufsbildner')) ? route('suche') : null;
    $name = trim(($u->vorname ?? '').' '.($u->nachname ?? '')) ?: ($u->email ?? '');
    $icon = fn (string $n) => \App\Support\Navigation::icon($n);
@endphp

<nav x-data="{ open: false }" @keydown.escape.window="open = false" class="glass-subtle sticky top-0 z-50 print:hidden" aria-label="Hauptnavigation">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center gap-3">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 shrink-0 mr-2 rounded-xl focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring">
            <x-application-logo class="w-8 h-8" />
            <span class="hidden md:block leading-tight">
                <span class="block text-sm font-bold text-text tracking-tight">Notenportal</span>
                @if($betriebName ?? null)<span class="block text-[11px] text-muted">{{ $betriebName }}</span>@endif
            </span>
        </a>

        {{-- Desktop --}}
        <div class="hidden lg:flex items-center gap-1 flex-1">
            @foreach($eintraege as $e)
                @if(isset($e['kinder']))
                    <div class="relative" x-data="{ auf: false }" @mouseenter="auf = true" @mouseleave="auf = false" @click.outside="auf = false" @keydown.escape="auf = false">
                        <button type="button" @click="auf = !auf" :aria-expanded="auf" aria-haspopup="true"
                                @class(['inline-flex items-center gap-1.5 h-9 px-3 rounded-xl text-sm font-medium transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring',
                                    'bg-accent/10 text-accent' => $e['aktiv'], 'text-muted hover:text-text hover:bg-accent/5' => ! $e['aktiv']])>
                            {{ $e['label'] }}
                            <svg class="w-3.5 h-3.5 transition-transform" :class="auf && 'rotate-180'" fill="none" viewBox="0 0 20 20" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 8l4 4 4-4"/></svg>
                        </button>
                        <div x-show="auf" x-cloak x-transition.origin.top.left
                             class="absolute left-0 top-full pt-1.5 w-52 z-50">
                            <div class="rounded-2xl bg-card/85 backdrop-blur-xl border border-border/70 shadow-2xl p-1.5">
                                @foreach($e['kinder'] as $k)
                                    <a href="{{ $k['url'] }}" @class(['block px-3 py-2 text-sm rounded-xl transition-colors',
                                        'text-accent font-medium bg-accent/10' => $k['aktiv'], 'text-text hover:bg-accent/5' => ! $k['aktiv']])>{{ $k['label'] }}</a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <a href="{{ $e['url'] }}" @if($e['aktiv']) aria-current="page" @endif
                       @class(['inline-flex items-center gap-1.5 h-9 px-3 rounded-xl text-sm font-medium transition-colors focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring',
                           'bg-accent/10 text-accent' => $e['aktiv'], 'text-muted hover:text-text hover:bg-accent/5' => ! $e['aktiv']])>
                        {{ $e['label'] }}
                        @if(($e['badge'] ?? 0) > 0)
                            <span class="inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-accent text-white text-[10px] font-bold">{{ $e['badge'] }}</span>
                        @endif
                    </a>
                @endif
            @endforeach
        </div>

        <div class="flex items-center gap-2 ml-auto">
            {{-- Befehlspalette --}}
            <div x-data="npSuche({{ \Illuminate\Support\Js::from(['eintraege' => $palette, 'url' => $suchUrl]) }})">
                <button type="button" @click="oeffnen()"
                        class="inline-flex items-center gap-2 h-9 px-3 rounded-xl glass-btn text-muted hover:text-text text-sm" aria-label="Suchen">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <span class="hidden md:inline">Suchen</span>
                    <kbd class="hidden md:inline text-[10px] font-sans px-1.5 py-0.5 rounded-md border border-border text-muted">Ctrl K</kbd>
                </button>

                <template x-teleport="body">
                    <div x-show="offen" x-cloak class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]" @keydown.escape.window="offen = false">
                        <div class="absolute inset-0 bg-bg/60 backdrop-blur-sm" @click="offen = false" x-show="offen" x-transition.opacity></div>
                        <div class="relative w-full max-w-xl glass rounded-3xl overflow-hidden" role="dialog" aria-modal="true" aria-label="Suchen"
                             x-show="offen" x-transition.scale.95.origin.top>
                            <div class="flex items-center gap-3 px-5 border-b border-border/70">
                                <svg class="w-5 h-5 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                <input x-ref="eingabe" x-model="q" @keydown="taste($event)" type="text"
                                       placeholder="{{ $suchUrl ? 'Seite, Aktion oder Name' : 'Seite oder Aktion' }}"
                                       class="flex-1 h-14 bg-transparent border-0 focus:ring-0 text-text placeholder:text-muted" aria-label="Suchbegriff">
                                <kbd class="text-[10px] px-1.5 py-0.5 rounded-md border border-border text-muted">Esc</kbd>
                            </div>
                            <div class="max-h-[50vh] overflow-y-auto p-2">
                                <template x-for="(t, i) in liste" :key="t.url + t.label">
                                    <a :href="t.url" @mouseenter="index = i"
                                       class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-xl"
                                       :class="i === index ? 'bg-accent/10 text-accent' : 'text-text'">
                                        <span class="min-w-0">
                                            <span class="block text-sm truncate" x-text="t.label"></span>
                                            <span class="block text-xs text-muted truncate" x-show="t.sub" x-text="t.sub"></span>
                                        </span>
                                        <span class="text-[11px] text-muted shrink-0" x-text="t.gruppe"></span>
                                    </a>
                                </template>
                                <div x-show="!liste.length" class="px-3 py-8 text-center text-sm text-muted">Keine Treffer</div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" onclick="window.npToggleTheme()" class="w-9 h-9 inline-flex items-center justify-center rounded-xl text-muted hover:text-text hover:bg-accent/5" aria-label="Hell oder dunkel">
                <svg class="w-5 h-5 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364-.707.707M6.343 17.657l-.707.707m12.728 0-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 1 1 0 10A5 5 0 0 1 12 7z"/></svg>
                <svg class="w-5 h-5 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </button>

            <div class="hidden lg:block">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center gap-2 h-9 pl-1 pr-2.5 rounded-xl text-sm text-muted hover:text-text hover:bg-accent/5 focus:outline-hidden focus-visible:ring-2 focus-visible:ring-ring">
                            <span class="w-7 h-7 rounded-full bg-accent/15 text-accent text-xs font-bold inline-flex items-center justify-center">{{ mb_strtoupper(mb_substr($u->vorname ?? '', 0, 1).mb_substr($u->nachname ?? '', 0, 1)) }}</span>
                            <span class="max-w-40 truncate">{{ $name }}</span>
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profil</x-dropdown-link>
                        <x-dropdown-link :href="route('feedback.index')">Meine Meldungen</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">Abmelden</x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <button @click="open = !open" :aria-expanded="open" aria-label="Menü"
                    class="lg:hidden w-10 h-10 inline-flex items-center justify-center rounded-xl text-muted hover:text-text hover:bg-accent/5">
                <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                    <path x-show="!open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path x-show="open" x-cloak stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobil --}}
    <div x-show="open" x-cloak x-transition.origin.top class="lg:hidden border-t border-border/70 max-h-[80vh] overflow-y-auto">
        <div class="px-4 py-3 flex flex-col gap-1">
            @foreach($eintraege as $e)
                @if(isset($e['kinder']))
                    <div class="px-3 pt-3 pb-1 text-[11px] uppercase tracking-widest text-muted font-medium">{{ $e['label'] }}</div>
                    @foreach($e['kinder'] as $k)
                        <a href="{{ $k['url'] }}" @class(['flex items-center px-3 min-h-11 rounded-xl text-base', 'bg-accent/10 text-accent font-medium' => $k['aktiv'], 'text-text hover:bg-accent/5' => ! $k['aktiv']])>{{ $k['label'] }}</a>
                    @endforeach
                @else
                    <a href="{{ $e['url'] }}" @class(['flex items-center gap-3 px-3 min-h-11 rounded-xl text-base', 'bg-accent/10 text-accent font-medium' => $e['aktiv'], 'text-text hover:bg-accent/5' => ! $e['aktiv']])>
                        <svg class="w-5 h-5 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon($e['icon']) }}"/></svg>
                        {{ $e['label'] }}
                        @if(($e['badge'] ?? 0) > 0)<span class="ml-auto inline-flex items-center justify-center min-w-5 h-5 px-1 rounded-full bg-accent text-white text-[10px] font-bold">{{ $e['badge'] }}</span>@endif
                    </a>
                @endif
            @endforeach
        </div>
        <div class="px-4 py-3 border-t border-border/70 flex flex-col gap-1">
            <div class="px-3 pb-1 text-sm">
                <div class="font-medium text-text">{{ $name }}</div>
                <div class="text-muted text-xs">{{ $u->email ?? '' }}</div>
            </div>
            <a href="{{ route('profile.edit') }}" class="flex items-center px-3 min-h-11 rounded-xl text-text hover:bg-accent/5">Profil</a>
            <a href="{{ route('feedback.index') }}" class="flex items-center px-3 min-h-11 rounded-xl text-text hover:bg-accent/5">Meine Meldungen</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full text-left flex items-center px-3 min-h-11 rounded-xl text-text hover:bg-accent/5">Abmelden</button>
            </form>
        </div>
    </div>
</nav>
