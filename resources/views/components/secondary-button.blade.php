<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text disabled:opacity-50'
    ]) }}
>
    {{ $slot }}
</button>
