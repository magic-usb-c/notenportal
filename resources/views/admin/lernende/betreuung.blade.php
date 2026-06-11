<x-app-layout>
    <x-slot name="title">Betreuungen</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">
                Betreuung:
                <span class="text-muted">{{ $lernender->nachname }} {{ $lernender->vorname }}</span>
            </h2>
            <a href="{{ route('admin.lernende.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">


            @if($errors->any())
                <div class="bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-200 rounded-xl px-4 py-3 text-sm space-y-1">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Bestehende Betreuungen --}}
            <div class="glass rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-border font-semibold text-text">Bisherige Betreuungen</div>

                @forelse($betreuungen as $bt)
                    <div class="flex items-center justify-between px-5 py-3 border-b border-border last:border-0 hover:bg-bg">
                        <div>
                            <div class="font-medium text-text">{{ $bt->nachname }} {{ $bt->vorname }}</div>
                            <div class="text-xs text-muted">{{ $bt->email }}</div>
                            <div class="text-xs text-muted mt-0.5">
                                {{ \Carbon\Carbon::parse($bt->gueltig_von)->format('d.m.Y') }}
                                –
                                {{ $bt->gueltig_bis ? \Carbon\Carbon::parse($bt->gueltig_bis)->format('d.m.Y') : 'offen' }}
                            </div>
                        </div>
                        @if(!$bt->gueltig_bis)
                            <form method="POST"
                                  action="{{ route('admin.betreuungen.beenden', $bt->betreuung_id) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  onsubmit="return confirm('Betreuung wirklich beenden?');">
                                @csrf
                                <button :disabled="loading" class="px-3 py-1.5 rounded-xl bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 text-sm hover:opacity-80 disabled:opacity-60 disabled:cursor-not-allowed">
                                    Beenden
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-muted">Abgeschlossen</span>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-5 text-sm text-muted text-center">Noch keine Betreuungen erfasst.</div>
                @endforelse
            </div>

            {{-- Neue Betreuung erfassen --}}
            <div class="glass rounded-2xl p-5">
                <div class="font-semibold text-text mb-4">Neue Betreuung hinzufügen</div>

                <form method="POST"
                      action="{{ route('admin.lernende.betreuung.store', $lernender_id) }}"
                      class="space-y-4"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div>
                        <label for="berufsbildner_id" class="text-sm font-medium text-muted">Berufsbildner</label>
                        <select name="berufsbildner_id" id="berufsbildner_id" required
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Bitte wählen…</option>
                            @foreach($berufsbildner as $bb)
                                <option value="{{ $bb->berufsbildner_id }}" @selected(old('berufsbildner_id') == $bb->berufsbildner_id)>
                                    {{ $bb->nachname }} {{ $bb->vorname }} ({{ $bb->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="gueltig_von" class="text-sm font-medium text-muted">Gültig ab</label>
                        <input type="date" name="gueltig_von" id="gueltig_von" value="{{ old('gueltig_von', now()->toDateString()) }}" required
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    <button type="submit" :disabled="loading"
                            class="w-full px-4 py-2 rounded-xl bg-accent text-white np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                        Betreuung speichern
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
