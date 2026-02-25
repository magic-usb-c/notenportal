<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' =>
            'inline-flex items-center px-4 py-2 rounded-xl font-semibold text-xs uppercase tracking-widest shadow-sm ' .
            'bg-red-600 text-white hover:bg-red-500 active:bg-red-700 ' .
            'focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:ring-offset-bg ' .
            'transition ease-in-out duration-150'
    ]) }}
>
    {{ $slot }}
</button>