{{-- Miniatur-Vorschaukarte eines Farbthemas. $theme statisch oder reaktiv (x-bind:data-theme),
     Dunkelmodus/Akzent über weitergereichte Attribute (x-bind:class, x-bind:data-akzent). --}}
@props(['theme'])
@php
    $noten = ['bg-note-gut', 'bg-note-genuegend', 'bg-note-knapp', 'bg-note-ungenuegend'];
    $serien = ['bg-chart-1', 'bg-chart-2', 'bg-chart-3', 'bg-chart-4', 'bg-chart-5'];
@endphp
<div data-theme="{{ $theme }}" {{ $attributes->merge(['class' => 'rounded-lg border border-border bg-bg p-2', 'aria-hidden' => 'true']) }}>
    <div class="rounded-md border border-border bg-card p-2">
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-semibold text-text">Aa</span>
            <span class="h-4 w-8 rounded-sm bg-accent"></span>
        </div>
        <div class="mt-1.5 h-1.5 w-3/4 rounded-full bg-muted/50"></div>
        <div class="mt-2 flex gap-1">
            @foreach($noten as $klasse)<span class="h-2.5 flex-1 rounded-xs {{ $klasse }}"></span>@endforeach
        </div>
        <div class="mt-1 flex gap-1">
            @foreach($serien as $klasse)<span class="h-1.5 flex-1 rounded-full {{ $klasse }}"></span>@endforeach
        </div>
    </div>
</div>
