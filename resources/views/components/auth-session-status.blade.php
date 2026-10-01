@props(['status'])

@if ($status)
    <div role="status" {{ $attributes->merge(['class' => 'rounded-xl bg-fill-2 px-4 py-3 text-sm text-text']) }}>
        {{ $status }}
    </div>
@endif
