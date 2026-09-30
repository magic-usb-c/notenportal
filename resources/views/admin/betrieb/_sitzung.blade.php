{{-- Abmeldung nach Inaktivität (Minuten) und schwebender Feedback-Knopf – beides gilt sofort. --}}
@php
    $aktuell = (int) old('sitzung_minuten', \App\Support\Sitzung::minuten());
    $stufen = collect([15, 30, 60, 120, 240, 480, $aktuell, $sitzungStandard])
        ->filter(fn (int $m) => $m >= \App\Support\Sitzung::MINUTEN_MIN && $m <= \App\Support\Sitzung::MINUTEN_MAX)
        ->unique()->sort()->values();
    $dauer = fn (int $m) => match (true) {
        $m === 60 => __('1 Stunde'),
        $m > 60 && $m % 60 === 0 => __(':anzahl Stunden', ['anzahl' => $m / 60]),
        default => __(':anzahl Minuten', ['anzahl' => $m]),
    };
@endphp
<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Sitzung und Rückmeldungen') }}</h2>
    <div class="np-karte np-gruppe">
        <form method="POST" action="{{ route('admin.operations.session.update') }}#bedienung">
            @csrf
            @method('PUT')
            <x-einstellung :label="__('Abmelden nach Inaktivität von')" fuer="sitzung_minuten" name="sitzung_minuten">
                <select id="sitzung_minuten" name="sitzung_minuten" onchange="this.form.requestSubmit()" class="np-feld w-56"
                        @error('sitzung_minuten') aria-invalid="true" @enderror>
                    @foreach($stufen as $m)
                        <option value="{{ $m }}" @selected($m === $aktuell)>{{ $m === $sitzungStandard ? __(':dauer (Standard)', ['dauer' => $dauer($m)]) : $dauer($m) }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="np-knopf np-knopf-sekundaer">{{ __('Speichern') }}</button></noscript>
            </x-einstellung>
        </form>
        <form method="POST" action="{{ route('admin.operations.feedback-button.update') }}#bedienung">
            @csrf
            @method('PUT')
            <x-einstellung :label="__('Feedback-Knopf')" fuer="feedback_knopf" name="feedback_knopf"
                           :hinweis="__('Schwebender Knopf, mit dem Angemeldete während der Testphase Rückmeldungen und Fehler melden.')">
                <input id="feedback_knopf" name="feedback_knopf" type="checkbox" role="switch" value="1" @checked(old('feedback_knopf', $feedbackKnopfAktiv))
                       onchange="this.form.requestSubmit()" class="np-schalter">
                <noscript><button type="submit" class="np-knopf np-knopf-sekundaer">{{ __('Speichern') }}</button></noscript>
            </x-einstellung>
        </form>
    </div>
</section>
