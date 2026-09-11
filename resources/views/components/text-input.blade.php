@props(['disabled' => false])

<input
    @disabled($disabled)
    {{ $attributes->merge([
        'class' =>
            'h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text ' .
            'placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30'
    ]) }}
>
