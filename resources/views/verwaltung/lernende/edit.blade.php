<x-app-layout>
    <x-slot name="title">{{ __('Lernender bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.show', $lernender->lernender_id)" :titel="__('Lernender bearbeiten')" schmal>
        </x-seitenkopf>
    </x-slot>

    @php
        $feld = 'np-feld mt-1';
        $label = 'text-sm font-medium text-text';
        $benutzer = $lernender->benutzer;
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <div class="max-w-3xl">
            <form method="POST" action="{{ route("{$bereich}.learners.update", $lernender->lernender_id) }}"
                  class="np-karte p-6 space-y-5"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="vorname" class="{{ $label }}">{{ __('Vorname *') }}</label>
                        <input id="vorname" type="text" name="vorname" value="{{ old('vorname', $benutzer->vorname) }}" required maxlength="100" class="{{ $feld }}">
                        @error('vorname')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="nachname" class="{{ $label }}">{{ __('Nachname *') }}</label>
                        <input id="nachname" type="text" name="nachname" value="{{ old('nachname', $benutzer->nachname) }}" required maxlength="100" class="{{ $feld }}">
                        @error('nachname')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="{{ $label }}">{{ __('E-Mail *') }}</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $benutzer->email) }}" required maxlength="255" class="{{ $feld }}">
                        @error('email')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="benutzername" class="{{ $label }}">{{ __('Benutzername *') }}</label>
                        <input id="benutzername" type="text" name="benutzername" value="{{ old('benutzername', $benutzer->benutzername) }}" required maxlength="50" class="{{ $feld }} tabular-nums">
                        @error('benutzername')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="border-t border-border pt-5 space-y-4">
                    <div>
                        <label for="lehrberuf_id" class="{{ $label }}">{{ __('Lehrberuf *') }}</label>
                        <select id="lehrberuf_id" name="lehrberuf_id" required class="{{ $feld }}">
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}" @selected(old('lehrberuf_id', $lernender->lehrberuf_id) == $lb->lehrberuf_id)>{{ $lb->name }}</option>
                            @endforeach
                        </select>
                        @error('lehrberuf_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="lehrbeginn" class="{{ $label }}">{{ __('Lehrbeginn *') }}</label>
                            <input id="lehrbeginn" type="date" name="lehrbeginn" required
                                   value="{{ old('lehrbeginn', $lernender->lehrbeginn?->format('Y-m-d')) }}" class="{{ $feld }}">
                            @error('lehrbeginn')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="lehrende" class="{{ $label }}">{{ __('Lehrende') }}</label>
                            <input id="lehrende" type="date" name="lehrende"
                                   value="{{ old('lehrende', $lernender->lehrende?->format('Y-m-d')) }}" class="{{ $feld }}">
                            @error('lehrende')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="bemerkung" class="{{ $label }}">{{ __('Bemerkung (intern)') }}</label>
                        <textarea id="bemerkung" name="bemerkung" rows="4" maxlength="5000" class="{{ $feld }}">{{ old('bemerkung', $lernender->bemerkung) }}</textarea>
                        @error('bemerkung')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <x-formular-aktionen :abbrechen="route($bereich.'.learners.show', $lernender->lernender_id)">{{ __('Speichern') }}</x-formular-aktionen>
            </form>
            </div>
        </div>
    </div>
</x-app-layout>
