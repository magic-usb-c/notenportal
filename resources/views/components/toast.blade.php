@props([
    'art' => 'erfolg',      // erfolg | fehler
    'meldung' => null,      // null = JS-Toast (Event np-toast)
    'dauer' => null,
])
@php
    $fehler = $art === 'fehler';
    $dauer ??= $fehler ? 6000 : 4000;
@endphp
{{-- Toast (G2) oben rechts unter der Symbolleiste, wo macOS Mitteilungen zeigt: Farbe nur im Symbol, Text in --text; Pause beim Überfahren --}}
<div x-data="npToast(@js(['show' => $meldung !== null, 'message' => $meldung, 'fehler' => $fehler, 'dauer' => (int) $dauer]))"
     @if($meldung === null) x-on:np-toast.window="zeigen($event.detail)" @endif
     x-on:mouseenter="clearTimeout(timer)" x-on:mouseleave="start()"
     x-show="show" x-cloak
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0 -translate-y-2 motion-reduce:translate-y-0"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     role="{{ $fehler ? 'alert' : 'status' }}" @if($meldung === null) :role="fehler ? 'alert' : 'status'" @endif
     class="fixed right-4 top-[calc(var(--np-symbolleiste-hoehe)+0.5rem)] z-[60] flex w-[min(24rem,calc(100vw-2rem))] items-start gap-3 rounded-2xl py-3 pl-3.5 pr-3 text-sm text-text glass-overlay print:hidden">
    @if($meldung === null)
        <svg x-show="fehler" class="size-5 shrink-0 text-note-ungenuegend" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
        <svg x-show="!fehler" class="size-5 shrink-0 text-note-gut" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
    @elseif($fehler)
        <svg class="size-5 shrink-0 text-note-ungenuegend" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
        </svg>
    @else
        <svg class="size-5 shrink-0 text-note-gut" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
    @endif
    <span class="min-w-0 flex-1 pt-px" x-text="message">{{ $meldung }}</span>
    <button type="button" @click="show = false" aria-label="{{ __('Schliessen') }}" class="np-knopf np-knopf-symbol -my-1.5 shrink-0">
        <x-symbol name="x-mark" strich="2" />
    </button>
</div>
