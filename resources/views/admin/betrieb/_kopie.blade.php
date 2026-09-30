{{-- Kopie ausser Haus: Ziel (Ordner oder SSH), Status und Sofortaktionen. --}}
@php
    $wert = fn (string $k) => old($k, $werte[$k]);
    $K = \App\Services\Betrieb\SicherungKopie::class;
    $letzte = $kopie->letzte();
    $fehler = $kopie->fehler();
@endphp
<section x-data="{ ziel: @js($wert($K::ZIEL)) }">
    <div class="mb-2 flex items-end justify-between gap-4 px-1">
        <div class="min-w-0">
            <h2 class="text-sm font-semibold text-text">{{ __('Kopie ausser Haus') }}</h2>
            <p @class(['mt-0.5 text-xs', 'text-muted' => ! $fehler, 'text-note-ungenuegend' => $fehler])>
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
        @if($kopie->aktiv())
            {{-- Erst nach dem Absenden sperren: ein schon gesperrter Knopf schickt name/value nicht mit – aus «testen» würde «kopieren». --}}
            <form method="POST" action="{{ route('admin.operations.offsite.run') }}#sicherung" class="flex shrink-0 gap-2"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) setTimeout(() => loading = true)">
                @csrf
                <button type="submit" name="aktion" value="testen" :disabled="loading" class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Verbindung testen') }}</button>
                <button type="submit" name="aktion" value="kopieren" :disabled="loading" class="np-knopf np-knopf-sekundaer np-knopf-klein">
                    <span x-show="loading" x-cloak class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-accent border-t-transparent" aria-hidden="true"></span>
                    {{ __('Jetzt kopieren') }}
                </button>
            </form>
        @endif
    </div>

    <form method="POST" action="{{ route('admin.operations.offsite.update') }}#sicherung" class="flex flex-col gap-4"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Ziel')" :fuer="$K::ZIEL" :name="$K::ZIEL">
                <select id="{{ $K::ZIEL }}" name="{{ $K::ZIEL }}" x-model="ziel" class="np-feld w-72">
                    @foreach($K::ZIELE as $schluessel => $text)
                        <option value="{{ $schluessel }}" @selected($wert($K::ZIEL) === $schluessel)>{{ __($text) }}</option>
                    @endforeach
                </select>
            </x-einstellung>
            <x-einstellung :label="__('Server')" :fuer="$K::HOST" :name="$K::HOST" x-show="ziel === 'ssh'" x-cloak>
                <input id="{{ $K::HOST }}" name="{{ $K::HOST }}" type="text" maxlength="190" value="{{ $wert($K::HOST) }}" placeholder="backup.example.ch"
                       autocomplete="off" spellcheck="false" class="np-feld w-72" @error($K::HOST) aria-invalid="true" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Port')" :fuer="$K::PORT" :name="$K::PORT" x-show="ziel === 'ssh'" x-cloak>
                <input id="{{ $K::PORT }}" name="{{ $K::PORT }}" type="number" min="1" max="65535" value="{{ $wert($K::PORT) }}"
                       class="np-feld w-24 text-right tabular-nums" @error($K::PORT) aria-invalid="true" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Benutzer')" :fuer="$K::BENUTZER" :name="$K::BENUTZER" x-show="ziel === 'ssh'" x-cloak>
                <input id="{{ $K::BENUTZER }}" name="{{ $K::BENUTZER }}" type="text" maxlength="64" value="{{ $wert($K::BENUTZER) }}"
                       autocomplete="off" spellcheck="false" class="np-feld w-72" @error($K::BENUTZER) aria-invalid="true" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Ordner am Ziel')" :fuer="$K::PFAD" :name="$K::PFAD" x-show="ziel !== ''" x-cloak>
                <input id="{{ $K::PFAD }}" name="{{ $K::PFAD }}" type="text" maxlength="250" value="{{ $wert($K::PFAD) }}" placeholder="/mnt/sicherungen/notenportal"
                       autocomplete="off" spellcheck="false" class="np-feld w-72 font-mono" @error($K::PFAD) aria-invalid="true" @enderror>
            </x-einstellung>
            @if($schluessel_oeffentlich)
                <div class="px-4 py-3" x-show="ziel === 'ssh'" x-cloak x-data="{ kopiert: false }">
                    <div class="flex min-h-7 items-center justify-between gap-6">
                        <div class="min-w-0">
                            <span class="text-sm text-text">{{ __('Öffentlicher Schlüssel') }}</span>
                            <p class="mt-0.5 text-xs text-muted">{{ __('In authorized_keys am Ziel eintragen') }}</p>
                        </div>
                        <button type="button" class="np-knopf np-knopf-sekundaer np-knopf-klein shrink-0"
                                @click="if (await np.kopieren(@js($schluessel_oeffentlich))) { kopiert = true; setTimeout(() => kopiert = false, 2000) }"
                                x-text="kopiert ? @js(__('Kopiert')) : @js(__('Kopieren'))">{{ __('Kopieren') }}</button>
                    </div>
                    <code class="mt-2 block break-all rounded-lg bg-fill px-3 py-2 font-mono text-xs text-text select-all">{{ $schluessel_oeffentlich }}</code>
                </div>
            @endif
        </div>
        {{-- Solange nichts eingerichtet ist und «Aus» gewählt bleibt, gibt es nichts zu speichern --}}
        <div class="flex justify-end" x-show="ziel !== '' || @js($kopie->aktiv())">
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Speichern') }}</button>
        </div>
    </form>
</section>
