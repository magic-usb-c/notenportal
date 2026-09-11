<x-app-layout>
    <x-slot name="title">Benachrichtigungen</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Benachrichtigungen" />
    </x-slot>

    @php
        $toggle = 'inline-flex items-center gap-1.5 px-3 h-9 rounded-xl border border-border bg-input text-xs text-text cursor-pointer '
            .'has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent has-focus-visible:ring-2 has-focus-visible:ring-ring has-disabled:opacity-60 has-disabled:cursor-not-allowed';
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-2 py-1.5 text-sm focus:ring-2 focus:ring-ring focus:border-ring';
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.notifications.update') }}" class="flex flex-col gap-5"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('PUT')

                @foreach($gruppen as $gruppenKey => $gruppenLabel)
                    @php $anlaesseInGruppe = array_filter($anlaesse, fn ($a) => $a['group'] === $gruppenKey); @endphp
                    @continue(empty($anlaesseInGruppe))
                    <section class="rounded-xl border border-border bg-card overflow-hidden">
                        <h3 class="px-5 pt-4 pb-3 text-sm font-semibold text-text border-b border-border">{{ $gruppenLabel }}</h3>
                        <div class="divide-y divide-border">
                            @foreach($anlaesseInGruppe as $type => $a)
                                <div class="p-5 flex flex-col gap-3">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="text-sm font-medium text-text flex items-center gap-2">
                                                {{ $a['label'] }}
                                                @if($a['locked'] ?? false)
                                                    <svg class="w-3.5 h-3.5 text-muted" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 2a4 4 0 00-4 4v2H5a1 1 0 00-1 1v8a1 1 0 001 1h10a1 1 0 001-1V9a1 1 0 00-1-1h-1V6a4 4 0 00-4-4zm2 6V6a2 2 0 10-4 0v2h4z" clip-rule="evenodd"/></svg>
                                                    <span class="sr-only">verpflichtend, nicht änderbar</span>
                                                @endif
                                            </div>
                                            <p class="text-xs text-muted mt-0.5">{{ $a['description'] }}</p>
                                            <p class="text-[11px] text-muted mt-1">{{ implode(', ', $a['roles']) }}</p>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                                            <input type="hidden" name="policies[{{ $type }}][enabled]" value="0">
                                            <label class="{{ $toggle }}">
                                                <input type="checkbox" name="policies[{{ $type }}][enabled]" value="1" class="sr-only"
                                                       @checked($a['enabled']) @disabled($a['locked'] ?? false)>
                                                Aktiv
                                            </label>
                                            <input type="hidden" name="policies[{{ $type }}][mandatory]" value="0">
                                            <label class="{{ $toggle }}">
                                                <input type="checkbox" name="policies[{{ $type }}][mandatory]" value="1" class="sr-only"
                                                       @checked($a['mandatory']) @disabled($a['locked'] ?? false)>
                                                Verpflichtend
                                            </label>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-end gap-3">
                                        @if(count($a['frequencies']) > 1)
                                            <div>
                                                <label for="frequency-{{ $type }}" class="text-sm font-medium text-text">Standard-Frequenz</label>
                                                <select id="frequency-{{ $type }}" name="policies[{{ $type }}][frequency]" @disabled($a['locked'] ?? false) class="{{ $feld }}">
                                                    @foreach($a['frequencies'] as $f)
                                                        <option value="{{ $f }}" @selected($a['frequency'] === $f)>{{ \App\Services\Notifications\NotificationCatalog::FREQUENCIES[$f] }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        @endif
                                        @foreach($a['params'] as $name => $p)
                                            <div>
                                                <label for="param-{{ $type }}-{{ $name }}" class="text-sm font-medium text-text">{{ $p['label'] }}</label>
                                                <input id="param-{{ $type }}-{{ $name }}" type="number" name="policies[{{ $type }}][params][{{ $name }}]"
                                                       value="{{ old('policies.'.$type.'.params.'.$name, $a['paramWerte'][$name]) }}"
                                                       min="{{ $p['min'] }}" max="{{ $p['max'] }}" @disabled($a['locked'] ?? false) class="{{ $feld }} tabular-nums w-24">
                                                @error('policies.'.$type.'.params.'.$name)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                                            </div>
                                        @endforeach
                                    </div>
                                    @error('policies.'.$type.'.frequency')<p class="text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <div class="flex justify-end">
                    <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary disabled:opacity-60">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
