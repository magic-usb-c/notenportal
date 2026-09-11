@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-medium text-text']) }}>
    {{ $value ?? $slot }}
</label>
