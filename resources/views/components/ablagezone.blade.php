@props(['titel', 'id' => 'datei', 'name' => 'datei', 'symbol' => 'arrow-up-tray', 'hinweis' => null, 'polster' => 'py-10'])
{{-- Ablagezone für genau eine Datei: Klick öffnet die Dateiauswahl, Ziehen und Ablegen füllt dasselbe Feld. Das Feld
     bleibt für Tastatur und Formularprüfung erhalten (sr-only); weitere Attribute (accept, required) gehen ans Feld.
     Der Fehler zu «name» steht unter der Zone mit der id «{id}-fehler». --}}
@php
    $fehler = $errors->has($name);
    $ruhe = $fehler ? 'border-note-ungenuegend/70' : 'border-border-strong/60 hover:border-accent/60 hover:bg-fill-2';
@endphp
<div x-data="{ dateiname: '', ueber: false }">
    <label for="{{ $id }}"
           @class(['flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed px-6 text-center transition-colors duration-150',
                   'has-[input:focus-visible]:outline-2 has-[input:focus-visible]:outline-offset-2 has-[input:focus-visible]:outline-ring', $ruhe, $polster])
           :class="ueber && 'border-accent! bg-accent/5!'"
           @dragover.prevent="ueber = true"
           @dragleave.prevent="if (! $el.contains($event.relatedTarget)) ueber = false"
           @drop.prevent="ueber = false; if ($event.dataTransfer.files.length) { $refs.feld.files = $event.dataTransfer.files; $refs.feld.dispatchEvent(new Event('change', { bubbles: true })) }">
        <x-symbol :name="$symbol" class="size-8 text-accent-text" />
        <span class="max-w-full truncate text-sm font-medium text-text" x-text="dateiname || @js($titel)">{{ $titel }}</span>
        @if($hinweis)
            <span class="text-xs text-muted">{{ $hinweis }}</span>
        @endif
        <input id="{{ $id }}" x-ref="feld" name="{{ $name }}" type="file" {{ $attributes->merge(['class' => 'sr-only']) }}
               @change="dateiname = $event.target.files[0]?.name ?? ''"
               @if($fehler) aria-invalid="true" aria-describedby="{{ $id }}-fehler" @endif>
    </label>
    @error($name)
        <p id="{{ $id }}-fehler" class="mt-1.5 flex items-center gap-1.5 text-xs text-note-ungenuegend">
            <x-symbol name="exclamation-triangle" class="size-4 shrink-0" />{{ $message }}
        </p>
    @enderror
</div>
