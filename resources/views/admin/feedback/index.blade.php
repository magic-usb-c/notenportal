<x-app-layout>
    <x-slot name="title">Feedback</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Feedback">
            <x-slot:aktionen>
                <a href="{{ route('admin.feedback.export', request()->query()) }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text whitespace-nowrap">
                    CSV-Export
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            @php($aktiveFilter = collect([$status, $kategorie, $rolle])->filter()->count())
            <x-filterleiste :action="route('admin.feedback.index')" :zaehler="$meldungen->total()" zaehler-label="Meldungen"
                             :zurueck="route('admin.feedback.index')" :aktive-filter="$aktiveFilter">
                <label for="status" class="sr-only">Status</label>
                <select name="status" id="status" x-on:change="$el.form.requestSubmit()"
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-40">
                    <option value="" @selected($status === '')>Status: alle</option>
                    @foreach(\App\Models\Feedback::STATUS as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="kategorie" class="sr-only">Kategorie</label>
                <select name="kategorie" id="kategorie" x-on:change="$el.form.requestSubmit()"
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-44">
                    <option value="" @selected($kategorie === '')>Kategorie: alle</option>
                    @foreach(\App\Models\Feedback::KATEGORIEN as $value => $label)
                        <option value="{{ $value }}" @selected($kategorie === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label for="rolle" class="sr-only">Rolle</label>
                <select name="rolle" id="rolle" x-on:change="$el.form.requestSubmit()"
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-40">
                    <option value="" @selected($rolle === '')>Rolle: alle</option>
                    <option value="Admin" @selected($rolle === 'Admin')>Admin</option>
                    <option value="Berufsbildner" @selected($rolle === 'Berufsbildner')>Berufsbildner</option>
                    <option value="Lernender" @selected($rolle === 'Lernender')>Lernender</option>
                </select>
            </x-filterleiste>

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-surface-2">
                            <tr>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">Datum</th>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">Absender</th>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">Kategorie</th>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted">Text</th>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">Status</th>
                                <th class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">Aktionen</th>
                            </tr>
                        </thead>
                        @forelse($meldungen as $m)
                            <tbody x-data="{
                                    open: false,
                                    status: '{{ $m->status }}',
                                    notiz: @js($m->admin_notiz ?? ''),
                                    saving: false,
                                    savedOk: false,
                                    async speichern() {
                                        this.saving = true;
                                        this.savedOk = false;
                                        try {
                                            const res = await fetch('{{ route('admin.feedback.update', $m->feedback_id) }}', {
                                                method: 'PATCH',
                                                headers: {
                                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                                    'Content-Type': 'application/json',
                                                    'Accept': 'application/json',
                                                },
                                                body: JSON.stringify({ status: this.status, admin_notiz: this.notiz }),
                                            });
                                            if (res.ok) {
                                                this.savedOk = true;
                                                setTimeout(() => this.savedOk = false, 2000);
                                            }
                                        } finally {
                                            this.saving = false;
                                        }
                                    },
                                }"
                                class="divide-y divide-border">
                                <tr class="hover:bg-surface-2/60">
                                    <td class="px-3 py-2.5 text-muted whitespace-nowrap align-top">{{ $m->erstellt_am->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2.5 whitespace-nowrap align-top">
                                        <div class="font-medium">{{ $m->nachname }} {{ $m->vorname }}</div>
                                        <div class="text-xs text-muted">{{ $m->rollen }}</div>
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap align-top">{{ \App\Models\Feedback::KATEGORIEN[$m->kategorie] ?? $m->kategorie }}</td>
                                    <td class="px-3 py-2.5 max-w-sm align-top">
                                        <span class="whitespace-pre-wrap">{{ Str::limit($m->text, 160) }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap align-top">
                                        <x-status :status="match ($m->status) {
                                                \App\Models\Feedback::STATUS_ERLEDIGT => 'gruen',
                                                \App\Models\Feedback::STATUS_IN_ARBEIT => 'neutral',
                                                default => 'gelb',
                                            }"
                                            :text="\App\Models\Feedback::STATUS[$m->status] ?? $m->status" />
                                    </td>
                                    <td class="px-3 py-2.5 text-right align-top whitespace-nowrap">
                                        <button type="button" @click="open = !open"
                                                class="px-3 py-1.5 rounded-lg border border-border text-xs hover:bg-surface-2">
                                            <span x-text="open ? 'Schliessen' : 'Details'"></span>
                                        </button>
                                    </td>
                                </tr>
                                <tr x-show="open" x-cloak>
                                    <td colspan="6" class="p-4 bg-surface-2/60">
                                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                                            <div class="space-y-2 text-sm">
                                                <p class="whitespace-pre-wrap">{{ $m->text }}</p>
                                                <dl class="text-xs text-muted space-y-1">
                                                    <div><dt class="inline font-medium">Rolle:</dt> <dd class="inline">{{ $m->rolle ?? '–' }}</dd></div>
                                                    <div><dt class="inline font-medium">Route:</dt> <dd class="inline">{{ $m->route_name ?? '–' }}</dd></div>
                                                    <div><dt class="inline font-medium">URL:</dt> <dd class="inline">{{ $m->url ?? '–' }}</dd></div>
                                                    <div><dt class="inline font-medium">Viewport:</dt> <dd class="inline">{{ $m->viewport ?? '–' }}</dd></div>
                                                    <div><dt class="inline font-medium">Browser:</dt> <dd class="inline">{{ $m->browser ?? $m->user_agent ?? '–' }}</dd></div>
                                                    <div><dt class="inline font-medium">E-Mail:</dt> <dd class="inline">{{ $m->email }}</dd></div>
                                                </dl>
                                                @if(!empty($m->js_fehler))
                                                    <div>
                                                        <p class="font-medium text-xs text-muted mt-2">Letzte JS-Fehler</p>
                                                        <ul class="text-xs text-muted list-disc list-inside">
                                                            @foreach($m->js_fehler as $fehler)
                                                                <li class="break-words">{{ $fehler }}</li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                                @if($m->hatScreenshot())
                                                    <div>
                                                        <p class="font-medium text-xs text-muted mt-2 mb-1">Screenshot</p>
                                                        <a href="{{ route('admin.feedback.screenshot', $m->feedback_id) }}" target="_blank" rel="noopener">
                                                            <img src="{{ route('admin.feedback.screenshot', $m->feedback_id) }}" alt="Screenshot der Meldung"
                                                                 class="max-w-full max-h-64 rounded-xl border border-border">
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                            <div class="space-y-2">
                                                <div>
                                                    <label for="status-{{ $m->feedback_id }}" class="text-sm font-medium text-text">Status</label>
                                                    <select id="status-{{ $m->feedback_id }}" x-model="status"
                                                            class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                                                        @foreach(\App\Models\Feedback::STATUS as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="notiz-{{ $m->feedback_id }}" class="text-sm font-medium text-text">Antwort an die meldende Person</label>
                                                    <textarea id="notiz-{{ $m->feedback_id }}" x-model="notiz" rows="3"
                                                              class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm"></textarea>
                                                </div>
                                                <div class="flex items-center gap-3">
                                                    <button type="button" @click="speichern()" :disabled="saving"
                                                            class="px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary text-sm disabled:opacity-50">
                                                        <span x-show="!saving">Speichern</span>
                                                        <span x-show="saving">…</span>
                                                    </button>
                                                    <span x-show="savedOk" x-cloak class="text-xs text-note-gut">Gespeichert.</span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        @empty
                            <tbody>
                                <tr>
                                    <td colspan="6" class="p-6 text-center text-muted">Keine Meldungen gefunden.</td>
                                </tr>
                            </tbody>
                        @endforelse
                    </table>
                </div>

                @if($meldungen->hasPages())
                    <div class="px-4 py-3 border-t border-border">
                        {{ $meldungen->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
