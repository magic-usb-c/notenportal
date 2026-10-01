{{-- Tabellenzeile eines Moduls, flach und gruppiert; die ganze Zeile öffnet das Modul. Verweis in den
     Modulbaukasten nur mit bekannter Version (App\Support\Modulbaukasten). --}}
@php
    $mbk = \App\Support\Modulbaukasten::modulLink($m->modul_nummer, $m->version ?? null);
    $ziel = route('admin.master-data.modules.edit', $m->modul_id);
@endphp
<tr data-href="{{ $ziel }}">
    <td class="whitespace-nowrap">
        <a href="{{ $ziel }}" class="font-semibold {{ $m->aktiv ? 'text-text' : 'text-muted' }} hover:text-accent-text">{{ $m->modul_nummer }}</a>@if($m->version ?? null)<span class="ml-1.5 text-2xs text-muted">V{{ $m->version }}</span>@endif
    </td>
    <td>
        <div class="flex min-w-0 items-center gap-2">
            <span class="truncate {{ $m->aktiv ? 'text-text' : 'text-muted' }}">{{ $m->titel }}</span>
            @unless($m->aktiv)<span class="np-marke shrink-0 text-muted">{{ __('Inaktiv') }}</span>@endunless
            @if($mbk !== null)
                <a href="{{ $mbk }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 shrink-0 items-center gap-0.5 text-xs text-accent-text hover:underline underline-offset-2">{{ __('Modulbaukasten') }}<x-symbol name="arrow-up-right" class="size-3" /><span class="sr-only"> ({{ __('neues Fenster') }})</span></a>
            @endif
        </div>
    </td>
    <td class="truncate text-muted">{{ $m->lernorte ?: '–' }}</td>
    <td class="truncate {{ $m->lehrberufe ? 'text-text' : 'text-muted' }}" @if($m->lehrberufe) title="{{ $m->lehrberufe }}" @endif>{{ $m->lehrberufe ?: '–' }}</td>
    <td class="text-right">
        <x-zeilen-link :href="$ziel" :zeile="$m->modul_nummer.' '.$m->titel" />
    </td>
</tr>
