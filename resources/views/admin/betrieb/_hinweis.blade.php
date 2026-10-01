{{-- Systemhinweis: Banner für eine Zielgruppe, optional mit Zeitfenster und auf der Anmeldeseite. Leerer Text = aus. --}}
@php
    $wert = fn (string $k) => old('hinweis_'.$k, $hinweisWerte[$k]);
    $aktiv = filled($hinweisWerte['text']);
@endphp
<form method="POST" action="{{ route('admin.operations.notice.update') }}#hinweis" class="flex flex-col gap-8"
      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) setTimeout(() => loading = true)">
    @csrf
    @method('PUT')
    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Systemhinweis') }}</h2>
        <div class="np-karte np-gruppe">
            <div class="px-4 py-3">
                <label for="hinweis_text" class="sr-only">{{ __('Text') }}</label>
                <textarea id="hinweis_text" name="hinweis_text" rows="3" maxlength="300" placeholder="{{ __('Text des Hinweises') }}"
                          class="np-feld" @error('hinweis_text') aria-invalid="true" aria-describedby="hinweis_text-fehler" @enderror>{{ $wert('text') }}</textarea>
                @error('hinweis_text')<p id="hinweis_text-fehler" class="mt-1.5 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
            <x-einstellung :label="__('Art')" name="hinweis_art">
                <x-segment-auswahl name="hinweis_art" :wert="$wert('art')" :optionen="['info' => __('Info'), 'warnung' => __('Warnung')]" />
            </x-einstellung>
            <x-einstellung :label="__('Zielgruppe')" name="hinweis_zielgruppe">
                <x-segment-auswahl name="hinweis_zielgruppe" :wert="$wert('zielgruppe')"
                                   :optionen="['alle' => __('Alle'), 'lernende' => __('Lernende'), 'berufsbildner' => __('Berufsbildner'), 'admins' => __('Admins')]" />
            </x-einstellung>
            <x-einstellung :label="__('Auch auf der Anmeldeseite')" fuer="hinweis_login" name="hinweis_login">
                <input id="hinweis_login" name="hinweis_login" type="checkbox" role="switch" value="1" @checked($wert('login')) class="np-schalter">
            </x-einstellung>
        </div>
    </section>

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Zeitfenster') }}</h2>
        <div class="np-karte np-gruppe">
            @foreach(['beginn' => __('Beginn'), 'ende' => __('Ende')] as $k => $text)
                <x-einstellung :label="$text" :fuer="'hinweis_'.$k" :name="'hinweis_'.$k">
                    <input id="hinweis_{{ $k }}" name="hinweis_{{ $k }}" type="datetime-local" value="{{ $wert($k) }}"
                           class="np-feld w-60 tabular-nums" @error('hinweis_'.$k) aria-invalid="true" aria-describedby="{{ 'hinweis_'.$k }}-fehler" @enderror>
                </x-einstellung>
            @endforeach
        </div>
        <p class="mt-2 px-1 text-xs text-muted">{{ __('Ohne Beginn gilt der Hinweis sofort, ohne Ende bis er entfernt wird.') }}</p>
    </section>

    {{-- «Speichern» steht im DOM zuerst: Enter in einem Feld löst den ersten Submit-Knopf aus und darf nie entfernen. --}}
    <div class="flex flex-row-reverse items-center gap-3">
        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Speichern') }}</button>
        @if($aktiv)
            <button type="submit" name="hinweis_entfernen" value="1" formnovalidate :disabled="loading"
                    class="np-knopf np-knopf-schlicht mr-auto">{{ __('Hinweis entfernen') }}</button>
        @endif
    </div>
</form>
