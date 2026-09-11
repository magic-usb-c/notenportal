@auth
    @php
        $hinweisGesehen = \App\Models\NotificationMark::query()
            ->where('user_id', (int) auth()->id())
            ->where('type', \App\Http\Controllers\FeedbackController::HINWEIS_TYP)
            ->where('subject_key', \App\Http\Controllers\FeedbackController::HINWEIS_SCHLUESSEL)
            ->exists();
    @endphp

    {{-- Einmaliger Hinweis nur auf Desktop; mobil erreichbar über das Menü, damit nichts Inhalt verdeckt --}}
    @unless($hinweisGesehen)
        <div x-data="{ zeigen: true }" x-show="zeigen" x-cloak
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed bottom-4 right-4 z-40 hidden w-96 lg:block print:hidden">
            <div class="flex items-start gap-3 rounded-xl px-4 py-3 text-sm glass-overlay">
                <svg class="mt-0.5 size-5 shrink-0 text-accent-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                </svg>
                <p class="flex-1 text-text">
                    {{ __('Fehler gefunden, eine Idee, eine Frage oder einfach ein Lob? Über das Benutzermenü oder mit') }}
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

    <x-modal name="feedback" maxWidth="md" focusable>
        <div x-data="feedbackDialog({ url: @js(route('feedback.store')), routeName: @js(request()->route()?->getName()), pfad: @js(request()->getRequestUri()) })"
             @close-modal.window="reset()"
             role="dialog" aria-modal="true" aria-labelledby="feedback-dialog-titel" class="p-6">
            <div class="mb-1 flex items-center justify-between">
                <h2 id="feedback-dialog-titel" class="text-lg font-semibold text-text">{{ __('Feedback melden') }}</h2>
                <button type="button" @click="$dispatch('close-modal', 'feedback')" aria-label="{{ __('Schliessen') }}"
                        class="-mr-2 inline-flex size-9 items-center justify-center rounded-lg text-muted hover:bg-surface-2 hover:text-text">
                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <p class="mb-4 text-sm text-muted">{{ __('Fehler, Ideen, Fragen oder Lob – alles ist willkommen.') }}</p>

            <div class="mb-4 grid grid-cols-2 gap-1 rounded-lg bg-surface-2 p-0.5 sm:grid-cols-4" role="radiogroup" x-radiogroup aria-label="{{ __('Kategorie') }}">
                @foreach(\App\Models\Feedback::KATEGORIEN as $value => $label)
                    <button type="button" @click="kategorie = '{{ $value }}'" role="radio"
                            :aria-checked="kategorie === '{{ $value }}'"
                            class="h-9 rounded-md px-2 text-sm font-medium text-muted transition-colors duration-150 hover:text-text aria-checked:bg-card aria-checked:text-text aria-checked:shadow-xs">
                        {{ __($label) }}
                    </button>
                @endforeach
            </div>

            <label for="feedback-text" class="text-sm font-medium text-text">{{ __('Deine Meldung') }} <span class="text-note-ungenuegend">*</span></label>
            <textarea id="feedback-text" x-model="text" rows="5" maxlength="5000" required
                      placeholder="{{ __('Was ist passiert, was fehlt dir, was gefällt dir?') }}"
                      aria-describedby="feedback-fehler"
                      class="mt-1.5 w-full rounded-lg border border-border-strong/70 bg-input px-3 py-2 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30"></textarea>

            <label class="mt-4 flex items-start gap-2 text-sm text-text">
                <input type="checkbox" x-model="mitScreenshot"
                       class="mt-0.5 rounded border-border-strong/70 text-accent focus:ring-ring">
                <span>
                    {{ __('Screenshot der Seite mitschicken') }}
                    <span class="block text-xs text-muted">{{ __('Passwortfelder werden dabei ausgeblendet.') }}</span>
                </span>
            </label>

            <p id="feedback-fehler" x-show="error" x-cloak class="mt-2 text-xs text-note-ungenuegend" x-text="error"></p>

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="$dispatch('close-modal', 'feedback')"
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
    </x-modal>
@endauth
