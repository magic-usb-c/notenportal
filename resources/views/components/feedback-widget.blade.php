@auth
    @php
        $hinweisGesehen = \App\Models\NotificationMark::query()
            ->where('user_id', (int) auth()->id())
            ->where('type', \App\Http\Controllers\FeedbackController::HINWEIS_TYP)
            ->where('subject_key', \App\Http\Controllers\FeedbackController::HINWEIS_SCHLUESSEL)
            ->exists();
        $feedbackKnopfAktiv = \App\Support\Einstellungen::get(\App\Support\Einstellungen::FEEDBACK_KNOPF, '1') !== '0';
    @endphp

    {{-- Einmaliger Hinweis nur auf Desktop; mobil erreichbar über das Menü, damit nichts Inhalt verdeckt --}}
    @unless($hinweisGesehen)
        <div x-data="{ zeigen: true }" x-show="zeigen" x-cloak
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed {{ $feedbackKnopfAktiv ? 'bottom-36' : 'bottom-4' }} right-4 z-40 hidden w-96 lg:block print:hidden">
            <div class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm glass-overlay">
                <svg class="mt-0.5 size-5 shrink-0 text-accent-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p class="flex-1 text-text">
                    @if($feedbackKnopfAktiv)
                        {{ __('Fehler gefunden, eine Idee, eine Frage oder sonst etwas? Über den Knopf unten rechts oder mit') }}
                    @else
                        {{ __('Fehler gefunden, eine Idee, eine Frage oder sonst etwas? Mit') }}
                    @endif
                    <kbd class="rounded-md border border-border px-1 py-0.5 text-2xs">{{ __('Strg/Cmd K') }}</kbd> {{ __('→ «Feedback melden» erreichst du uns jederzeit.') }}
                </p>
                <button type="button" @click="zeigen = false; fetch('{{ route('feedback.hint.dismiss') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '', Accept: 'application/json' },
                        })"
                        aria-label="{{ __('Hinweis schliessen') }}"
                        class="-my-1 -mr-1.5 inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted hover:bg-surface-2 hover:text-text">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    @endunless

    {{-- Der x-data-Wrapper wird immer gerendert (unabhängig vom Schalter Einstellungen::FEEDBACK_KNOPF):
         nur so sind die open-modal/close-modal-Fensterlistener aus feedback.js init() immer aktiv und
         die Befehlspalette (Strg/Cmd K) sowie der «Meldung erfassen»-Knopf auf feedback/index.blade.php
         können den Dialog öffnen, auch wenn der schwebende Knopf selbst ausgeschaltet ist. Nur der
         sichtbare Auslöser (der Knopf) hängt am Schalter. --}}
    <div x-data="feedbackDialog({ url: @js(route('feedback.store')), routeName: @js(request()->route()?->getName()), pfad: @js(request()->getRequestUri()) })"
         class="fixed bottom-20 right-4 z-40 print:hidden">
        @if($feedbackKnopfAktiv)
            <button type="button" @click="open ? schliessen() : $dispatch('open-modal', 'feedback')" :aria-expanded="open" aria-haspopup="dialog"
                    aria-label="{{ __('Feedback / Fehler melden') }}"
                    class="group peer inline-flex h-12 max-w-12 items-center gap-2 overflow-hidden rounded-full bg-accent pl-3.5 pr-3.5 text-accent-contrast shadow-e2 transition-[max-width] duration-200 ease-out hover:max-w-xs focus-visible:max-w-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring/50">
                <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <span class="whitespace-nowrap text-sm font-medium">{{ __('Feedback / Fehler melden') }}</span>
            </button>
        @endif

            <div x-show="open" x-cloak @click.outside="schliessen()" @keydown.escape.window="schliessen()"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 translate-y-2"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0 translate-y-2"
                 role="dialog" aria-modal="false" aria-labelledby="feedback-panel-titel"
                 class="absolute bottom-14 right-0 max-h-[75vh] w-[min(24rem,calc(100vw-2rem))] overflow-y-auto rounded-2xl border border-border-strong/30 bg-card p-5 text-text shadow-e3">
                <div class="mb-1 flex items-center justify-between">
                    <h2 id="feedback-panel-titel" class="text-lg font-semibold text-text">{{ __('Feedback melden') }}</h2>
                    <button type="button" @click="schliessen()" aria-label="{{ __('Schliessen') }}"
                            class="-mr-2 inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-2 hover:text-text">
                        <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="mb-4 text-sm text-muted">{{ __('Fehler, Ideen, Fragen oder sonst etwas – alles ist willkommen.') }}</p>

                <div class="mb-4 grid grid-cols-2 gap-1 rounded-lg bg-surface-2 p-0.5" role="radiogroup" x-radiogroup aria-label="{{ __('Kategorie') }}">
                    @foreach(\App\Models\Feedback::KATEGORIEN as $value => $label)
                        <button type="button" @click="kategorie = '{{ $value }}'" role="radio"
                                :aria-checked="kategorie === '{{ $value }}'"
                                class="h-9 rounded-md px-2 text-sm font-medium text-muted transition-colors duration-150 hover:text-text aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs">
                            {{ __($label) }}
                        </button>
                    @endforeach
                </div>

                @if(Route::has('feedback.similar'))
                    <div x-data="{
                            anzahl: 0,
                            meldungen: [],
                            routeName: @js(request()->route()?->getName()),
                            async laden() {
                                if (! this.routeName) return;
                                try {
                                    const res = await fetch(@js(route('feedback.similar')) + '?route_name=' + encodeURIComponent(this.routeName), {
                                        headers: { Accept: 'application/json' },
                                    });
                                    const daten = res.ok ? await res.json() : { anzahl: 0, meldungen: [] };
                                    this.anzahl = daten.anzahl ?? 0;
                                    this.meldungen = daten.meldungen ?? [];
                                } catch {
                                    this.anzahl = 0;
                                    this.meldungen = [];
                                }
                            },
                            async stimmen(m) {
                                const werStimmt = ! m.meine;
                                m.meine = werStimmt;
                                m.stimmen += werStimmt ? 1 : -1;
                                try {
                                    const res = await fetch(@js(url('/feedback')) + '/' + m.id + '/vote', {
                                        method: werStimmt ? 'POST' : 'DELETE',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                            Accept: 'application/json',
                                        },
                                    });
                                    if (res.ok) {
                                        const daten = await res.json();
                                        m.stimmen = daten.stimmen;
                                        m.meine = daten.meine;
                                    }
                                } catch {}
                            },
                         }"
                         @open-modal.window="if ($event.detail === 'feedback') laden()">
                        <template x-if="anzahl > 0">
                            <div class="mb-4 rounded-lg border border-border bg-surface-2/60 p-3 text-sm">
                                <p class="mb-2 font-medium text-text"
                                   x-text="anzahl === 1 ? @js(__('1 offene Meldung zu dieser Seite')) : @js(__(':anzahl offene Meldungen zu dieser Seite')).replace(':anzahl', anzahl)"></p>
                                <ul class="space-y-2">
                                    <template x-for="m in meldungen" :key="m.id">
                                        <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <span class="text-muted">
                                                <span x-text="m.kategorie_label"></span>
                                                <span aria-hidden="true"> · </span>
                                                <span x-text="@js(__('seit :datum')).replace(':datum', m.datum)"></span>
                                                <span aria-hidden="true"> · </span>
                                                <span x-text="m.stimmen"></span>
                                            </span>
                                            <button type="button" @click="stimmen(m)" :aria-pressed="m.meine"
                                                    class="inline-flex h-11 w-full items-center justify-center rounded-lg px-3 text-sm font-medium transition-colors duration-100 sm:h-9 sm:w-auto"
                                                    :class="m.meine ? 'bg-accent/10 text-accent-text' : 'text-muted hover:bg-surface-2 hover:text-text'">
                                                <span x-text="m.meine ? @js(__('Unterstützt')) : @js(__('Betrifft mich auch'))"></span>
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                @endif

                <label for="feedback-text" class="text-sm font-medium text-text">{{ __('Deine Meldung') }} <span class="text-note-ungenuegend">*</span></label>
                <textarea id="feedback-text" x-model="text" rows="4" maxlength="5000" required
                          placeholder="{{ __('Was ist passiert, was fehlt dir, was gefällt dir?') }}"
                          aria-describedby="feedback-fehler"
                          class="mt-1.5 w-full rounded-lg border border-border-strong/70 bg-input px-3 py-2 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30"></textarea>

                <label class="mt-3 flex items-start gap-2 text-sm text-text">
                    <input type="checkbox" x-model="mitScreenshot"
                           class="mt-0.5 rounded border-border-strong/70 text-accent-text focus:ring-ring">
                    <span>
                        {{ __('Screenshot der Seite mitschicken') }}
                        <span class="block text-xs text-muted">{{ __('Passwortfelder werden dabei ausgeblendet.') }}</span>
                    </span>
                </label>

                @if(\App\Models\Feedback::hatTechnikSpalte())
                    <label class="mt-3 flex items-start gap-2 text-sm text-text">
                        <input type="checkbox" x-model="mitTechnik"
                               class="mt-0.5 rounded border-border-strong/70 text-accent-text focus:ring-ring">
                        <span>{{ __('Technische Angaben mitsenden') }}</span>
                    </label>
                    <details class="mt-1 ms-6" x-show="mitTechnik" x-cloak>
                        <summary class="cursor-pointer text-xs text-muted hover:text-text">{{ __('Was wird gesendet?') }}</summary>
                        <dl class="mt-1 text-xs text-muted space-y-0.5">
                            <div><dt class="inline font-medium">{{ __('Seite:') }}</dt> <dd class="inline">{{ request()->route()?->getName() ?? request()->getRequestUri() }}</dd></div>
                            <div><dt class="inline font-medium">{{ __('Bildschirm:') }}</dt> <dd class="inline" x-text="technik.bildschirm"></dd></div>
                            <div><dt class="inline font-medium">{{ __('Pixelverhältnis:') }}</dt> <dd class="inline" x-text="technik.pixelverhaeltnis"></dd></div>
                            <div><dt class="inline font-medium">{{ __('Sprache:') }}</dt> <dd class="inline" x-text="technik.sprache"></dd></div>
                            <div><dt class="inline font-medium">{{ __('Zeitzone:') }}</dt> <dd class="inline" x-text="technik.zeitzone"></dd></div>
                            <div><dt class="inline font-medium">{{ __('Darstellung:') }}</dt> <dd class="inline" x-text="technik.darstellung"></dd></div>
                            <div><dt class="inline font-medium">{{ __('Verbindung:') }}</dt> <dd class="inline" x-text="technik.online ? @js(__('Online')) : @js(__('Offline'))"></dd></div>
                            <div><dt class="inline font-medium">{{ __('Letzte JS-Fehler:') }}</dt> <dd class="inline" x-text="(window.__npFeedbackFehler ?? []).length"></dd></div>
                            <div><dt class="inline font-medium">{{ __('Fehlgeschlagene Anfragen:') }}</dt> <dd class="inline" x-text="(window.__npFeedbackRequests ?? []).length"></dd></div>
                        </dl>
                    </details>
                @endif

                @if(\App\Models\Feedback::hatAnhaengeTabelle())
                    <div class="mt-3">
                        <label for="feedback-anhaenge" class="text-sm font-medium text-text">{{ __('Eigene Anhänge') }}</label>
                        <input id="feedback-anhaenge" type="file" multiple accept=".png,.jpg,.jpeg,.webp,.pdf,.txt,.log"
                               @change="dateienWaehlen($event)"
                               class="mt-1.5 block w-full text-sm text-muted file:mr-3 file:h-8 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:text-sm file:font-medium file:text-text hover:file:bg-surface-2/80">
                        <p class="mt-1 text-xs text-muted">{{ __('Bis zu 3 Dateien, je maximal 5 MB (PNG, JPG, WebP, PDF, TXT, LOG).') }}</p>
                        <ul class="mt-1.5 space-y-1" x-show="anhaenge.length > 0">
                            <template x-for="(datei, index) in anhaenge" :key="datei.name + index">
                                <li class="flex items-center justify-between gap-2 rounded-md bg-surface-2 px-2.5 py-1 text-xs text-text">
                                    <span class="truncate" x-text="datei.name"></span>
                                    <button type="button" @click="anhangEntfernen(index)" aria-label="{{ __('Anhang entfernen') }}"
                                            class="shrink-0 text-muted hover:text-text">
                                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                @endif

                <p id="feedback-fehler" x-show="error" x-cloak class="mt-2 text-xs text-note-ungenuegend" x-text="error"></p>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="schliessen()"
                            class="inline-flex h-9 items-center rounded-lg px-3.5 text-sm text-muted hover:bg-surface-2 hover:text-text">
                        {{ __('Abbrechen') }}
                    </button>
                    <button type="button" @click="senden()" :disabled="loading || text.trim().length < 3"
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">
                        <span x-show="!loading">{{ __('Senden') }}</span>
                        <span x-show="loading" x-text="ladeText"></span>
                    </button>
                </div>
            </div>
    </div>
@endauth
