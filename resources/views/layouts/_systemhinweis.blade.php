{{-- Systemhinweis-Banner: reiner Textinhalt über {{ }} (kein {!! !!}, kein Markdown, keine Links) –
     XSS und Attribut-Injection sind damit ausgeschlossen. Wegklicken per Alpine: sofort ausblenden,
     dann per fetch dauerhaft merken. Nicht sticky, volle Breite, print:hidden. --}}
@php
    $npHinweisArt = $systemhinweis['art'] ?? 'info';
    $npHinweisWarnung = $npHinweisArt === 'warnung';
@endphp
<div x-data="{ zeigen: true }" x-show="zeigen" x-cloak
     role="{{ $npHinweisWarnung ? 'alert' : 'status' }}"
     class="w-full px-4 py-3 text-sm print:hidden {{ $npHinweisWarnung ? 'bg-note-ungenuegend/10 text-note-ungenuegend' : 'bg-accent/10 text-accent-text' }}">
    <div class="mx-auto flex max-w-7xl items-start gap-3">
        <p class="flex-1 whitespace-pre-line">{{ $systemhinweis['text'] }}</p>
        <button type="button"
                @click="zeigen = false; fetch('{{ route('system-notice.dismiss') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '', Accept: 'application/json' },
                            body: JSON.stringify({ version: @js($systemhinweis['version']) }),
                        })"
                aria-label="{{ __('Hinweis schliessen') }}"
                class="-my-1 -mr-1.5 inline-flex size-7 shrink-0 items-center justify-center rounded-md hover:bg-black/5 dark:hover:bg-white/10">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
</div>
