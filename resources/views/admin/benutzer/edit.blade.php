<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">Benutzer bearbeiten</h2>
                <p class="text-sm text-muted mt-0.5">{{ $user->nachname }} {{ $user->vorname }}</p>
            </div>
            <a href="{{ route('admin.benutzer.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-5">
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

                    <div class="border-t border-border pt-5">
                        <label class="text-sm font-medium text-muted">
                            Neues Passwort <span class="text-xs font-normal">(leer lassen = nicht ändern)</span>
                        </label>
                        <input type="password" name="passwort" minlength="8"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('passwort') border-red-400 @enderror">
                        @error('passwort')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Passwort bestätigen</label>
                        <input type="password" name="passwort_confirmation"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 font-medium">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
