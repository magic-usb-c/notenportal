@props(['name', 'strich' => '1.5'])
{{-- Symbol aus App\Support\Symbole (Heroicons 2, Outline). Grösse über die Klasse (size-4, size-5), Farbe über currentColor. --}}
<svg {{ $attributes->class('shrink-0') }} fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="{{ $strich }}" aria-hidden="true" focusable="false">@foreach(\App\Support\Symbole::pfade($name) as $d)<path stroke-linecap="round" stroke-linejoin="round" d="{{ $d }}"/>@endforeach</svg>
