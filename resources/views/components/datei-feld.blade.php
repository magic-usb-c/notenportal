@props(['id', 'mehrere' => false, 'rahmen' => 'mt-1.5', 'ohneName' => false])
{{-- Dateiauswahl als Sekundärknopf mit Dateiname daneben. Das native Feld beschriftet seinen Knopf in der
     Sprache des Browsers («Choose File»), nicht der Oberfläche. Das Feld bleibt für Tastatur und
     Formularprüfung erhalten (sr-only, peer), weitere Attribute (name, accept, required, form, @change) gehen
     unverändert an das Feld. ohneName: wenn die Seite die gewählten Dateien selbst auflistet. --}}
<div x-data="{ dateiname: '' }" class="{{ $rahmen }} flex min-w-0 items-center gap-3">
    <input id="{{ $id }}" type="file" @if($mehrere) multiple @endif {{ $attributes->merge(['class' => 'peer sr-only']) }}
           x-on:input="dateiname = [...$event.target.files].map(f => f.name).join(', ')">
    <label for="{{ $id }}" class="np-knopf np-knopf-sekundaer shrink-0 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring">
        {{ $mehrere ? __('Dateien wählen') : __('Datei wählen') }}
    </label>
    @unless($ohneName)
        <span class="min-w-0 truncate text-sm text-muted" x-text="dateiname || @js(__('Keine Datei gewählt'))">{{ __('Keine Datei gewählt') }}</span>
    @endunless
</div>
