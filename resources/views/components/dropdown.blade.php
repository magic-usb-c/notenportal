@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'p-1 text-text',
])

@php
$alignmentClasses = match ($align) {
    'left' => 'inset-s-0',
    'top' => '',
    default => 'inset-e-0',
};

$width = match ($width) {
    '48' => 'w-48',
    '56' => 'w-56',
    default => $width,
};
@endphp

{{-- Menü (G9, B1): wächst aus seinem Auslöser. Das Öffnen läuft über @starting-style von glass-overlay, Startmass und
     Ursprung setzt window.npMorph (layouts/app); nur das Ausblenden läuft über Alpine (Deckkraft, 150 ms).
     Escape schliesst und gibt den Fokus an den Auslöser zurück. --}}
<div class="relative" x-data="{ open: false }"
     x-init="$watch('open', (v) => v && $nextTick(() => window.npMorph?.($refs.ausloeser.querySelector('button, a') ?? $refs.ausloeser, $refs.panel)))"
     @click.outside="open = false" @focusout="if ($event.relatedTarget && ! $el.contains($event.relatedTarget)) open = false" @keydown.escape.window="if (open) { open = false; $refs.ausloeser.querySelector('button, a')?.focus() }" @close.stop="open = false">
    <div x-ref="ausloeser" @click="open = ! open" x-effect="$el.querySelector('button')?.setAttribute('aria-expanded', open)">
        {{ $trigger }}
    </div>

    <div x-show="open" x-ref="panel"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute z-50 mt-1.5 {{ $width }} rounded-xl glass-overlay {{ $alignmentClasses }}"
        style="display: none;"
        @click="open = false"
    >
        <div class="{{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
