@php
    $heute = \Carbon\CarbonImmutable::today();
    $tage = (int) $heute->diffInDays($e['datum'], false);
    $wann = match (true) {
        $tage === 0 => __('heute'),
        $tage === 1 => __('morgen'),
        $tage === -1 => __('gestern'),
        $tage > 1 => __('in :anzahl Tagen', ['anzahl' => $tage]),
        default => __('vor :anzahl Tagen', ['anzahl' => abs($tage)]),
    };
    $symbol = ['pruefung' => '●', 'erkannt' => '◆', 'termin' => '◇', 'lektion' => '○'][$e['art']];
    $symbolFarbe = in_array($e['art'], ['pruefung', 'erkannt'], true) ? 'text-accent-text' : 'text-muted';
    $p = $e['pruefung'];
@endphp
<div class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
    <div class="w-14 shrink-0 text-center">
        <div class="text-lg font-bold text-text tabular-nums leading-none">{{ $e['datum']->format('d') }}</div>
        <div class="text-[11px] uppercase tracking-wider text-muted">{{ \App\Support\Format::datum($e['datum'], 'M') }}</div>
    </div>
    <div class="flex-1 min-w-40">
        <div class="font-medium text-text truncate flex items-center gap-1.5">
            <span aria-hidden="true" class="{{ $symbolFarbe }}">{{ $symbol }}</span>
            <span class="truncate">{{ $e['titel'] }}</span>
            @if($p && $p->quelle === \App\Models\Pruefung::ICAL && filled($p->lokal_gesperrt))
                <span class="shrink-0 text-[10px] px-1.5 py-0.5 rounded-full border border-border text-muted" title="{{ __('Lokal angepasst') }}">{{ __('lokal angepasst') }}</span>
            @endif
        </div>
        <div class="text-xs text-muted truncate">
            @if($e['zeit']) {{ __(':zeit Uhr · ', ['zeit' => $e['zeit']]) }}@endif
            <span class="{{ $faellig ? 'text-note-knapp font-medium' : ($tage >= 0 && $tage <= 7 ? 'text-accent-text font-medium' : '') }}">{{ $wann }}</span>
            · {{ $e['nebentext'] }}
        </div>
    </div>
    <div class="ms-auto flex items-center gap-1.5 shrink-0">
        @if($e['art'] === 'pruefung')
            @if($p->note)
                <a href="{{ route('learner.grades.index') }}" class="inline-flex items-center gap-1.5">
                    <x-note :wert="$p->note->note_wert" :stufe="$p->note->note_stufe" variante="badge" />
                </a>
            @else
                <a href="{{ route('learner.grades.create', ['pruefung' => $p->pruefung_id]) }}"
                   class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm {{ $faellig ? 'bg-accent text-accent-contrast np-btn-primary' : 'text-accent-text hover:bg-accent/10' }}">{{ __('Note eintragen') }}</a>
            @endif
            <a href="{{ route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]) }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-text hover:bg-bg" aria-label="{{ __('Bearbeiten') }}">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            </a>
            <form method="POST" action="{{ route('learner.exams.destroy', $p->pruefung_id) }}" onsubmit="return confirm('{{ __('Prüfung entfernen?') }}');" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('DELETE')
                <button :disabled="loading" class="inline-flex items-center justify-center w-9 h-9 rounded-lg disabled:opacity-60 text-muted hover:text-note-ungenuegend hover:bg-note-ungenuegend/10" aria-label="{{ __('Entfernen') }}">×</button>
            </form>
        @elseif($e['art'] === 'erkannt')
            <form method="POST" action="{{ route('learner.exams.adopt', $e['event']->id) }}" class="flex items-center gap-1.5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <label for="bezug-{{ $e['event']->id }}" class="sr-only">{{ __('Fach oder Modul') }}</label>
                <select id="bezug-{{ $e['event']->id }}" name="bezug" required
                        class="rounded-lg border border-border bg-input text-text text-xs px-2 py-1.5 min-h-9 max-w-[10rem]">
                    <option value="">{{ __('Zuordnen') }}</option>
                    @foreach($bezugOptionen as $gruppe => $optionen)
                        <optgroup label="{{ $gruppe }}">
                            @foreach($optionen as $o)
                                <option value="{{ $o['wert'] }}">{{ $o['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <button :disabled="loading" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent-text hover:bg-accent/10 disabled:opacity-60">{{ __('Übernehmen') }}</button>
            </form>
        @endif
    </div>
</div>
