<x-app-layout>
    <x-slot name="title">{{ __('Semester') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Semester')">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.semesters.create') }}"
                   class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Neues Semester') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="np-karte @container overflow-hidden">
                <div class="overflow-x-auto p-2">
                <table class="np-tabelle text-sm">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('Bezeichnung') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Neutraler Name') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Von') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell">{{ __('Bis') }}</th>
                            <th scope="col" class="hidden @3xl:table-cell text-right">{{ __('Sortierung') }}</th>
                            <th scope="col"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $today = now()->toDateString(); @endphp
                        @forelse($semester as $s)
                            @php
                                $isAktiv = $s->start_datum <= $today && $s->end_datum >= $today;
                            @endphp
                            <tr class="group {{ $isAktiv ? 'bg-accent/5' : '' }}">
                                <td class="font-semibold text-text">
                                    {{ $s->bezeichnung }}
                                    @if($isAktiv)
                                        <span class="ml-2 np-marke bg-accent/12 text-accent-text">{{ __('aktuell') }}</span>
                                    @endif
                                    <span class="block text-xs font-normal text-muted @3xl:hidden">{{ \Carbon\Carbon::parse($s->start_datum)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($s->end_datum)->format('d.m.Y') }}</span>
                                </td>
                                <td class="hidden @3xl:table-cell text-muted">{{ \App\Models\Semester::neutralerName($s->start_datum) }}</td>
                                <td class="hidden @3xl:table-cell text-muted">{{ \Carbon\Carbon::parse($s->start_datum)->format('d.m.Y') }}</td>
                                <td class="hidden @3xl:table-cell text-muted">{{ \Carbon\Carbon::parse($s->end_datum)->format('d.m.Y') }}</td>
                                <td class="hidden @3xl:table-cell text-right text-muted">{{ $s->sortierung }}</td>
                                <td class="text-right">
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
