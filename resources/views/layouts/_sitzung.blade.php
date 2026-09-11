{{-- Sitzungs-Timeout-Warnung: anfangs versteckt, resources/js/sitzung.js zeigt sie 120s vor Ablauf
     und meldet 5s davor sicher ab. Ohne gebautes JS bleibt sie dank [hidden] unsichtbar. --}}
<div id="np-sitzung-dialog" role="alertdialog" aria-modal="true" aria-labelledby="np-sitzung-titel" hidden
     data-sekunden="{{ \App\Support\Sitzung::minuten() * 60 }}"
     data-keepalive="{{ route('session.keep-alive') }}"
     data-login="{{ route('login') }}"
     data-logout="{{ route('logout') }}"
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 print:hidden">
    <div class="w-full max-w-sm rounded-xl bg-card p-6 shadow-lg">
        <h2 id="np-sitzung-titel" class="text-base font-semibold text-text">{{ __('Du wirst gleich wegen Inaktivität abgemeldet.') }}</h2>
        <div class="mt-5 flex justify-end gap-3">
            <button type="button" id="np-sitzung-bleiben" class="inline-flex h-9 items-center rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">{{ __('Angemeldet bleiben') }}</button>
            <button type="button" id="np-sitzung-abmelden" class="inline-flex h-9 items-center rounded-lg px-3.5 text-sm text-muted hover:bg-surface-2 hover:text-text">{{ __('Abmelden') }}</button>
        </div>
    </div>
</div>
