<x-app-layout>
    <x-slot name="title">Lernenden erfassen</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <nav class="text-xs text-muted flex items-center gap-1 mb-1">
                    <a href="{{ route('berufsbildner.lernende.index') }}" class="hover:text-text transition-colors">Lernende</a>
                    <span class="text-muted/40">›</span>
                    <span class="text-text">Erfassen</span>
                </nav>
                <h2 class="font-semibold text-xl text-text">Neuen Lernenden erfassen</h2>
                <p class="text-xs text-muted mt-0.5">Die Betreuung wird automatisch dir zugewiesen.</p>
            </div>
            <a href="{{ route('berufsbildner.lernende.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6 space-y-6">

                <form method="POST" action="{{ route('berufsbildner.lernende.store') }}" class="space-y-5"
                      x-data="{ track: '{{ old('track_typ') }}', loading: false }" @submit="loading = true">
                    @csrf

                    {{-- ---- Person ---- --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Vorname *</label>
                            <input type="text" name="vorname" value="{{ old('vorname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-red-400 @enderror">
                            @error('vorname')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Nachname *</label>
                            <input type="text" name="nachname" value="{{ old('nachname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-red-400 @enderror">
                            @error('nachname')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">E-Mail *</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">
                            Benutzername * <span class="text-xs font-normal">(nur Buchstaben und Ziffern)</span>
                        </label>
                        <input type="text" name="benutzername" value="{{ old('benutzername') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('benutzername') border-red-400 @enderror">
                        @error('benutzername')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ---- Passwort (mit Generator) ---- --}}
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
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
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

                    {{-- ---- Lehrausbildung ---- --}}
                    <div class="border-t border-border pt-5 space-y-4">
                        <div class="text-sm font-semibold text-text">Lehrausbildung</div>

                        <div>
                            <label class="text-sm font-medium text-muted">Lehrberuf *</label>
                            <select name="lehrberuf_id" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrberuf_id') border-red-400 @enderror">
                                <option value="">Bitte wählen…</option>
                                @foreach($lehrberufe as $lb)
                                    <option value="{{ $lb->lehrberuf_id }}" @selected(old('lehrberuf_id') == $lb->lehrberuf_id)>
                                        {{ $lb->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('lehrberuf_id')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-muted">Lehrbeginn *</label>
                                <input type="date" name="lehrbeginn" value="{{ old('lehrbeginn') }}" required
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrbeginn') border-red-400 @enderror">
                                @error('lehrbeginn')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="text-sm font-medium text-muted">Lehrende <span class="text-xs font-normal">(optional)</span></label>
                                <input type="date" name="lehrende" value="{{ old('lehrende') }}"
                                       class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrende') border-red-400 @enderror">
                                @error('lehrende')
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
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
                                    <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-11 rounded-xl bg-accent text-white font-medium np-btn-primary
                                       disabled:opacity-60 disabled:cursor-not-allowed inline-flex items-center justify-center gap-2">
                            <svg x-show="loading" x-cloak class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span x-text="loading ? 'Wird angelegt…' : 'Lernenden anlegen'"></span>
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
