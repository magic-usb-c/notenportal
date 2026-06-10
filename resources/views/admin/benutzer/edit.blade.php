<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">Benutzer bearbeiten</h2>
                <p class="text-sm text-muted mt-0.5">{{ $user->nachname }} {{ $user->vorname }}</p>
            </div>
            <div class="flex gap-2">
                @if($lernendeProfil)
                    <a href="{{ route('admin.lernende.show', $lernendeProfil->lernender_id) }}"
                       class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm whitespace-nowrap">
                        Lernenden-Profil
                    </a>
                @endif
                <a href="{{ route('admin.benutzer.index') }}"
                   class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm">
                    Zurück
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-5">

            @if(session('status'))
                <div class="rounded-xl border border-green-300 bg-green-50 dark:bg-green-900/20 dark:border-green-700 px-4 py-3 text-sm text-green-800 dark:text-green-200" data-autohide>
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                    <div class="font-semibold mb-1">Bitte prüfen:</div>
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-card border border-border rounded-2xl shadow-sm p-6">
                <form method="POST" action="{{ route('admin.benutzer.update', $user->benutzer_id) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Vorname *</label>
                            <input type="text" name="vorname" value="{{ old('vorname', $user->vorname) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-red-400 @enderror">
                            @error('vorname')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Nachname *</label>
                            <input type="text" name="nachname" value="{{ old('nachname', $user->nachname) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-red-400 @enderror">
                            @error('nachname')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">E-Mail *</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Benutzername</label>
                        <input type="text" value="{{ $user->benutzername }}" disabled
                               class="mt-1 w-full rounded-xl border border-border bg-bg text-muted font-mono px-3 py-2 cursor-not-allowed">
                        <p class="mt-1 text-xs text-muted">Benutzername kann nicht geändert werden.</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Rollen</label>
                        <div class="mt-1 flex flex-wrap gap-2">
                            @php
                                $userRollen = DB::table('benutzer_rollen as br')
                                    ->join('rollen as r', 'r.rolle_id', '=', 'br.rolle_id')
                                    ->where('br.benutzer_id', $user->benutzer_id)
                                    ->pluck('r.name');
                            @endphp
                            @foreach($userRollen as $rolle)
                                <span class="px-2 py-0.5 rounded-full text-xs bg-bg border border-border text-text">{{ $rolle }}</span>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-muted">Rollen können hier nicht geändert werden.</p>
                    </div>

                    {{-- Lernenden-Profil (nur wenn Lernender) --}}
                    @if($lernendeProfil)
                        <div class="border-t border-border pt-5 space-y-4">
                            <div class="text-sm font-semibold text-text">Lehrausbildung</div>

                            <div>
                                <label class="text-sm font-medium text-muted">Lehrberuf *</label>
                                <select name="lehrberuf_id" required
                                        class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrberuf_id') border-red-400 @enderror">
                                    @foreach($lehrberufe as $lb)
                                        <option value="{{ $lb->lehrberuf_id }}"
                                                @selected(old('lehrberuf_id', $lernendeProfil->lehrberuf_id) == $lb->lehrberuf_id)>
                                            {{ $lb->name }} ({{ $lb->kuerzel }})
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
                                    <input type="date" name="lehrbeginn"
                                           value="{{ old('lehrbeginn', $lernendeProfil->lehrbeginn) }}" required
                                           class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrbeginn') border-red-400 @enderror">
                                    @error('lehrbeginn')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted">Lehrende</label>
                                    <input type="date" name="lehrende"
                                           value="{{ old('lehrende', $lernendeProfil->lehrende) }}"
                                           class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrende') border-red-400 @enderror">
                                    @error('lehrende')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="border-t border-border pt-5 space-y-5"
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
                                <label class="text-sm font-medium text-muted">
                                    Neues Passwort <span class="text-xs font-normal">(leer lassen = nicht ändern)</span>
                                </label>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="generieren()"
                                            class="text-xs text-accent hover:underline">
                                        Generieren
                                    </button>
                                    <button type="button" @click="show = !show"
                                            class="text-xs text-muted hover:text-text">
                                        <span x-show="!show">Anzeigen</span>
                                        <span x-show="show" x-cloak>Verbergen</span>
                                    </button>
                                </div>
                            </div>
                            <input x-ref="pw1" :type="show ? 'text' : 'password'" name="passwort" minlength="8"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('passwort') border-red-400 @enderror">
                            @error('passwort')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="text-sm font-medium text-muted">Passwort bestätigen</label>
                            <input x-ref="pw2" :type="show ? 'text' : 'password'" name="passwort_confirmation"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary font-medium">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
