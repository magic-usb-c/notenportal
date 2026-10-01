{{-- Feld der Anmeldeseiten: Bezeichnung über dem Feld, Hinweis und Fehler darunter und per aria-describedby verknüpft. --}}
@php
    $hinweis ??= null;
    $beschrieben = trim(($hinweis ? $name.'-hinweis ' : '').($errors->has($name) ? $name.'-fehler' : ''));
@endphp
<div>
    <label for="{{ $name }}" class="mb-1.5 block text-sm font-medium text-text">{{ $label }}</label>
    <input id="{{ $name }}" type="{{ $typ }}" name="{{ $name }}" value="{{ $wert ?? '' }}" required autocomplete="{{ $autocomplete }}"
           @if($autofocus ?? false) autofocus @endif
           @if($typ === 'email') spellcheck="false" autocapitalize="none" @endif
           class="np-feld h-11"
           @if($beschrieben !== '') aria-describedby="{{ $beschrieben }}" @endif
           @error($name) aria-invalid="true" @enderror>
    @if($hinweis)
        <p id="{{ $name }}-hinweis" class="mt-1.5 text-xs text-muted">{{ $hinweis }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-fehler" class="mt-1.5 text-xs text-note-ungenuegend">{{ $message }}</p>
    @enderror
</div>
