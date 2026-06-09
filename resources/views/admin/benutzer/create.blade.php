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

                @if($errors->any())
                    <div class="bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-200 rounded-xl px-4 py-3 text-sm space-y-1">
                        @foreach($errors->all() as $e)
                            <div>{{ $e }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.benutzer.store') }}" class="space-y-5">
                    @csrf

                    {{-- Name --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Vorname</label>
                            <input type="text" name="vorname" value="{{ old('vorname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Nachname</label>
                            <input type="text" name="nachname" value="{{ old('nachname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    {{-- E-Mail --}}
                    <div>
                        <label class="text-sm font-medium text-muted">E-Mail</label>
                        <input type="email" name="email" value="{{ old('email') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    {{-- Benutzername --}}
                    <div>
                        <label class="text-sm font-medium text-muted">Benutzername <span class="text-xs">(nur Buchstaben/Ziffern, kein Leerzeichen)</span></label>
                        <input type="text" name="benutzername" value="{{ old('benutzername') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    {{-- Passwort --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Passwort</label>
                            <input type="password" name="passwort" required minlength="8"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Passwort bestätigen</label>
                            <input type="password" name="passwort_confirmation" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    {{-- Rolle --}}
                    <div>
                        <label class="text-sm font-medium text-muted">Rolle</label>
                        <select name="rolle_id" id="rolle_select" required
                                onchange="toggleRolleFelder()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Bitte wählen…</option>
                            @foreach($rollen as $r)
                                <option value="{{ $r->rolle_id }}" @selected(old('rolle_id') == $r->rolle_id)>
                                    {{ $r->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Zusatzfelder für Lernende (rolle_id = 3) --}}
                    <div id="felder_lernender" class="space-y-4 hidden">
                        <div class="border-t border-border pt-4">
                            <div class="text-sm font-semibold text-muted mb-3">Lernender-Profil</div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium text-muted">Lehrberuf</label>
                                    <select name="lehrberuf_id"
                                            class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                                        <option value="">Bitte wählen…</option>
                                        @foreach($lehrberufe as $lb)
                                            <option value="{{ $lb->lehrberuf_id }}" @selected(old('lehrberuf_id') == $lb->lehrberuf_id)>
                                                {{ $lb->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-sm font-medium text-muted">Lehrbeginn</label>
                                    <input type="date" name="lehrbeginn" value="{{ old('lehrbeginn') }}"
                                           class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full px-4 py-2 rounded-xl bg-accent text-white hover:opacity-90 font-medium">
                            Benutzer anlegen
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>

    <script>
        function toggleRolleFelder() {
            const rolleId = parseInt(document.getElementById('rolle_select').value);
            document.getElementById('felder_lernender').classList.toggle('hidden', rolleId !== 3);
        }
        // Beim Laden prüfen (falls old() einen Wert hat)
        document.addEventListener('DOMContentLoaded', toggleRolleFelder);
    </script>

</x-app-layout>
