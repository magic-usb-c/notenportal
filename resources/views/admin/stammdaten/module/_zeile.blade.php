{{-- Tabellenzeile eines Moduls, flach und gruppiert. Verweis in den Modulbaukasten nur mit bekannter Version
     (App\Support\Modulbaukasten). --}}
@php($mbk = \App\Support\Modulbaukasten::modulLink($m->modul_nummer, $m->version ?? null))
<tr class="group">
    <td class="whitespace-nowrap font-medium text-text">
        {{ $m->modul_nummer }}@if($m->version ?? null)<span class="ml-1.5 text-2xs font-normal text-muted">V{{ $m->version }}</span>@endif
    </td>
    <td class="text-text">
        {{ $m->titel }}
        @if($mbk !== null)
            <a href="{{ $mbk }}" target="_blank" rel="noopener noreferrer" class="ml-1 text-xs text-accent-text underline-offset-2 hover:underline">{{ __('Modulbaukasten') }}<span class="sr-only"> ({{ __('neues Fenster') }})</span></a>
        @endif
        <span class="block text-xs text-muted sm:hidden">
            {{ (int) $m->lehrberuf_count === 1 ? __('1 Lehrberuf') : __(':anzahl Lehrberufe', ['anzahl' => (int) $m->lehrberuf_count]) }}@unless($m->aktiv) · {{ __('inaktiv') }}@endunless
        </span>
    </td>
    <td class="hidden text-right text-muted sm:table-cell">{{ $m->lehrberuf_count }}</td>
    <td class="hidden sm:table-cell">
        @if($m->aktiv)
            <span class="text-xs text-muted">{{ __('aktiv') }}</span>
        @else
            <span class="np-marke text-muted">{{ __('inaktiv') }}</span>
        @endif
    </td>
    <td class="text-right">
        <x-zeilen-link :href="route('admin.master-data.modules.edit', $m->modul_id)" :zeile="$m->modul_nummer.' '.$m->titel"
                      class="opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100 pointer-coarse:opacity-100" />
    </td>
</tr>
