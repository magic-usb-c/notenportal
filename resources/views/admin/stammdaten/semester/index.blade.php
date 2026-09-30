<x-app-layout>
    <x-slot name="title">{{ __('Semester') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Semester')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.semesters.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <span class="text-lg leading-none">+</span> {{ __('Neues Semester') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="np-karte @container overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm tabular-nums">
                    <thead class="sticky top-0 bg-surface-2">
                        <tr>
                            <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Bezeichnung') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Neutraler Name') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Von') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Bis') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell h-9 px-4 text-right text-2xs font-medium text-muted">{{ __('Sortierung') }}</th>
                            <th scope="col" class="h-9 px-4"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @php $today = now()->toDateString(); @endphp
                        @forelse($semester as $s)
                            @php
                                $isAktiv = $s->start_datum <= $today && $s->end_datum >= $today;
                            @endphp
                            <tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60 {{ $isAktiv ? 'bg-accent/5' : '' }}">
                                <td class="px-4 font-semibold text-text">
                                    {{ $s->bezeichnung }}
                                    @if($isAktiv)
                                        <span class="ml-2 rounded-md bg-accent/12 px-2 py-0.5 text-xs font-medium text-accent-text">{{ __('aktuell') }}</span>
                                    @endif
                                    <span class="block text-xs font-normal text-muted @3xl:hidden">{{ \Carbon\Carbon::parse($s->start_datum)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($s->end_datum)->format('d.m.Y') }}</span>
                                </td>
                                <td class="hidden @3xl:table-cell px-4 text-muted">{{ \App\Models\Semester::neutralerName($s->start_datum) }}</td>
                                <td class="hidden @3xl:table-cell px-4 text-muted">{{ \Carbon\Carbon::parse($s->start_datum)->format('d.m.Y') }}</td>
                                <td class="hidden @3xl:table-cell px-4 text-muted">{{ \Carbon\Carbon::parse($s->end_datum)->format('d.m.Y') }}</td>
                                <td class="hidden @3xl:table-cell px-4 text-right text-muted">{{ $s->sortierung }}</td>
                                <td class="px-4 py-1 text-right">
                                    <div class="flex flex-col items-end justify-end gap-1 sm:flex-row sm:items-center opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                        <a href="{{ route('admin.master-data.semesters.edit', $s->semester_id) }}"
                                           class="np-knopf np-knopf-schlicht">{{ __('Bearbeiten') }}</a>
                                        <form method="POST" action="{{ route('admin.master-data.semesters.destroy', $s->semester_id) }}" class="inline"
                                              onsubmit="return confirm(@js(__('Semester :bezeichnung löschen?', ['bezeichnung' => $s->bezeichnung])))"
                                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                            @csrf @method('DELETE')
                                            <button :disabled="loading" class="np-knopf np-knopf-gefahr">{{ __('Löschen') }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-muted">
                                    {{ __('Noch keine Semester erfasst.') }}
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
