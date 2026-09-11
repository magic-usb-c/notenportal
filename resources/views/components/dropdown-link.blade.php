<a {{
    $attributes->merge([
        'class' =>
            'flex w-full items-center rounded-lg px-3 min-h-9 text-start text-sm text-text ' .
            'transition-colors duration-100 hover:bg-surface-2 ' .
            'focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring'
    ])
}}>{{ $slot }}</a>
