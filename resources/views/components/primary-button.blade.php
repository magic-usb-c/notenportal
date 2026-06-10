<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' =>
            'inline-flex items-center px-4 py-2 rounded-xl font-semibold text-xs uppercase tracking-widest ' .
            'bg-accent text-white shadow-sm np-btn-primary active:opacity-80 ' .
            'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg ' .
            'transition ease-in-out duration-150'
    ]) }}
>
    {{ $slot }}
</button>