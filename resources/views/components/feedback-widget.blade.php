@auth
    @php
        $hinweisGesehen = \App\Models\NotificationMark::query()
            ->where('user_id', (int) auth()->id())
            ->where('type', \App\Http\Controllers\FeedbackController::HINWEIS_TYP)
            ->where('subject_key', \App\Http\Controllers\FeedbackController::HINWEIS_SCHLUESSEL)
            ->exists();
    @endphp

    @unless($hinweisGesehen)
        <div x-data="{ zeigen: true }" x-show="zeigen" x-cloak
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             class="fixed inset-x-4 bottom-4 lg:inset-x-auto lg:right-4 lg:bottom-18 lg:w-96 z-40 print:hidden">
            <div class="glass rounded-2xl shadow-lg px-4 py-3 flex items-start gap-3 text-sm">
                <svg class="w-5 h-5 text-accent shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p class="flex-1 text-text">
                    Fehler gefunden, eine Idee, eine Frage oder einfach ein Lob? Über das Benutzermenü oder mit
                    <kbd class="px-1 py-0.5 rounded-md border border-border text-xs">Strg/Cmd K</kbd> → «Feedback melden» erreichst du uns jederzeit.
                </p>
                <button type="button" @click="zeigen = false; fetch('{{ route('feedback.hint.dismiss') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '', Accept: 'application/json' },
                        })"
                        aria-label="Hinweis schliessen" class="text-muted hover:text-text text-lg leading-none shrink-0">&times;</button>
            </div>
        </div>
    @endunless

    <button type="button"
            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'feedback' }))"
            aria-label="Feedback melden"
            title="Feedback melden"
            class="hidden lg:flex fixed bottom-4 right-4 z-40 w-11 h-11 rounded-full bg-accent text-white shadow-lg accent-glow
                   hover:opacity-90 active:scale-[0.97] transition-all duration-150
                   items-center justify-center print:hidden">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
    </button>

    <x-modal name="feedback" maxWidth="md" focusable>
        <div x-data="feedbackDialog({ url: @js(route('feedback.store')), routeName: @js(request()->route()?->getName()), pfad: @js(request()->getRequestUri()) })"
             @close-modal.window="reset()"
             role="dialog" aria-modal="true" aria-labelledby="feedback-dialog-titel" class="p-6">
            <div class="flex items-center justify-between mb-1">
                <h2 id="feedback-dialog-titel" class="text-lg font-semibold text-text">Feedback melden</h2>
                <button type="button" @click="$dispatch('close-modal', 'feedback')" aria-label="Schliessen"
                        class="text-muted hover:text-text text-xl leading-none">&times;</button>
            </div>
            <p class="text-sm text-muted mb-4">Fehler, Ideen, Fragen oder Lob – alles ist willkommen.</p>

            <div class="grid grid-cols-4 gap-2 mb-4" role="radiogroup" aria-label="Kategorie">
                @foreach(\App\Models\Feedback::KATEGORIEN as $value => $label)
                    <button type="button" @click="kategorie = '{{ $value }}'" role="radio"
                            :aria-checked="kategorie === '{{ $value }}'"
                            :class="kategorie === '{{ $value }}' ? 'border-accent bg-accent/10 text-accent' : 'border-border text-muted hover:bg-accent/5'"
                            class="rounded-xl border px-2 py-2 text-sm font-medium transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <label for="feedback-text" class="text-xs uppercase tracking-widest text-muted font-medium">Deine Meldung *</label>
            <textarea id="feedback-text" x-model="text" rows="5" maxlength="5000" required
                      placeholder="Was ist passiert, was fehlt dir, was gefällt dir?"
                      class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm"></textarea>

            <label class="mt-4 flex items-start gap-2 text-sm text-text">
                <input type="checkbox" x-model="mitScreenshot"
                       class="mt-0.5 rounded border-border text-accent focus:ring-ring">
                <span>
                    Screenshot der Seite mitschicken
                    <span class="block text-xs text-muted">Hilft beim Verstehen der Meldung. Passwortfelder werden dabei ausgeblendet.</span>
                </span>
            </label>

            <p x-show="error" x-cloak class="mt-2 text-xs text-red-600 dark:text-red-400" x-text="error"></p>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="$dispatch('close-modal', 'feedback')"
                        class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                    Abbrechen
                </button>
                <button type="button" @click="senden()" :disabled="loading || text.trim().length < 3"
                        class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm inline-flex items-center gap-2 disabled:opacity-50">
                    <span x-show="!loading">Senden</span>
                    <span x-show="loading" x-text="ladeText"></span>
                </button>
            </div>
        </div>
    </x-modal>
@endauth
