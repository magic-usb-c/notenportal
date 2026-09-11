{{-- Gefährlich, solid: nur im Bestätigungsdialog --}}
<button
    {{ $attributes->merge([
        'type' => 'submit',
        'class' =>
            'inline-flex h-9 items-center gap-2 rounded-lg px-3.5 text-sm font-medium ' .
            'bg-note-ungenuegend text-accent-contrast transition-colors duration-100 hover:bg-note-ungenuegend/90 ' .
            'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-50'
    ]) }}
>
    {{ $slot }}
</button>
