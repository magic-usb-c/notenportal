{{-- Einmaliger Hinweis auf den Feedback-Weg, als Tipp im Seitenfluss über dem Seitenkopf (HIG «Tips»):
     schiebt den Inhalt nach unten statt ihn zu überdecken. Nur ab lg; mobil führt das Menü zum Feedback.
     Wegklicken blendet sofort aus und merkt es per fetch dauerhaft (FeedbackController::hinweisSchliessen). --}}
<div x-data="{ zeigen: true }" x-show="zeigen" x-cloak
     x-transition:leave="transition-opacity ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="mx-auto w-full np-seite px-8 pb-2 pt-1 print:hidden">
    <div class="np-karte flex items-center gap-3 py-2.5 pl-4 pr-2.5 text-sm">
        <x-symbol name="chat-bubble-left-ellipsis" class="size-5 text-accent-text" />
        <p class="flex-1 text-text">
            @if($feedbackKnopfAktiv)
                {{ __('Fehler gefunden, eine Idee, eine Frage oder sonst etwas? Über «Feedback» oben rechts oder mit') }}
            @else
                {{ __('Fehler gefunden, eine Idee, eine Frage oder sonst etwas? Mit') }}
            @endif
            <kbd class="np-taste">{{ __('Strg/Cmd K') }}</kbd> {{ __('→ «Feedback melden» erreichst du uns jederzeit.') }}
        </p>
        <button type="button" @click="zeigen = false; fetch('{{ route('feedback.hint.dismiss') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '', Accept: 'application/json' },
                })"
                aria-label="{{ __('Hinweis schliessen') }}" class="np-knopf np-knopf-symbol shrink-0">
            <x-symbol name="x-mark" strich="2" />
        </button>
    </div>
</div>
