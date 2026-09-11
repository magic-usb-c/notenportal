<x-app-layout>
    <x-slot name="title">Fächer</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Fächer">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.subjects.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> Neues Fach
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg border-b border-border text-muted">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Kürzel</th>
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">Kategorie</th>
                            <th class="px-4 py-3 text-left font-medium">Track</th>
                            <th class="px-4 py-3 text-left font-medium">Lehrberufe</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($faecher as $f)
                            <tr class="hover:bg-bg/50">
                                <td class="px-4 py-3 font-mono font-semibold text-text">{{ $f->kurzname }}</td>
                                <td class="px-4 py-3 text-text">{{ $f->name }}</td>
                                <td class="px-4 py-3 text-muted">{{ $f->kategorie_name }}</td>
                                <td class="px-4 py-3">
                                    @if($f->track_typ)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-surface-2 text-text">
                                            {{ $f->track_typ }}
                                        </span>
                                    @else
                                        <span class="text-xs text-muted">–</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-muted">{{ $f->lehrberuf_count }}</td>
                                <td class="px-4 py-3">
                                    @if($f->aktiv)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-note-gut/14 text-note-gut">aktiv</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-bg text-muted border border-border">inaktiv</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.master-data.subjects.edit', $f->fach_id) }}"
                                       class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">Bearbeiten</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-muted">
                                    Noch keine Fächer erfasst.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

        </div>
    </div>
</x-app-layout>
