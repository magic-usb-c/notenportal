@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'p-1 text-text',
])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right inset-s-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left inset-e-0',
};

$width = match ($width) {
    '48' => 'w-48',
    '56' => 'w-56',
    default => $width,
};
@endphp

{{-- Menü (G2 Overlay): Glas nur für die schwebende Ebene, 150 ms --}}
<div class="relative" x-data="{ open: false }" @click.outside="open = false" @focusout="if ($event.relatedTarget && ! $el.contains($event.relatedTarget)) open = false" @keydown.escape.window="if (open) { open = false; $refs.ausloeser.querySelector('button, a')?.focus() }" @close.stop="open = false">
    <div x-ref="ausloeser" @click="open = ! open" x-effect="$el.querySelector('button')?.setAttribute('aria-expanded', open)">
        {{ $trigger }}
    </div>

    <div x-show="open"
        x-transition:enter="transition-opacity ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-100"
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
