@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-muted']) }}>
    {{ $value ?? $slot }}
</label>