@props(['status', 'text' => null])
@php
    [$punkt, $pill, $standard] = match ($status) {
        'rot' => ['bg-red-500', 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300', 'kritisch'],
        'gelb' => ['bg-yellow-500', 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300', 'beobachten'],
        'neutral' => ['bg-muted', 'bg-bg text-muted border border-border', 'offen'],
        default => ['bg-green-500', 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300', 'im Plan'],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-semibold whitespace-nowrap '.$pill]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $punkt }}" aria-hidden="true"></span>{{ $text ?? $standard }}
</span>
