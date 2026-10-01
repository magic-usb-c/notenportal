@props(['abbrechen' => null, 'schliessen' => null, 'laden' => true])
{{-- Abschluss eines Formulars wie in einem macOS-Sheet (HIG «Buttons»): Standardknopf ganz rechts, Abbrechen links daneben.
     Slot = Text des Standardknopfs; Slot «links» für eine Nebenaktion am anderen Ende (z. B. Löschen).
     «schliessen»: Name eines Drawers – Abbrechen schliesst ihn, statt eine Seite zu laden.
     laden=false, wenn das Formular kein Alpine-«loading» führt. --}}
<div {{ $attributes->class('flex items-center justify-end gap-2 pt-2') }}>
    @isset($links)
        <div class="mr-auto flex items-center gap-2">{{ $links }}</div>
    @endisset
    @if($schliessen)
        <button type="button" @click="$dispatch('close-drawer', @js($schliessen))" class="np-knopf np-knopf-sekundaer min-w-24">{{ __('Abbrechen') }}</button>
    @elseif($abbrechen)
        <a href="{{ $abbrechen }}" class="np-knopf np-knopf-sekundaer min-w-24">{{ __('Abbrechen') }}</a>
    @endif
    <button type="submit" @if($laden) :disabled="loading" @endif class="np-knopf np-knopf-primaer min-w-24">{{ $slot }}</button>
</div>
