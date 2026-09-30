<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' => 'np-knopf np-knopf-primaer'
    ]) }}
>
    {{ $slot }}
</button>
