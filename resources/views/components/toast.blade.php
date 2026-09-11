@props([
    'art' => 'erfolg',      // erfolg | fehler
    'meldung' => null,      // null = JS-Toast (Event np-toast)
    'dauer' => null,
])
@php
    $fehler = $art === 'fehler';
    $dauer ??= $fehler ? 6000 : 4000;
@endphp
{{-- Toast (G2): Farbe nur im Icon, Text in --text; Pause bei Hover --}}
<div x-data="{
        show: @js($meldung !== null),
        message: @js($meldung),
        timer: null,
        start() { clearTimeout(this.timer); this.timer = setTimeout(() => this.show = false, {{ (int) $dauer }}) },
     }"
     x-init="show && start()"
     @if($meldung === null) x-on:np-toast.window="message = $event.detail.message; show = true; start()" @endif
     x-on:mouseenter="clearTimeout(timer)" x-on:mouseleave="start()"
     x-show="show" x-cloak
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     role="{{ $fehler ? 'alert' : 'status' }}"
     class="fixed inset-x-4 bottom-4 z-[60] flex items-start gap-3 rounded-xl px-4 py-3 text-sm text-text glass-overlay sm:inset-x-auto sm:right-4 sm:max-w-md print:hidden">
    @if($fehler)
        <svg class="mt-px size-5 shrink-0 text-note-ungenuegend" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
    @else
        <svg class="mt-px size-5 shrink-0 text-note-gut" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
    @endif
    <span class="min-w-0 flex-1" x-text="message">{{ $meldung }}</span>
    <button type="button" @click="show = false" aria-label="Schliessen"
            class="-my-1 -mr-1.5 inline-flex size-7 shrink-0 items-center justify-center rounded-md text-muted hover:bg-surface-2 hover:text-text">
        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>
