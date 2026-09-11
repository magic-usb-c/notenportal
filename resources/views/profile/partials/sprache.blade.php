{{-- Sprache der Oberfläche und der Mails (nur mit eingeschalteter Sprachwahl) --}}
<div class="rounded-xl border border-border bg-card p-6">
    <form method="POST" action="{{ route('profile.locale') }}" class="flex flex-col gap-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <fieldset>
            <legend class="font-semibold text-text text-sm">{{ __('Sprache') }}</legend>
            <div class="mt-3 grid grid-cols-2 gap-2 sm:max-w-xs">
                @foreach(['de' => 'Deutsch', 'en' => 'English'] as $wert => $name)
                    <label lang="{{ $wert }}" class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                  has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                        <input type="radio" name="locale" value="{{ $wert }}" class="sr-only" @checked(app()->getLocale() === $wert)>
                        {{ $name }}
                    </label>
                @endforeach
            </div>
            @error('locale')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </fieldset>
        <div>
            <button type="submit" :disabled="loading"
                    class="inline-flex h-10 items-center rounded-xl glass-btn px-5 text-sm font-medium text-text disabled:opacity-60">
                {{ __('Sprache speichern') }}
            </button>
        </div>
    </form>
</div>
