<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neuen Benutzer anlegen</h2>
            <a href="{{ route('admin.benutzer.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-card border border-border rounded-2xl shadow-sm p-6 space-y-6">

                <form method="POST" action="{{ route('admin.benutzer.store') }}" class="space-y-5">
                    @csrf

                    {{-- ---- Stammdaten ---- --}}
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

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Passwort * <span class="text-xs font-normal">(mind. 8 Zeichen)</span></label>
                            <input type="password" name="passwort" required minlength="8"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('passwort') border-red-400 @enderror">
                            @error('passwort')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Passwort bestätigen *</label>
                            <input type="password" name="passwort_confirmation" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    {{-- ---- Rolle ---- --}}
                    <div>
                        <label class="text-sm font-medium text-muted">Rolle *</label>
                        <select name="rolle_id" id="rolle_select" required
                                onchange="onRolleChange()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('rolle_id') border-red-400 @enderror">
                            <option value="">Bitte wählen…</option>
                            @foreach($rollen as $r)
                                <option value="{{ $r->rolle_id }}" @selected(old('rolle_id') == $r->rolle_id)>
                                    {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('rolle_id')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ======================================================
                         Lernender-spezifische Felder (rolle_id = 3)
                         ====================================================== --}}
                    <div id="felder_lernender" class="hidden space-y-5">
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
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted">Lehrbeginn *</label>
                                    <input type="date" name="lehrbeginn" value="{{ old('lehrbeginn') }}"
                                           class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrbeginn') border-red-400 @enderror">
                                    @error('lehrbeginn')
                                        <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
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
                            <select name="track_typ" id="track_typ_select"
                                    onchange="onTrackChange()"
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                                <option value="">Kein Track (später einrichten)</option>
                                <option value="BMS" @selected(old('track_typ') === 'BMS')>BMS</option>
                                <option value="ABU" @selected(old('track_typ') === 'ABU')>ABU</option>
                            </select>

                            {{-- Startsemester – nur wenn Track ausgewählt --}}
                            <div id="track_semester_block" class="mt-3 hidden">
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
                        <button type="submit"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 font-medium">
                            Benutzer anlegen
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function onRolleChange() {
            const rolleId = parseInt(document.getElementById('rolle_select').value);
            document.getElementById('felder_lernender').classList.toggle('hidden', rolleId !== 3);
        }

        function onTrackChange() {
            const typ = document.getElementById('track_typ_select').value;
            document.getElementById('track_semester_block').classList.toggle('hidden', !typ);
        }

        document.addEventListener('DOMContentLoaded', () => {
            onRolleChange();
            onTrackChange();
        });
    </script>
</x-app-layout>
