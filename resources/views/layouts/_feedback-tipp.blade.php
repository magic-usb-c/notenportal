{{-- Einmaliger Hinweis auf den Feedback-Weg, als Tipp im Seitenfluss über dem Seitenkopf (HIG «Tips»):
     schiebt den Inhalt nach unten statt ihn zu überdecken. Nur ab lg; mobil führt das Menü zum Feedback.
     Wegklicken blendet sofort aus und merkt es per fetch dauerhaft (FeedbackController::hinweisSchliessen). --}}
<div x-data="{ zeigen: true }" x-show="zeigen" x-cloak
     x-transition:leave="transition-opacity ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mx-auto w-full np-seite px-4 pt-4 sm:px-6 lg:px-8 max-lg:hidden print:hidden">
    <div class="flex items-start gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm">
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
