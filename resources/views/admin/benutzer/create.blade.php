<x-app-layout>
    <x-slot name="title">Neuer Benutzer</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neuen Benutzer anlegen</h2>
            <a href="{{ route('admin.benutzer.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6 space-y-6">

                @php
                    $lernenderRolleId = $rollen->firstWhere('name', 'Lernender')->rolle_id ?? 3;
                    // ?rolle=lernender (z.B. vom "Neuer Lernender"-Button) waehlt die Rolle vor
                    $vorgewaehlteRolle = old('rolle_id', request('rolle') === 'lernender' ? $lernenderRolleId : '');
                @endphp
                <form method="POST" action="{{ route('admin.benutzer.store') }}" class="space-y-5"
                      x-data="{ rolle: '{{ $vorgewaehlteRolle }}', track: '{{ old('track_typ') }}', loading: false }"
                      @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    {{-- ---- Stammdaten ---- --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Vorname *</label>
                            <input type="text" name="vorname" value="{{ old('vorname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-red-400 @enderror">
                            @error('vorname')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Nachname *</label>
                            <input type="text" name="nachname" value="{{ old('nachname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-red-400 @enderror">
                            @error('nachname')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">E-Mail *</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">
                            Benutzername * <span class="text-xs font-normal">(nur Buchstaben und Ziffern)</span>
                        </label>
                        <input type="text" name="benutzername" value="{{ old('benutzername') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('benutzername') border-red-400 @enderror">
                        @error('benutzername')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4"
                         x-data="{
                             show: false,
                             generieren() {
                                 const zeichen = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!?#+';
                                 const arr = new Uint32Array(14);
                                 crypto.getRandomValues(arr);
                                 const pw = Array.from(arr, v => zeichen[v % zeichen.length]).join('');
                                 this.$refs.pw1.value = pw;
                                 this.$refs.pw2.value = pw;
                                 this.show = true;
                             }
                         }">
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-muted">Passwort * <span class="text-xs font-normal">(mind. 8 Zeichen)</span></label>
                                <button type="button" @click="generieren()"
                                        class="text-xs text-accent hover:underline">
                                    Generieren
                                </button>
                            </div>
                            <input x-ref="pw1" :type="show ? 'text' : 'password'" name="passwort" required minlength="8"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('passwort') border-red-400 @enderror">
                            @error('passwort')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <div class="flex items-center justify-between">
                                <label class="text-sm font-medium text-muted">Passwort bestätigen *</label>
                                <button type="button" @click="show = !show"
                                        class="text-xs text-muted hover:text-text">
                                    <span x-show="!show">Anzeigen</span>
                                    <span x-show="show" x-cloak>Verbergen</span>
                                </button>
                            </div>
                            <input x-ref="pw2" :type="show ? 'text' : 'password'" name="passwort_confirmation" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    {{-- ---- Rolle: visuelle Card-Auswahl ---- --}}
                    <div>
                        <label class="text-sm font-medium text-muted">Rolle *</label>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                            @foreach($rollen as $r)
                                @php
                                    $rolleMeta = match ($r->name) {
                                        'Lernender'     => ['desc' => 'Erfasst eigene Noten', 'icon' => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
                                        'Berufsbildner' => ['desc' => 'Betreut Lernende', 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z'],
                                        default         => ['desc' => 'Volle Verwaltung', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                                    };
                                @endphp
                                <label class="cursor-pointer rounded-2xl border-2 p-4 text-center transition-all duration-150 select-none"
                                       :class="rolle == '{{ $r->rolle_id }}'
                                           ? 'border-accent bg-accent/10 shadow-[0_0_16px_-4px_rgb(var(--accent-rgb) / 0.4)]'
                                           : 'border-border bg-input hover:border-accent/40'">
                                    <input type="radio" name="rolle_id" value="{{ $r->rolle_id }}"
                                           x-model="rolle" class="sr-only" required>
                                    <svg class="w-6 h-6 mx-auto mb-1.5 transition-colors"
                                         :class="rolle == '{{ $r->rolle_id }}' ? 'text-accent' : 'text-muted'"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rolleMeta['icon'] }}"/>
                                        @if($r->name !== 'Lernender' && $r->name !== 'Berufsbildner')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        @endif
                                    </svg>
                                    <div class="font-semibold text-sm"
                                         :class="rolle == '{{ $r->rolle_id }}' ? 'text-accent' : 'text-text'">{{ $r->name }}</div>
                                    <div class="text-[11px] text-muted mt-0.5">{{ $rolleMeta['desc'] }}</div>
                                </label>
                            @endforeach
                        </div>
                        @error('rolle_id')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ======================================================
                         Lernender-spezifische Felder
                         ====================================================== --}}
                    <div x-show="rolle == '{{ $lernenderRolleId }}'" x-cloak
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-2"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="space-y-5">
                        <div class="border-t border-border pt-5">
                            <div class="text-sm font-semibold text-text mb-4">Lernenden-Profil</div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-muted">Lehrberuf *</label>
                                    <select name="lehrberuf_id"
                                            class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrberuf_id') border-red-400 @enderror">
                                        <option value="">Bitte wählen…</option>
                                        @foreach($lehrberufe as $lb)
                                            <option value="{{ $lb->lehrberuf_id }}" @selected(old('lehrberuf_id') == $lb->lehrberuf_id)>
                                                {{ $lb->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('lehrberuf_id')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted">Lehrbeginn *</label>
                                    <input type="date" name="lehrbeginn" value="{{ old('lehrbeginn') }}"
                                           class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrbeginn') border-red-400 @enderror">
                                    @error('lehrbeginn')
                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        {{-- Berufsbildner zuweisen --}}
                        <div>
                            <label class="text-sm font-medium text-muted">Berufsbildner zuweisen</label>
                            <select name="berufsbildner_id"
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                                <option value="">Kein Berufsbildner (später zuweisen)</option>
                                @foreach($berufsbildner as $bb)
                                    <option value="{{ $bb->berufsbildner_id }}" @selected(old('berufsbildner_id') == $bb->berufsbildner_id)>
                                        {{ $bb->nachname }} {{ $bb->vorname }}
                                    </option>
                                @endforeach
                            </select>
                            @if($berufsbildner->isEmpty())
                                <p class="mt-1 text-xs text-muted">Noch keine Berufsbildner im System – zuerst einen Berufsbildner anlegen.</p>
                            @endif
                        </div>

                        {{-- BMS/ABU-Track --}}
                        <div>
                            <label class="text-sm font-medium text-muted">Schul-Track</label>
                            <select name="track_typ" x-model="track"
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                                <option value="">Kein Track (später einrichten)</option>
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>

                            {{-- Startsemester – nur wenn Track ausgewählt --}}
                            <div x-show="track" x-cloak class="mt-3">
                                <label class="text-sm font-medium text-muted">Startsemester für Track *</label>
                                <select name="track_semester_id"
                                        class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('track_semester_id') border-red-400 @enderror">
                                    <option value="">Bitte wählen…</option>
                                    @foreach($semester as $s)
                                        <option value="{{ $s->semester_id }}" @selected(old('track_semester_id') == $s->semester_id)>
                                            {{ $s->bezeichnung }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('track_semester_id')
                                    <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                            Benutzer anlegen
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
