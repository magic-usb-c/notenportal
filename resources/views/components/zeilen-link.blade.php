@props(['href', 'label' => __('Bearbeiten'), 'zeile' => null])
{{-- Zeilenaktion in Tabellen, immer sichtbar (HIG: keine Aktion nur beim Überfahren). Der zugängliche Name nennt
     die Zeile («Bearbeiten: Englisch»), sonst hört man in einer Liste nur zwanzigmal «Bearbeiten». --}}
@php($name = $zeile !== null ? $label.': '.$zeile : $label)
<a href="{{ $href }}" aria-label="{{ $name }}"
   {{ $attributes->merge(['class' => 'np-knopf np-knopf-schlicht np-knopf-klein']) }}>{{ $label }}</a>
