{{-- Schwebender Feedback-Knopf (Block G, Testphase): ein-/ausschaltbar, bis alles stabil läuft. --}}
<section class="rounded-xl border border-border bg-card p-6 mt-5">
    <h3 class="text-sm font-semibold text-text">{{ __('Feedback-Knopf') }}</h3>
    <p class="mt-1 text-sm text-muted">{{ __('Schwebender Knopf unten rechts, mit dem Angemeldete während der Testphase Rückmeldungen und Fehler melden können.') }}</p>
    <form method="POST" action="{{ route('admin.operations.feedback-button.update') }}" class="mt-4 flex flex-col gap-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) setTimeout(() => loading = true)">
        @csrf
        @method('PUT')
        <label for="feedback_knopf" class="flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
            <input id="feedback_knopf" name="feedback_knopf" type="checkbox" value="1" @checked(old('feedback_knopf', $feedbackKnopfAktiv))
                   class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent-text focus:ring-2 focus:ring-ring/30">
            {{ __('Feedback-Knopf einblenden') }}
        </label>
        <div class="flex justify-end">
            <button type="submit" :disabled="loading" class="inline-flex h-10 items-center rounded-lg bg-accent px-5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">{{ __('Speichern') }}</button>
        </div>
    </form>
</section>
