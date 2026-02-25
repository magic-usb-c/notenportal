<a {{
    $attributes->merge([
        'class' =>
            'block w-full px-4 py-2 text-start text-sm leading-5 ' .
            'text-text hover:bg-card/60 ' .
            'focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:ring-offset-bg ' .
            'transition duration-150 ease-in-out'
    ])
}}>{{ $slot }}</a>