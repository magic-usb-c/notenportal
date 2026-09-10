<x-app-layout>
    <x-slot name="title">Benutzer bearbeiten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">Benutzer bearbeiten</h2>
                <p class="text-sm text-muted mt-0.5">{{ $user->nachname }} {{ $user->vorname }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.benutzer.index') }}"
                   class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                    Zurück
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-5">

            <div class="glass rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.benutzer.update', $user->benutzer_id) }}" class="space-y-5"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="vorname" class="text-xs uppercase tracking-widest text-muted font-medium">Vorname *</label>
                            <input type="text" name="vorname" id="vorname" value="{{ old('vorname', $user->vorname) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-red-400 @enderror">
                            @error('vorname')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="nachname" class="text-xs uppercase tracking-widest text-muted font-medium">Nachname *</label>
                            <input type="text" name="nachname" id="nachname" value="{{ old('nachname', $user->nachname) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-red-400 @enderror">
                            @error('nachname')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="text-xs uppercase tracking-widest text-muted font-medium">E-Mail *</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="benutzername" class="text-xs uppercase tracking-widest text-muted font-medium">Benutzername *</label>
                        <input type="text" name="benutzername" id="benutzername" value="{{ old('benutzername', $user->benutzername) }}" required maxlength="50" pattern="[A-Za-z0-9._\-]+"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('benutzername') border-red-400 @enderror">
                        @error('benutzername')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <fieldset>
                        <legend class="text-xs uppercase tracking-widest text-muted font-medium">Rollen *</legend>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach(['Admin', 'Berufsbildner'] as $rolle)
                                <label class="inline-flex items-center gap-2 rounded-full border border-border px-3 min-h-9 text-sm text-text cursor-pointer has-[:checked]:border-accent/50 has-[:checked]:bg-accent/10">
                                    <input type="checkbox" name="rollen[]" value="{{ $rolle }}" @checked(in_array($rolle, old('rollen', $rollen->all()), true))
                                           class="w-4 h-4 rounded border-border text-accent focus:ring-ring">
                                    {{ $rolle }}
                                </label>
                            @endforeach
                        </div>
                        @error('rollen')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </fieldset>


                    <div class="border-t border-border pt-5 space-y-5"
                         x-data="{
                             show: false,
                             generieren() {
                                 const zeichen = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!?#+';
                                 const arr = new Uint32Array(14);
                                 let pw;
                                 do {
                                     crypto.getRandomValues(arr);
                                     pw = Array.from(arr, v => zeichen[v % zeichen.length]).join('');
                                 } while (!/\d/.test(pw) || !/[a-z]/i.test(pw));
                                 this.$refs.pw1.value = pw;
                                 this.$refs.pw2.value = pw;
                                 this.show = true;
                             }
                         }">
                        <div>
                            <div class="flex items-center justify-between">
                                <label for="passwort" class="text-xs uppercase tracking-widest text-muted font-medium">
                                    Neues Passwort
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
                            <input x-ref="pw1" :type="show ? 'text' : 'password'" name="passwort" id="passwort" minlength="10"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('passwort') border-red-400 @enderror">
                            @error('passwort')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="passwort_confirmation" class="text-xs uppercase tracking-widest text-muted font-medium">Passwort bestätigen</label>
                            <input x-ref="pw2" :type="show ? 'text' : 'password'" name="passwort_confirmation" id="passwort_confirmation"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
