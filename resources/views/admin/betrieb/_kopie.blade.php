@php
    $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
    $label = 'text-sm font-medium text-text';
    $wert = fn (string $k) => old($k, $werte[$k]);
    $K = \App\Services\Betrieb\SicherungKopie::class;
    $letzte = $kopie->letzte();
    $fehler = $kopie->fehler();
@endphp
<div class="px-6 py-5 border-t border-border flex flex-col gap-5" x-data="{ ziel: @js($wert($K::ZIEL)) }">
    <div>
        <h4 class="text-sm font-semibold text-text">{{ __('Kopie ausser Haus') }}</h4>
        <p @class(['text-xs mt-0.5', 'text-muted' => ! $fehler, 'text-note-ungenuegend' => $fehler])>
            @if($fehler)
                {{ __('Letzte Kopie fehlgeschlagen: :fehler', ['fehler' => $fehler]) }}
            @elseif(! $kopie->aktiv())
                {{ __('Aus – Sicherungen liegen nur auf diesem Server') }}
            @elseif($letzte)
                {{ __('Zuletzt kopiert :datum, danach nach jeder Sicherung', ['datum' => $letzte->timezone(config('app.timezone'))->format('d.m.Y H:i')]) }}
            @else
                {{ __('Eingerichtet, noch nicht kopiert') }}
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('admin.operations.offsite.update') }}" class="flex flex-col gap-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <div>
            <label for="{{ $K::ZIEL }}" class="{{ $label }}">{{ __('Ziel') }}</label>
            <select id="{{ $K::ZIEL }}" name="{{ $K::ZIEL }}" x-model="ziel" class="{{ $feld }}">
                @foreach($K::ZIELE as $schluessel => $text)
                    <option value="{{ $schluessel }}" @selected($wert($K::ZIEL) === $schluessel)>{{ __($text) }}</option>
                @endforeach
            </select>
            @error($K::ZIEL)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>

        <div x-show="ziel === 'ssh'" x-cloak class="grid sm:grid-cols-3 gap-4">
            <div>
                <label for="{{ $K::HOST }}" class="{{ $label }}">{{ __('Server') }}</label>
                <input id="{{ $K::HOST }}" name="{{ $K::HOST }}" type="text" maxlength="190" value="{{ $wert($K::HOST) }}" class="{{ $feld }}" autocomplete="off" placeholder="backup.example.ch">
                @error($K::HOST)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="{{ $K::PORT }}" class="{{ $label }}">{{ __('Port') }}</label>
                <input id="{{ $K::PORT }}" name="{{ $K::PORT }}" type="number" min="1" max="65535" value="{{ $wert($K::PORT) }}" class="{{ $feld }} tabular-nums">
                @error($K::PORT)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="{{ $K::BENUTZER }}" class="{{ $label }}">{{ __('Benutzer') }}</label>
                <input id="{{ $K::BENUTZER }}" name="{{ $K::BENUTZER }}" type="text" maxlength="64" value="{{ $wert($K::BENUTZER) }}" class="{{ $feld }}" autocomplete="off">
                @error($K::BENUTZER)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
            </div>
        </div>

        <div x-show="ziel !== ''" x-cloak>
            <label for="{{ $K::PFAD }}" class="{{ $label }}">{{ __('Ordner am Ziel') }}</label>
            <input id="{{ $K::PFAD }}" name="{{ $K::PFAD }}" type="text" maxlength="250" value="{{ $wert($K::PFAD) }}" class="{{ $feld }} font-mono text-sm" autocomplete="off" placeholder="/mnt/sicherungen/notenportal">
            @error($K::PFAD)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>

        @if($schluessel_oeffentlich)
            <div x-show="ziel === 'ssh'" x-cloak x-data="{ kopiert: false }">
                <span class="{{ $label }}">{{ __('Öffentlicher Schlüssel für authorized_keys am Ziel') }}</span>
                <div class="mt-1 flex items-start gap-2">
                    <code class="flex-1 min-w-0 break-all rounded-xl border border-border bg-input px-3 py-2 text-xs text-text">{{ $schluessel_oeffentlich }}</code>
                    <button type="button" class="shrink-0 inline-flex items-center px-3 h-9 rounded-lg glass-btn text-text text-xs"
                            @click="navigator.clipboard.writeText(@js($schluessel_oeffentlich)); kopiert = true; setTimeout(() => kopiert = false, 2000)"
                            x-text="kopiert ? @js(__('Kopiert')) : @js(__('Kopieren'))">{{ __('Kopieren') }}</button>
                </div>
            </div>
        @endif

        <div class="flex justify-end">
            <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary disabled:opacity-60">{{ __('Speichern') }}</button>
        </div>
    </form>

    @if($kopie->aktiv())
        <form method="POST" action="{{ route('admin.operations.offsite.run') }}" class="flex flex-wrap gap-3"
              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <button type="submit" name="aktion" value="testen" :disabled="loading" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm disabled:opacity-60">{{ __('Verbindung testen') }}</button>
            <button type="submit" name="aktion" value="kopieren" :disabled="loading" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm disabled:opacity-60">
                <span x-show="loading" x-cloak class="w-4 h-4 rounded-full border-2 border-accent border-t-transparent animate-spin" aria-hidden="true"></span>
                {{ __('Jetzt kopieren') }}
            </button>
        </form>
    @endif
</div>
