{{-- Sitzungs-Timeout-Warnung: anfangs versteckt, resources/js/sitzung.js zeigt sie 120s vor Ablauf
     und meldet 5s davor sicher ab. Ohne gebautes JS bleibt sie dank [hidden] unsichtbar. --}}
<div id="np-sitzung-dialog" role="alertdialog" aria-modal="true" aria-labelledby="np-sitzung-titel" hidden
     data-sekunden="{{ \App\Support\Sitzung::minuten() * 60 }}"
     data-keepalive="{{ route('session.keep-alive') }}"
     data-login="{{ route('login') }}"
     data-logout="{{ route('logout') }}"
     class="fixed inset-0 z-[70] flex items-center justify-center glass-scrim p-4 print:hidden">
    <div class="w-full max-w-sm rounded-2xl border border-border bg-card p-6 shadow-e3">
        <h2 id="np-sitzung-titel" class="text-base font-semibold text-text">{{ __('Du wirst gleich wegen Inaktivität abgemeldet.') }}</h2>
        <div class="mt-5 flex justify-end gap-2">
            <button type="button" id="np-sitzung-abmelden" class="np-knopf np-knopf-sekundaer">{{ __('Abmelden') }}</button>
            <button type="button" id="np-sitzung-bleiben" class="np-knopf np-knopf-primaer">{{ __('Angemeldet bleiben') }}</button>
        </div>
    </div>
</div>
