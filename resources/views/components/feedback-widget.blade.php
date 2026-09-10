@auth
    <button type="button"
            onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'feedback' }))"
            aria-label="Feedback geben"
            title="Feedback geben"
            class="fixed bottom-4 right-4 z-40 w-12 h-12 rounded-full bg-accent text-white shadow-lg
                   hover:opacity-90 active:scale-[0.97] transition-all duration-150
                   flex items-center justify-center print:hidden">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.86 9.86 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
    </button>

    <x-modal name="feedback" maxWidth="md" focusable>
        <div x-data="feedbackDialog()" @close-modal.window="reset()"
             role="dialog" aria-modal="true" aria-labelledby="feedback-dialog-titel" class="p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 id="feedback-dialog-titel" class="text-lg font-semibold text-text">Feedback</h2>
                <button type="button" @click="$dispatch('close-modal', 'feedback')" aria-label="Schliessen"
                        class="text-muted hover:text-text text-xl leading-none">&times;</button>
            </div>

            <div class="grid grid-cols-3 gap-2 mb-4" role="radiogroup" aria-label="Kategorie">
                @foreach(\App\Models\Feedback::KATEGORIEN as $value => $label)
                    <button type="button" @click="kategorie = '{{ $value }}'" role="radio"
                            :aria-checked="kategorie === '{{ $value }}'"
                            :class="kategorie === '{{ $value }}' ? 'border-accent bg-accent/10 text-accent' : 'border-border text-muted hover:bg-accent/5'"
                            class="rounded-xl border px-3 py-2 text-sm font-medium transition-colors">
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <label for="feedback-text" class="text-xs uppercase tracking-widest text-muted font-medium">Meldung *</label>
            <textarea id="feedback-text" x-model="text" rows="5" maxlength="5000" required
                      class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm"></textarea>
            <p x-show="error" x-cloak class="mt-1 text-xs text-red-600 dark:text-red-400" x-text="error"></p>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" @click="$dispatch('close-modal', 'feedback')"
                        class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                    Abbrechen
                </button>
                <button type="button" @click="senden()" :disabled="loading || text.trim().length < 3"
                        class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm inline-flex items-center gap-2 disabled:opacity-50">
                    <span x-show="!loading">Senden</span>
                    <span x-show="loading">…</span>
                </button>
            </div>
        </div>
    </x-modal>

    <script>
        function feedbackDialog() {
            return {
                kategorie: 'feedback',
                text: '',
                loading: false,
                error: '',
                reset() {
                    this.kategorie = 'feedback';
                    this.text = '';
                    this.loading = false;
                    this.error = '';
                },
                async senden() {
                    if (this.loading || this.text.trim().length < 3) return;
                    this.error = '';
                    this.loading = true;
                    try {
                        const res = await fetch('{{ route('feedback.store') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                kategorie: this.kategorie,
                                text: this.text,
                                route_name: @js(request()->route()?->getName()),
                                url: @js(request()->getRequestUri()),
                                viewport: window.innerWidth + 'x' + window.innerHeight,
                            }),
                        });

                        if (res.status === 201) {
                            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'feedback' }));
                            window.dispatchEvent(new CustomEvent('np-toast', { detail: { message: 'Danke, deine Meldung ist eingegangen.' } }));
                            this.reset();
                        } else if (res.status === 422) {
                            const data = await res.json();
                            this.error = Object.values(data.errors ?? {}).flat().join(' ') || 'Bitte Eingaben prüfen.';
                        } else {
                            this.error = 'Meldung konnte nicht gesendet werden.';
                        }
                    } catch (e) {
                        this.error = 'Meldung konnte nicht gesendet werden.';
                    } finally {
                        this.loading = false;
                    }
                },
            };
        }
    </script>
@endauth
