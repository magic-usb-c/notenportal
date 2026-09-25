<x-app-layout>
    <x-slot name="title">{{ __('Neuer Benutzer') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Neuen Benutzer anlegen')" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.users.index') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text whitespace-nowrap">
                    {{ __('Zurück') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
            <div class="rounded-xl border border-border bg-card p-6 space-y-6">

                <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5"
                      x-data="{ rolle: @js((string) old('rolle_id', '')), loading: false }"
                      @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    {{-- ---- Stammdaten ---- --}}
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="vorname" class="text-sm font-medium text-text">{{ __('Vorname') }} *</label>
                            <input type="text" name="vorname" id="vorname" value="{{ old('vorname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-note-ungenuegend @enderror">
                            @error('vorname')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="nachname" class="text-sm font-medium text-text">{{ __('Nachname') }} *</label>
                            <input type="text" name="nachname" id="nachname" value="{{ old('nachname') }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-note-ungenuegend @enderror">
                            @error('nachname')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div>
                        <label for="email" class="text-sm font-medium text-text">{{ __('E-Mail') }} *</label>
                        <input type="email" name="email" id="email" value="{{ old('email') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-note-ungenuegend @enderror">
                        @error('email')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="benutzername" class="text-sm font-medium text-text">
                            {{ __('Benutzername') }} * <span class="text-xs font-normal">{{ __('(Buchstaben, Ziffern, . _ -)') }}</span>
                        </label>
                        <input type="text" name="benutzername" id="benutzername" value="{{ old('benutzername') }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('benutzername') border-note-ungenuegend @enderror">
                        @error('benutzername')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"
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
                                <label for="passwort" class="text-sm font-medium text-text">{{ __('Passwort') }} * <span class="text-xs font-normal">{{ __('(mind. 10 Zeichen, Buchstaben und Ziffern)') }}</span></label>
                                <button type="button" @click="generieren()"
                                        class="text-xs text-accent-text hover:underline">
                                    {{ __('Generieren') }}
                                </button>
                            </div>
                            <input x-ref="pw1" :type="show ? 'text' : 'password'" name="passwort" id="passwort" required minlength="10"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('passwort') border-note-ungenuegend @enderror">
                            @error('passwort')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <div class="flex items-center justify-between">
                                <label for="passwort_confirmation" class="text-sm font-medium text-text">{{ __('Passwort bestätigen') }} *</label>
                                <button type="button" @click="show = !show" :aria-pressed="show"
                                        class="text-xs text-muted hover:text-text">
                                    <span x-show="!show">{{ __('Anzeigen') }}</span>
                                    <span x-show="show" x-cloak>{{ __('Verbergen') }}</span>
                                </button>
                            </div>
                            <input x-ref="pw2" :type="show ? 'text' : 'password'" name="passwort_confirmation" id="passwort_confirmation" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    {{-- ---- Rolle: visuelle Card-Auswahl ---- --}}
                    <div>
                        <label class="text-sm font-medium text-text">{{ __('Rolle') }} *</label>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach($rollen as $r)
                                @php
                                    $rolleMeta = match ($r->name) {
                                        'Lernender'     => ['desc' => __('Erfasst eigene Noten'), 'icon' => 'M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z'],
                                        'Berufsbildner' => ['desc' => __('Betreut Lernende'), 'icon' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z'],
                                        default         => ['desc' => __('Volle Verwaltung'), 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],
                                    };
                                @endphp
                                <label class="cursor-pointer rounded-2xl border-2 p-4 text-center transition-all duration-150 select-none"
                                       :class="rolle == '{{ $r->rolle_id }}'
                                           ? 'border-accent bg-accent/10'
                                           : 'border-border bg-input hover:border-accent/40'">
                                    <input type="radio" name="rolle_id" value="{{ $r->rolle_id }}"
                                           x-model="rolle" class="sr-only" required>
                                    <svg class="w-6 h-6 mx-auto mb-1.5 transition-colors"
                                         :class="rolle == '{{ $r->rolle_id }}' ? 'text-accent-text' : 'text-muted'"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $rolleMeta['icon'] }}"/>
                                        @if($r->name !== 'Lernender' && $r->name !== 'Berufsbildner')
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        @endif
                                    </svg>
                                    <div class="font-semibold text-sm"
                                         :class="rolle == '{{ $r->rolle_id }}' ? 'text-accent-text' : 'text-text'">{{ __($r->name) }}</div>
                                    <div class="text-[11px] text-muted mt-0.5">{{ $rolleMeta['desc'] }}</div>
                                </label>
                            @endforeach
                        </div>
                        @error('rolle_id')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                            {{ __('Benutzer anlegen') }}
                        </button>
                    </div>
                </form>

            </div>
            </div>
        </div>
    </div>
</x-app-layout>
