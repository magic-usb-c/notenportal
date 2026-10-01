{{-- Feedback als Postfach (Mail): links die Meldungen, rechts die gewählte mit Inhalt und Kontext, daneben die
     Bearbeitung als Inspektor. Auswahl per Klick oder Pfeiltasten (npFeedbackPostfach in resources/js/feedback.js). --}}
@use('App\Models\Feedback')
@use('App\Support\Format')
@php
    $aktiveFilter = collect([$suche, $status, $kategorie, $rolle])->filter(fn ($w) => $w !== '')->count() + ($duplikate ? 1 : 0);
    $auswahl = 'np-feld np-feld-klein w-auto max-w-64';
    $wann = fn ($d) => match (true) {
        $d->isToday() => $d->format('H:i'),
        $d->isYesterday() => __('Gestern'),
        $d->gt(now()->subDays(6)->startOfDay()) => Format::date($d, 'dddd'),
        default => $d->format('d.m.Y'),
    };
    $name = fn ($m) => trim($m->vorname.' '.$m->nachname) ?: $m->email;
    $stimmen = fn ($n) => (int) $n === 1 ? __('1 Stimme') : __(':n Stimmen', ['n' => (int) $n]);
    $statusLabels = collect(Feedback::STATUS)->map(fn ($l) => __($l))->all();
    $postfach = [
        'statusLabels' => $statusLabels,
        'reihenfolge' => $meldungen->map(fn ($m) => (int) $m->feedback_id)->values()->all(),
        'meldungen' => $meldungen->mapWithKeys(fn ($m) => [(int) $m->feedback_id => [
            'status' => $m->status,
            'notiz' => $m->admin_notiz ?? '',
            'duplikatVon' => $hatDuplikatSpalte ? $m->duplikat_von : null,
            'duplikatEingabe' => $hatDuplikatSpalte ? ($m->duplikat_von ?? '') : '',
        ]])->all(),
        'urls' => [
            'speichern' => route('admin.feedback.update', '__ID__'),
            'duplikat' => $hatDuplikatSpalte ? route('admin.feedback.duplicate', '__ID__') : null,
        ],
    ];
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Feedback') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Feedback')" :zaehler="$gibtEs ? $meldungen->total() : null">
            @if($gibtEs)
                <x-slot:aktionen>
                    <a href="{{ route('admin.feedback.export') }}" class="np-knopf np-knopf-sekundaer">
                        <x-symbol name="arrow-down-tray" />{{ __('CSV exportieren') }}
                    </a>
                </x-slot:aktionen>
            @endif
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8 flex flex-col gap-4">
            @if(! $gibtEs)
                <div class="np-karte">
                    <x-leer symbol="chat-bubble-left-ellipsis" :titel="__('Noch keine Meldungen')" />
                </div>
            @else
                <x-filterleiste :action="route('admin.feedback.index')" suche-name="suche" :suche-wert="$suche"
                                 :suche-platzhalter="__('Text, Name oder E-Mail')"
                                 :zurueck="route('admin.feedback.index')" :aktive-filter="$aktiveFilter">
                    <label for="status" class="sr-only">{{ __('Status') }}</label>
                    <select name="status" id="status" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="" @selected($status === '')>{{ __('Status: alle') }}</option>
                        @foreach(Feedback::STATUS as $wert => $label)
                            <option value="{{ $wert }}" @selected($status === $wert)>{{ __($label) }}</option>
                        @endforeach
                    </select>

                    <label for="kategorie" class="sr-only">{{ __('Kategorie') }}</label>
                    <select name="kategorie" id="kategorie" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="" @selected($kategorie === '')>{{ __('Kategorie: alle') }}</option>
                        @foreach(Feedback::KATEGORIEN as $wert => $label)
                            <option value="{{ $wert }}" @selected($kategorie === $wert)>{{ __($label) }}</option>
                        @endforeach
                    </select>

                    <label for="rolle" class="sr-only">{{ __('Rolle') }}</label>
                    <select name="rolle" id="rolle" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="" @selected($rolle === '')>{{ __('Rolle: alle') }}</option>
                        @foreach(['Admin', 'Berufsbildner', 'Lernender'] as $r)
                            <option value="{{ $r }}" @selected($rolle === $r)>{{ __($r) }}</option>
                        @endforeach
                    </select>

                    <x-slot:weitere>
                        <label for="sort" class="sr-only">{{ __('Sortierung') }}</label>
                        <select name="sort" id="sort" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                            <option value="neueste" @selected($sort === 'neueste')>{{ __('Neueste zuerst') }}</option>
                            <option value="aelteste" @selected($sort === 'aelteste')>{{ __('Älteste zuerst') }}</option>
                            <option value="stimmen" @selected($sort === 'stimmen')>{{ __('Meiste Stimmen') }}</option>
                        </select>
                        @if($hatDuplikatSpalte)
                            <label class="flex h-8 items-center gap-2 px-1 text-sm text-text">
                                <input type="checkbox" name="duplikate" value="1" @checked($duplikate) x-on:change="$el.form.requestSubmit()" class="np-haken">
                                {{ __('Duplikate anzeigen') }}
                            </label>
                        @endif
                    </x-slot:weitere>
                </x-filterleiste>

                @if($meldungen->isEmpty())
                    <div class="np-karte">
                        <p class="flex items-center justify-center gap-3 px-6 py-10 text-sm text-muted">
                            {{ __('Keine Meldungen für diese Filter.') }}
                            <a href="{{ route('admin.feedback.index') }}" class="text-accent-text hover:underline underline-offset-2">{{ __('Filter zurücksetzen') }}</a>
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-[minmax(20rem,26rem)_minmax(0,1fr)] items-start gap-4" x-data="npFeedbackPostfach(@js($postfach))">

                        {{-- Liste --}}
                        <div class="flex flex-col gap-3">
                            <div class="np-karte p-1.5">
                                <div role="listbox" x-ref="liste" aria-label="{{ __('Meldungen') }}" class="flex flex-col gap-0.5"
                                     @keydown.arrow-down.prevent="bewegen(1)" @keydown.arrow-up.prevent="bewegen(-1)"
                                     @keydown.home.prevent="bewegen(-Infinity)" @keydown.end.prevent="bewegen(Infinity)">
                                    @foreach($meldungen as $m)
                                        @php
                                            $id = (int) $m->feedback_id;
                                            $offen = $m->status === Feedback::STATUS_OFFEN;
                                        @endphp
                                        <div role="option" data-meldung="{{ $id }}" id="meldung-{{ $id }}"
                                             aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? 0 : -1 }}"
                                             :aria-selected="gewaehlt === {{ $id }} ? 'true' : 'false'" :tabindex="gewaehlt === {{ $id }} ? 0 : -1"
                                             @click="waehlen({{ $id }})" class="np-listenzeile">
                                            <span class="absolute left-2 top-4 size-2 rounded-full bg-accent" @if(! $offen) style="display: none" @endif
                                                  x-show="meldungen[{{ $id }}].statusGespeichert === 'offen'"><span class="sr-only">{{ __('Offen') }}</span></span>
                                            <div class="flex items-baseline gap-3">
                                                <span @class(['min-w-0 flex-1 truncate text-sm text-text', 'font-semibold' => $offen, 'font-medium' => ! $offen])
                                                      :class="{ 'font-semibold': meldungen[{{ $id }}].statusGespeichert === 'offen', 'font-medium': meldungen[{{ $id }}].statusGespeichert !== 'offen' }">{{ $name($m) }}</span>
                                                <span class="shrink-0 text-xs tabular-nums text-muted">{{ $wann($m->erstellt_am) }}</span>
                                            </div>
                                            <div class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted">
                                                <span class="truncate">{{ __(Feedback::kategorieLabel($m->kategorie)) }}</span>
                                                <span x-show="meldungen[{{ $id }}].statusGespeichert !== 'offen'" @if($offen) style="display: none" @endif class="flex shrink-0 items-center gap-1.5">
                                                    <span aria-hidden="true">·</span><span x-text="statusLabels[meldungen[{{ $id }}].statusGespeichert]">{{ $statusLabels[$m->status] ?? $m->status }}</span>
                                                </span>
                                                @if($hatDuplikatSpalte && $m->duplikat_von)
                                                    <span class="flex shrink-0 items-center gap-1.5"><span aria-hidden="true">·</span>{{ __('Duplikat von #:id', ['id' => $m->duplikat_von]) }}</span>
                                                @elseif($hatDuplikatSpalte && ($m->duplikate_anzahl ?? 0) > 0)
                                                    <span class="flex shrink-0 items-center gap-1.5"><span aria-hidden="true">·</span>{{ __('+:n Duplikate', ['n' => $m->duplikate_anzahl]) }}</span>
                                                @endif
                                                @if(($m->stimmen_anzahl ?? 0) > 0)
                                                    <span class="ml-auto shrink-0 tabular-nums">{{ $stimmen($m->stimmen_anzahl) }}</span>
                                                @endif
                                            </div>
                                            <p class="mt-1 line-clamp-2 break-words text-xs text-muted">{{ Str::limit($m->text, 240) }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            @if($meldungen->hasPages())
                                <div class="px-1">{{ $meldungen->links() }}</div>
                            @endif
                        </div>

                        {{-- Gewählte Meldung --}}
                        <div x-ref="detail" class="np-karte sticky top-[calc(var(--np-symbolleiste-hoehe)_+_0.5rem)] max-h-[calc(100dvh_-_var(--np-symbolleiste-hoehe)_-_1.5rem)] overflow-y-auto">
                            @foreach($meldungen as $m)
                                @php
                                    $id = (int) $m->feedback_id;
                                    $rollen = $m->rolle ? __($m->rolle) : ($m->rollen ? implode(', ', array_map('__', explode(', ', $m->rollen))) : null);
                                    $kontext = array_filter([
                                        __('Seite') => $m->url,
                                        __('Route') => $m->route_name,
                                        __('Fenster') => $m->viewport,
                                        __('Browser') => $m->browser ?? $m->user_agent,
                                    ], fn ($w) => filled($w));
                                    $technik = $m->technik_details ?? [];
                                @endphp
                                <article x-show="gewaehlt === {{ $id }}" @unless($loop->first) x-cloak @endunless aria-labelledby="meldung-titel-{{ $id }}"
                                         class="grid grid-cols-[minmax(0,1fr)_20rem]">
                                    <div class="min-w-0">
                                        <header class="flex items-start gap-4 border-b border-border px-6 py-5">
                                            <span class="np-monogramm size-10 shrink-0 text-sm" aria-hidden="true">{{ mb_strtoupper(mb_substr($m->vorname ?? '', 0, 1).mb_substr($m->nachname ?? '', 0, 1)) ?: '?' }}</span>
                                            <div class="min-w-0 flex-1">
                                                <h2 id="meldung-titel-{{ $id }}" class="truncate text-lg font-semibold text-text">{{ $name($m) }}</h2>
                                                <p class="truncate text-sm text-muted">{{ $m->email }}@if($rollen) <span aria-hidden="true">·</span> {{ $rollen }}@endif</p>
                                            </div>
                                            <time datetime="{{ $m->erstellt_am->toIso8601String() }}" class="shrink-0 pt-1 text-sm tabular-nums text-muted">
                                                {{ Format::date($m->erstellt_am, 'tag_monat') }} {{ $m->erstellt_am->format('Y') }}, {{ $m->erstellt_am->format('H:i') }}
                                            </time>
                                        </header>

                                        <div class="flex flex-col gap-6 px-6 py-5">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="np-marke font-medium text-text">{{ __(Feedback::kategorieLabel($m->kategorie)) }}</span>
                                                @if(($m->stimmen_anzahl ?? 0) > 0)
                                                    <span class="np-marke tabular-nums text-muted">{{ $stimmen($m->stimmen_anzahl) }}</span>
                                                @endif
                                                @if($hatDuplikatSpalte && $m->duplikat_von)
                                                    <a href="{{ route('admin.feedback.index', ['duplikate' => 1]) }}#meldung-{{ $m->duplikat_von }}"
                                                       class="np-marke min-h-6 text-accent-text hover:underline">{{ __('Duplikat von #:id', ['id' => $m->duplikat_von]) }}</a>
                                                @endif
                                                @if($hatDuplikatSpalte && ($m->duplikate_anzahl ?? 0) > 0)
                                                    <span class="np-marke text-muted">{{ __('+:n Duplikate', ['n' => $m->duplikate_anzahl]) }}</span>
                                                @endif
                                                <span class="ml-auto text-xs tabular-nums text-muted">#{{ $id }}</span>
                                            </div>

                                            <p class="max-w-3xl whitespace-pre-wrap break-words text-base text-text">{{ $m->text }}</p>

                                            @if($m->hatScreenshot())
                                                <a href="{{ route('admin.feedback.screenshot', $id) }}" target="_blank" rel="noopener" class="block w-fit">
                                                    <img src="{{ route('admin.feedback.screenshot', $id) }}" alt="{{ __('Screenshot der Meldung') }}" loading="lazy"
                                                         class="max-h-80 max-w-full rounded-lg border border-border">
                                                </a>
                                            @endif

                                            @if($hatAnhaengeTabelle && $m->anhaenge->isNotEmpty())
                                                <section aria-labelledby="anhaenge-{{ $id }}">
                                                    <h3 id="anhaenge-{{ $id }}" class="text-sm font-semibold text-text">{{ __('Anhänge') }}</h3>
                                                    <ul class="mt-2 flex flex-wrap gap-2">
                                                        @foreach($m->anhaenge as $anhang)
                                                            <li>
                                                                <a href="{{ route('feedback.attachment', [$id, $anhang->feedback_anhang_id]) }}" target="_blank" rel="noopener"
                                                                   class="np-knopf np-knopf-sekundaer np-knopf-klein max-w-72">
                                                                    <x-symbol name="document-text" /><span class="truncate">{{ $anhang->dateiname }}</span>
                                                                </a>
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                </section>
                                            @endif

                                            @if($kontext !== [] || ! empty($m->js_fehler) || $technik !== [])
                                                <section aria-labelledby="kontext-{{ $id }}" class="border-t border-border pt-5">
                                                    <h3 id="kontext-{{ $id }}" class="text-sm font-semibold text-text">{{ __('Kontext') }}</h3>
                                                    <dl class="mt-3 grid grid-cols-[max-content_minmax(0,1fr)] gap-x-6 gap-y-1.5 text-sm">
                                                        @foreach($kontext as $label => $wert)
                                                            <dt class="text-muted">{{ $label }}</dt>
                                                            <dd class="break-all text-text">{{ $wert }}</dd>
                                                        @endforeach
                                                        @foreach([
                                                            'bildschirm' => __('Bildschirm'),
                                                            'pixelverhaeltnis' => __('Pixelverhältnis'),
                                                            'sprache' => __('Sprache'),
                                                            'zeitzone' => __('Zeitzone'),
                                                            'darstellung' => __('Darstellung'),
                                                            'app_version' => __('App-Version'),
                                                        ] as $schluessel => $label)
                                                            @if(filled($technik[$schluessel] ?? null))
                                                                <dt class="text-muted">{{ $label }}</dt>
                                                                <dd class="break-all text-text">{{ $technik[$schluessel] }}</dd>
                                                            @endif
                                                        @endforeach
                                                        @if(array_key_exists('online', $technik))
                                                            <dt class="text-muted">{{ __('Verbindung') }}</dt>
                                                            <dd class="text-text">{{ $technik['online'] ? __('Online') : __('Offline') }}</dd>
                                                        @endif
                                                    </dl>
                                                    @if(! empty($technik['fehlgeschlagene_requests']))
                                                        <h4 class="mt-4 text-sm font-medium text-text">{{ __('Fehlgeschlagene Anfragen') }}</h4>
                                                        <ul class="mt-1 flex flex-col gap-1 text-sm text-muted">
                                                            @foreach($technik['fehlgeschlagene_requests'] as $r)
                                                                <li class="break-all font-mono text-xs">{{ $r['status'] ?? '' }} {{ $r['pfad'] ?? '' }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                    @if(! empty($m->js_fehler))
                                                        <h4 class="mt-4 text-sm font-medium text-text">{{ __('Letzte JS-Fehler') }}</h4>
                                                        <ul class="mt-1 flex flex-col gap-1">
                                                            @foreach($m->js_fehler as $fehler)
                                                                <li class="break-words font-mono text-xs text-muted">{{ $fehler }}</li>
                                                            @endforeach
                                                        </ul>
                                                    @endif
                                                </section>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Inspektor: Bearbeitung --}}
                                    <aside aria-label="{{ __('Bearbeitung') }}" class="flex flex-col gap-6 border-l border-border px-5 py-5">
                                        <fieldset>
                                            <legend class="text-sm font-medium text-text">{{ __('Status') }}</legend>
                                            <div class="np-segment mt-2 w-full">
                                                @foreach(Feedback::STATUS as $wert => $label)
                                                    <label class="flex-1 cursor-pointer">
                                                        <input type="radio" class="sr-only" name="status-{{ $id }}" value="{{ $wert }}" x-model="meldungen[{{ $id }}].status" @checked($m->status === $wert)>{{ __($label) }}
                                                    </label>
                                                @endforeach
                                            </div>
                                        </fieldset>

                                        <div>
                                            <label for="notiz-{{ $id }}" class="text-sm font-medium text-text">{{ __('Antwort an die meldende Person') }}</label>
                                            <textarea id="notiz-{{ $id }}" x-model="meldungen[{{ $id }}].notiz" rows="6" maxlength="5000"
                                                      class="np-feld np-textfeld mt-1.5">{{ $m->admin_notiz }}</textarea>
                                        </div>

                                        <button type="button" @click="speichern({{ $id }})" :disabled="speichert || ! geaendert({{ $id }})" disabled
                                                class="np-knopf np-knopf-primaer w-full">{{ __('Speichern') }}</button>

                                        @if($hatDuplikatSpalte)
                                            <div class="border-t border-border pt-5">
                                                @if($m->duplikat_von)
                                                    <p class="text-sm text-text">{{ __('Duplikat von #:id', ['id' => $m->duplikat_von]) }}</p>
                                                    <button type="button" @click="duplikatUmschalten({{ $id }})" :disabled="duplikatSpeichert"
                                                            class="np-knopf np-knopf-sekundaer mt-2 w-full">{{ __('Markierung aufheben') }}</button>
                                                @else
                                                    <label for="duplikat-{{ $id }}" class="text-sm font-medium text-text">{{ __('Duplikat von #') }}</label>
                                                    <div class="mt-1.5 flex items-center gap-2">
                                                        <input type="number" inputmode="numeric" min="1" id="duplikat-{{ $id }}" x-model="meldungen[{{ $id }}].duplikatEingabe"
                                                               @keydown.enter.prevent="meldungen[{{ $id }}].duplikatEingabe && duplikatUmschalten({{ $id }})"
                                                               class="np-feld w-24 tabular-nums" aria-describedby="duplikat-fehler-{{ $id }}">
                                                        <button type="button" @click="duplikatUmschalten({{ $id }})" :disabled="duplikatSpeichert || ! meldungen[{{ $id }}].duplikatEingabe" disabled
                                                                class="np-knopf np-knopf-sekundaer flex-1">{{ __('Als Duplikat markieren') }}</button>
                                                    </div>
                                                @endif
                                                <p id="duplikat-fehler-{{ $id }}" x-show="duplikatFehler" x-cloak class="mt-1.5 text-xs text-note-ungenuegend" x-text="duplikatFehler"></p>
                                            </div>
                                        @endif
                                    </aside>
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
