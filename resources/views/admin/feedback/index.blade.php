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

            <div class="rounded-xl border border-border bg-card p-4">
                <form method="GET" action="{{ route('admin.feedback.index') }}"
                      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                    <div>
                        <label for="status" class="text-xs uppercase tracking-widest text-muted font-medium">Status</label>
                        <select name="status" id="status" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="" @selected($status === '')>Alle</option>
                            @foreach(\App\Models\Feedback::STATUS as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="kategorie" class="text-xs uppercase tracking-widest text-muted font-medium">Kategorie</label>
                        <select name="kategorie" id="kategorie" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="" @selected($kategorie === '')>Alle</option>
                            @foreach(\App\Models\Feedback::KATEGORIEN as $value => $label)
                                <option value="{{ $value }}" @selected($kategorie === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="rolle" class="text-xs uppercase tracking-widest text-muted font-medium">Rolle</label>
                        <select name="rolle" id="rolle" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="" @selected($rolle === '')>Alle</option>
                            <option value="Admin" @selected($rolle === 'Admin')>Admin</option>
                            <option value="Berufsbildner" @selected($rolle === 'Berufsbildner')>Berufsbildner</option>
                            <option value="Lernender" @selected($rolle === 'Lernender')>Lernender</option>
                        </select>
                    </div>
                    @if($status || $kategorie || $rolle)
                        <div>
                            <a href="{{ route('admin.feedback.index') }}"
                               class="inline-flex px-3 py-2 h-10 rounded-xl glass-btn text-text items-center text-sm">
                                Filter zurücksetzen
                            </a>
                        </div>
                    @endif
                </form>
            </div>

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-bg text-muted shadow-xs">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                <th class="text-left p-3 whitespace-nowrap">Absender</th>
                                <th class="text-left p-3 whitespace-nowrap">Kategorie</th>
                                <th class="text-left p-3">Text</th>
                                <th class="text-left p-3 whitespace-nowrap">Status</th>
                                <th class="text-right p-3 whitespace-nowrap">Aktionen</th>
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
                                class="divide-y divide-border even:bg-bg/30">
                                <tr>
                                    <td class="p-3 text-muted whitespace-nowrap align-top">{{ $m->erstellt_am->format('d.m.Y H:i') }}</td>
                                    <td class="p-3 whitespace-nowrap align-top">
                                        <div class="font-medium">{{ $m->nachname }} {{ $m->vorname }}</div>
                                        <div class="text-xs text-muted">{{ $m->rollen }}</div>
                                    </td>
                                    <td class="p-3 whitespace-nowrap align-top">{{ \App\Models\Feedback::KATEGORIEN[$m->kategorie] ?? $m->kategorie }}</td>
                                    <td class="p-3 max-w-sm align-top">
                                        <span class="whitespace-pre-wrap">{{ Str::limit($m->text, 160) }}</span>
                                    </td>
                                    <td class="p-3 whitespace-nowrap align-top">
                                        <x-status :status="match ($m->status) {
                                                \App\Models\Feedback::STATUS_ERLEDIGT => 'gruen',
                                                \App\Models\Feedback::STATUS_IN_ARBEIT => 'neutral',
                                                default => 'gelb',
                                            }"
                                            :text="\App\Models\Feedback::STATUS[$m->status] ?? $m->status" />
                                    </td>
                                    <td class="p-3 text-right align-top whitespace-nowrap">
                                        <button type="button" @click="open = !open"
                                                class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg">
                                            <span x-text="open ? 'Schliessen' : 'Details'"></span>
                                        </button>
                                    </td>
                                </tr>
                                <tr x-show="open" x-cloak>
                                    <td colspan="6" class="p-4 bg-bg/40">
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
                                                    <label for="status-{{ $m->feedback_id }}" class="text-xs uppercase tracking-widest text-muted font-medium">Status</label>
                                                    <select id="status-{{ $m->feedback_id }}" x-model="status"
                                                            class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                                                        @foreach(\App\Models\Feedback::STATUS as $value => $label)
                                                            <option value="{{ $value }}">{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div>
                                                    <label for="notiz-{{ $m->feedback_id }}" class="text-xs uppercase tracking-widest text-muted font-medium">Antwort an die meldende Person</label>
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
