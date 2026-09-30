<button
    {{ $attributes->merge([
        'type' => 'button',
        'class' => 'np-knopf np-knopf-sekundaer'
    ]) }}
>
    {{ $slot }}
</button>
