@props(['href', 'label' => __('Bearbeiten'), 'zeile' => null])
{{-- Zeilenaktion in Tabellen: ab einem Container von 36rem der Text, darunter ein Stift, damit die Tabelle auch bei
     320 px nicht quer scrollt. Braucht ein @container-Vorfahren. Der zugängliche Name nennt die Zeile
     («Bearbeiten: Englisch»), sonst hört man in einer Liste nur zwanzigmal «Bearbeiten». --}}
@php($name = $zeile !== null ? $label.': '.$zeile : $label)
<a href="{{ $href }}" aria-label="{{ $name }}" title="{{ $name }}"
   {{ $attributes->merge(['class' => 'np-knopf np-knopf-schlicht np-knopf-klein np-ziel @max-xl:aspect-square @max-xl:px-0']) }}>
    <span class="hidden @xl:inline">{{ $label }}</span>
    <svg class="@xl:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125"/></svg>
</a>
