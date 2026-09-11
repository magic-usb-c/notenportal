<x-app-layout>
    <x-slot name="title">Semester</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Semester">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.semesters.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> Neues Semester
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
                            <th class="px-4 py-3 text-left font-medium">Bezeichnung</th>
                            <th class="px-4 py-3 text-left font-medium">Von</th>
                            <th class="px-4 py-3 text-left font-medium">Bis</th>
                            <th class="px-4 py-3 text-left font-medium">Sortierung</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @php $today = now()->toDateString(); @endphp
                        @forelse($semester as $s)
                            @php
                                $isAktiv = $s->start_datum <= $today && $s->end_datum >= $today;
                            @endphp
                            <tr class="hover:bg-bg/50 {{ $isAktiv ? 'bg-accent/5' : '' }}">
                                <td class="px-4 py-3 font-semibold text-text">
                                    {{ $s->bezeichnung }}
                                    @if($isAktiv)
                                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs bg-note-gut/15 text-note-gut">aktuell</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-muted tabular-nums">{{ \Carbon\Carbon::parse($s->start_datum)->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-muted tabular-nums">{{ \Carbon\Carbon::parse($s->end_datum)->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-muted">{{ $s->sortierung }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.master-data.semesters.edit', $s->semester_id) }}"
                                       class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.master-data.semesters.destroy', $s->semester_id) }}" class="inline"
                                          onsubmit="return confirm('Semester {{ $s->bezeichnung }} löschen?')"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                        @csrf @method('DELETE')
                                        <button :disabled="loading" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-note-ungenuegend hover:bg-note-ungenuegend/10 disabled:opacity-60">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-muted">
                                    Noch keine Semester erfasst.
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
