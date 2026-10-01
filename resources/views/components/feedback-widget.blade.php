@auth
    @php
        $feedbackKnopfAktiv = \App\Support\Einstellungen::get(\App\Support\Einstellungen::FEEDBACK_KNOPF, '1') !== '0';
    @endphp

    {{-- Feedback als Eintrag der Symbolleiste mit Popover darunter (HIG «Popovers»): überdeckt keinen Inhalt.
         Der x-data-Wrapper steht immer (unabhängig vom Schalter Einstellungen::FEEDBACK_KNOPF): nur so hören
         die open-modal/close-modal-Listener aus feedback.js init(), und Befehlspalette (Strg/Cmd K) sowie
         «Meldung erfassen» auf feedback/index.blade.php öffnen den Dialog auch ohne sichtbaren Knopf.
         «contents» lässt den Knopf am Flex der Symbolleiste teilnehmen; das Popover richtet sich an deren rechtem Rand aus. --}}
    <div x-data="feedbackDialog({ url: @js(route('feedback.store')), routeName: @js(request()->route()?->getName()), pfad: @js(request()->getRequestUri()) })"
         class="contents print:hidden">
        @if($feedbackKnopfAktiv)
            <button type="button" data-feedback-knopf @click="open ? schliessen() : $dispatch('open-modal', 'feedback')" :aria-expanded="open" aria-haspopup="dialog"
                    aria-label="{{ __('Feedback / Fehler melden') }}" title="{{ __('Feedback / Fehler melden') }}"
                    class="np-glas-gruppe gap-2 px-2.5 text-sm text-muted transition-colors duration-100 hover:text-text aria-expanded:text-text xl:pr-3.5">
                <x-symbol name="chat-bubble-left-ellipsis" class="size-4.5 text-accent-text" />
                <span class="max-xl:sr-only">{{ __('Feedback') }}</span>
            </button>
        @endif

            <div x-show="open" x-cloak @click.outside="$event.target.closest('[data-feedback-knopf]') || schliessen()" @keydown.escape.window="schliessen()"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 role="dialog" aria-modal="false" aria-labelledby="feedback-panel-titel"
                 class="absolute right-8 top-full z-50 mt-1 max-h-[calc(100dvh-5rem)] w-104 overflow-y-auto rounded-2xl border border-border bg-card p-5 text-text shadow-e3">
                <div class="mb-1 flex items-center justify-between">
                    <h2 id="feedback-panel-titel" class="text-base font-semibold text-text">{{ __('Feedback melden') }}</h2>
                    <button type="button" @click="schliessen()" aria-label="{{ __('Schliessen') }}" class="np-knopf np-knopf-symbol -mr-1.5">
                        <x-symbol name="x-mark" strich="2" />
                    </button>
                </div>
                <div class="np-segment mb-4 mt-3 flex w-full" role="radiogroup" x-radiogroup aria-label="{{ __('Kategorie') }}">
                    @foreach(\App\Models\Feedback::KATEGORIEN as $value => $label)
                        <button type="button" @click="kategorie = '{{ $value }}'" role="radio" class="flex-1"
                                :aria-checked="(kategorie === '{{ $value }}').toString()">{{ __($label) }}</button>
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
                            <div class="mb-4 rounded-xl bg-fill-2 px-4 py-3 text-sm">
                                <p class="mb-2 font-medium text-text"
                                   x-text="anzahl === 1 ? @js(__('1 offene Meldung zu dieser Seite')) : @js(__(':anzahl offene Meldungen zu dieser Seite')).replace(':anzahl', anzahl)"></p>
                                <ul class="space-y-2">
                                    <template x-for="m in meldungen" :key="m.id">
                                        <li class="flex items-center justify-between gap-2">
                                            <span class="text-muted">
                                                <span x-text="m.kategorie_label"></span>
                                                <span aria-hidden="true"> · </span>
                                                <span x-text="@js(__('seit :datum')).replace(':datum', m.datum)"></span>
                                                <span aria-hidden="true"> · </span>
                                                <span x-text="m.stimmen"></span>
                                            </span>
                                            <button type="button" @click="stimmen(m)" :aria-pressed="m.meine"
                                                    class="inline-flex h-9 items-center justify-center rounded-lg px-3 text-sm font-medium transition-colors duration-100"
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

                <label for="feedback-text" class="text-sm font-medium text-text">{{ __('Deine Meldung') }}</label>
                <textarea id="feedback-text" x-model="text" rows="4" maxlength="5000" required
                          placeholder="{{ __('Was ist passiert, was fehlt dir, was gefällt dir?') }}"
                          aria-describedby="feedback-fehler" :aria-invalid="error ? 'true' : null"
                          class="np-feld mt-1.5"></textarea>

                <label class="mt-3 flex items-start gap-2 text-sm text-text">
                    <input type="checkbox" x-model="mitScreenshot"
                           class="np-haken mt-0.5">
                    <span>
                        {{ __('Screenshot der Seite mitschicken') }}
                        <span class="block text-xs text-muted">{{ __('Passwortfelder werden dabei ausgeblendet.') }}</span>
                    </span>
                </label>

                @if(\App\Models\Feedback::hatTechnikSpalte())
                    <label class="mt-3 flex items-start gap-2 text-sm text-text">
                        <input type="checkbox" x-model="mitTechnik"
                               class="np-haken mt-0.5">
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
                        <x-datei-feld id="feedback-anhaenge" mehrere ohne-name accept=".png,.jpg,.jpeg,.webp,.pdf,.txt,.log"
                                      @change="dateienWaehlen($event)" />
                        <p class="mt-1 text-xs text-muted">{{ __('Bis zu 3 Dateien, je maximal 5 MB (PNG, JPG, WebP, PDF, TXT, LOG).') }}</p>
                        <ul class="mt-2 flex flex-col gap-1.5" x-show="anhaenge.length > 0">
                            <template x-for="(datei, index) in anhaenge" :key="datei.name + index">
                                <li class="flex items-center justify-between gap-2 rounded-xl bg-fill-2 py-1 pl-4 pr-1 text-sm text-text">
                                    <span class="truncate" x-text="datei.name"></span>
                                    <button type="button" @click="anhangEntfernen(index)" aria-label="{{ __('Anhang entfernen') }}"
                                            class="np-knopf np-knopf-symbol np-knopf-klein shrink-0"><x-symbol name="x-mark" class="size-4" /></button>
                                </li>
                            </template>
                        </ul>
                    </div>
                @endif

                <p id="feedback-fehler" x-show="error" x-cloak class="mt-2 text-xs text-note-ungenuegend" x-text="error"></p>

                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" @click="schliessen()"
                            class="np-knopf np-knopf-sekundaer">
                        {{ __('Abbrechen') }}
                    </button>
                    <button type="button" @click="senden()" :disabled="loading || text.trim().length < 3"
                            class="np-knopf np-knopf-primaer">
                        <span x-show="!loading">{{ __('Senden') }}</span>
                        <span x-show="loading" x-text="ladeText"></span>
                    </button>
                </div>
            </div>
    </div>
@endauth
