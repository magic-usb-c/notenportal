@props(['active'])

@php
$base = 'inline-flex items-center h-full px-3 border-b-2 text-sm font-medium ' .
        'transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg rounded-sm';
$classes = ($active ?? false)
    ? $base . ' border-accent text-text bg-accent/10'
    : $base . ' border-transparent text-muted hover:text-text hover:border-border hover:bg-accent/5';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
