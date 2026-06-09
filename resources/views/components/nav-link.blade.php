@props(['active'])

@php
$base = 'inline-flex items-center h-full px-1 border-b-2 text-sm font-medium ' .
        'transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg rounded-sm';
$classes = ($active ?? false)
    ? $base . ' border-accent text-text'
    : $base . ' border-transparent text-muted hover:text-text hover:border-border';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
