{{--
    Bestätigung vor folgenreichen Aktionen (HIG «Alerts»), einmal im Layout. Ein Formular mit
    data-bestaetigen="Frage?" öffnet ihn statt window.confirm (resources/js/bestaetigung.js):
    data-bestaetigen-text ergänzt die Folge in einem Satz, data-bestaetigen-knopf benennt die Aktion mit einem Verb,
    data-bestaetigen-art="normal" für Aktionen ohne Datenverlust (sonst rot, Abbrechen hat den Fokus).
--}}
<dialog id="np-bestaetigung" class="np-alert" role="alertdialog" aria-modal="true"
        aria-labelledby="np-bestaetigung-titel" aria-describedby="np-bestaetigung-text"
        data-standard-knopf="{{ __('Bestätigen') }}">
    <div class="px-5 pt-5 pb-4 text-center">
        <h2 id="np-bestaetigung-titel" class="text-base font-semibold text-text" data-titel></h2>
        <p id="np-bestaetigung-text" class="mt-1 text-sm text-muted" data-text hidden></p>
    </div>
    <div class="grid grid-cols-2 gap-2 px-4 pb-4">
        <button type="button" class="np-knopf np-knopf-sekundaer w-full" data-abbrechen>{{ __('Abbrechen') }}</button>
        <button type="button" class="np-knopf w-full" data-ok></button>
    </div>
</dialog>
