@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'text-sm font-medium text-text']) }}>
        {{ $status }}
    </div>
@endif
