<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => 'inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50'
    ]) }}
>
    {{ $slot }}
</button>
