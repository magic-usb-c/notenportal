<x-app-layout>
    <x-slot name="title">Profil bearbeiten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">Profil bearbeiten</h2>
                <p class="text-sm text-muted mt-0.5">{{ $lernender->nachname }} {{ $lernender->vorname }}</p>
            </div>
            <a href="{{ route('admin.lernende.show', $lernender_id) }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">

                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('admin.lernende.profil.update', $lernender_id) }}"
                      class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <label class="text-sm font-medium text-muted">Lehrberuf</label>
                        <select name="lehrberuf_id"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">– kein Lehrberuf –</option>
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}"
                                    @selected(old('lehrberuf_id', $profil?->lehrberuf_id) == $lb->lehrberuf_id)>
                                    {{ $lb->name }}
                                    @if($lb->kuerzel) ({{ $lb->kuerzel }}) @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Lehrbeginn</label>
                            <input type="date" name="lehrbeginn"
                                   value="{{ old('lehrbeginn', $profil?->lehrbeginn) }}"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrbeginn') border-red-400 @enderror">
                            @error('lehrbeginn')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Lehrende</label>
                            <input type="date" name="lehrende"
                                   value="{{ old('lehrende', $profil?->lehrende) }}"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('lehrende') border-red-400 @enderror">
                            @error('lehrende')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="submit"
                                class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                            Speichern
                        </button>
                        <a href="{{ route('admin.lernende.show', $lernender_id) }}"
                           class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg">
                            Abbrechen
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
