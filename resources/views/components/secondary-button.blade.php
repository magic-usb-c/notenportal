<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' =>
            'inline-flex items-center px-4 py-2 rounded-xl font-semibold text-xs uppercase tracking-widest shadow-sm ' .
            'glass-btn text-text ' .
            'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg ' .
            'disabled:opacity-50 transition ease-in-out duration-150'
    ]) }}
>
    {{ $slot }}
</button>