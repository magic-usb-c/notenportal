<x-app-layout>
    <x-slot name="title">Lernender bearbeiten</x-slot>
    <x-slot name="header">
        <nav class="mb-1 flex items-center gap-1 text-xs text-muted" aria-label="Brotkrumen">
            <a href="{{ route("{$bereich}.learners.index") }}" class="transition-colors hover:text-text">Lernende</a>
            <span class="text-muted/40">›</span>
            <a href="{{ route("{$bereich}.learners.show", $lernender->lernender_id) }}" class="transition-colors hover:text-text">
                {{ $lernender->benutzer->nachname }} {{ $lernender->benutzer->vorname }}
            </a>
            <span class="text-muted/40">›</span>
            <span class="text-text">Bearbeiten</span>
        </nav>
        <x-seitenkopf titel="Lernender bearbeiten" schmal>
            <x-slot:aktionen>
                <a href="{{ route("{$bereich}.learners.show", $lernender->lernender_id) }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">Zurück</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $label = 'text-xs font-medium text-muted';
        $benutzer = $lernender->benutzer;
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl">
            <form method="POST" action="{{ route("{$bereich}.learners.update", $lernender->lernender_id) }}"
                  class="rounded-xl border border-border bg-card p-6 space-y-5"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="vorname" class="{{ $label }}">Vorname *</label>
                        <input id="vorname" type="text" name="vorname" value="{{ old('vorname', $benutzer->vorname) }}" required maxlength="100" class="{{ $feld }}">
                        @error('vorname')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="nachname" class="{{ $label }}">Nachname *</label>
                        <input id="nachname" type="text" name="nachname" value="{{ old('nachname', $benutzer->nachname) }}" required maxlength="100" class="{{ $feld }}">
                        @error('nachname')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="{{ $label }}">E-Mail *</label>
                        <input id="email" type="email" name="email" value="{{ old('email', $benutzer->email) }}" required maxlength="255" class="{{ $feld }}">
                        @error('email')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="benutzername" class="{{ $label }}">Benutzername *</label>
                        <input id="benutzername" type="text" name="benutzername" value="{{ old('benutzername', $benutzer->benutzername) }}" required maxlength="50" class="{{ $feld }} font-mono">
                        @error('benutzername')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="border-t border-border pt-5 space-y-4">
                    <div>
                        <label for="lehrberuf_id" class="{{ $label }}">Lehrberuf *</label>
                        <select id="lehrberuf_id" name="lehrberuf_id" required class="{{ $feld }}">
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}" @selected(old('lehrberuf_id', $lernender->lehrberuf_id) == $lb->lehrberuf_id)>{{ $lb->name }}</option>
                            @endforeach
                        </select>
                        @error('lehrberuf_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label for="lehrbeginn" class="{{ $label }}">Lehrbeginn *</label>
                            <input id="lehrbeginn" type="date" name="lehrbeginn" required
                                   value="{{ old('lehrbeginn', $lernender->lehrbeginn?->format('Y-m-d')) }}" class="{{ $feld }}">
                            @error('lehrbeginn')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="lehrende" class="{{ $label }}">Lehrende</label>
                            <input id="lehrende" type="date" name="lehrende"
                                   value="{{ old('lehrende', $lernender->lehrende?->format('Y-m-d')) }}" class="{{ $feld }}">
                            @error('lehrende')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="bemerkung" class="{{ $label }}">Bemerkung (intern)</label>
                        <textarea id="bemerkung" name="bemerkung" rows="4" maxlength="5000" class="{{ $feld }}">{{ old('bemerkung', $lernender->bemerkung) }}</textarea>
                        @error('bemerkung')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <button type="submit" :disabled="loading"
                        class="w-full h-12 rounded-xl bg-accent text-accent-contrast font-semibold hover:opacity-90 transition-colors duration-150 inline-flex items-center justify-center gap-2 disabled:opacity-60 disabled:cursor-not-allowed">
                    Speichern
                </button>
            </form>
            </div>
        </div>
    </div>
</x-app-layout>
