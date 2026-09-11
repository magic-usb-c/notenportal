{{-- Systemhinweis-Banner: Text/Art/Zielgruppe/Zeitfenster, Login-Hinweis. Leerer Text = aus. --}}
<section class="rounded-xl border border-border bg-card p-6 mt-5">
    <h3 class="text-sm font-semibold text-text">{{ __('Systemhinweis') }}</h3>
    <form method="POST" action="{{ route('admin.operations.notice.update') }}" class="mt-4 flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <div>
            <label for="hinweis_text" class="text-sm font-medium text-text">{{ __('Text') }}</label>
            <textarea id="hinweis_text" name="hinweis_text" rows="3" maxlength="300" aria-describedby="hinweis_text-fehler"
                      class="mt-1.5 w-full rounded-lg border border-border-strong/70 bg-input px-3 py-2 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30">{{ old('hinweis_text', $hinweisWerte['text']) }}</textarea>
            @error('hinweis_text')<p id="hinweis_text-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="hinweis_art" class="text-sm font-medium text-text">{{ __('Art') }}</label>
                <select id="hinweis_art" name="hinweis_art"
                        class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30">
                    @foreach(['info' => __('Info'), 'warnung' => __('Warnung')] as $wert => $name)
                        <option value="{{ $wert }}" @selected(old('hinweis_art', $hinweisWerte['art']) === $wert)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('hinweis_art')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="hinweis_zielgruppe" class="text-sm font-medium text-text">{{ __('Zielgruppe') }}</label>
                <select id="hinweis_zielgruppe" name="hinweis_zielgruppe"
                        class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30">
                    @foreach(['alle' => __('Alle'), 'lernende' => __('Lernende'), 'berufsbildner' => __('Berufsbildner'), 'admins' => __('Admins')] as $wert => $name)
                        <option value="{{ $wert }}" @selected(old('hinweis_zielgruppe', $hinweisWerte['zielgruppe']) === $wert)>{{ $name }}</option>
                    @endforeach
                </select>
                @error('hinweis_zielgruppe')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="hinweis_beginn" class="text-sm font-medium text-text">{{ __('Beginn') }}</label>
                <input id="hinweis_beginn" name="hinweis_beginn" type="datetime-local" value="{{ old('hinweis_beginn', $hinweisWerte['beginn']) }}"
                       class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30">
                @error('hinweis_beginn')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="hinweis_ende" class="text-sm font-medium text-text">{{ __('Ende') }}</label>
                <input id="hinweis_ende" name="hinweis_ende" type="datetime-local" value="{{ old('hinweis_ende', $hinweisWerte['ende']) }}"
                       class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30">
                @error('hinweis_ende')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
        </div>
        <label for="hinweis_login" class="flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
            <input id="hinweis_login" name="hinweis_login" type="checkbox" value="1" @checked(old('hinweis_login', $hinweisWerte['login']))
                   class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent focus:ring-2 focus:ring-ring/30">
            {{ __('Auch auf der Anmeldeseite zeigen') }}
        </label>
        {{-- «Speichern» steht im DOM zuerst: Enter in einem Feld löst den ersten Submit-Knopf aus und darf nie löschen. --}}
        <div class="flex flex-row-reverse justify-start gap-3">
            <button type="submit" :disabled="loading" class="inline-flex h-10 items-center rounded-lg bg-accent px-5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">{{ __('Speichern') }}</button>
            <button type="submit" name="hinweis_entfernen" value="1" formnovalidate :disabled="loading"
                    class="inline-flex h-10 items-center rounded-lg glass-btn px-5 text-sm font-medium text-text disabled:opacity-50">{{ __('Hinweis entfernen') }}</button>
        </div>
    </form>
</section>
