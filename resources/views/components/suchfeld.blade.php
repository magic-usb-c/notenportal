@props([
    'label' => __('Suchen'),
    'platzhalter' => __('Suchen'),
])
{{-- Suchfeld mit Lupe als Kapsel (HIG «Search fields»). Klassen gelten dem Rahmen, alle übrigen Attribute
     (x-model, name, value, id) dem Eingabefeld. --}}
<div class="relative {{ $attributes->get('class') }}">
    <x-symbol name="magnifying-glass" strich="2" class="pointer-events-none absolute left-2.5 top-1/2 size-3.5 -translate-y-1/2 text-muted" />
    <input type="search" placeholder="{{ $platzhalter }}" aria-label="{{ $label }}" {{ $attributes->except('class') }} class="np-feld np-feld-klein np-suchfeld">
</div>
