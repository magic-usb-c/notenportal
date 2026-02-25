@props(['disabled' => false])

<input
    @disabled($disabled)
    {{ $attributes->merge([
        'class' =>
            'w-full rounded-xl bg-input text-text border border-border shadow-sm ' .
            'placeholder:text-muted/70 ' .
            'focus:outline-none focus:ring-2 focus:ring-ring focus:border-ring'
    ]) }}
>