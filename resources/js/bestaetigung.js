// Bestätigung vor folgenreichen Aktionen (HIG «Alerts») statt window.confirm: Formulare mit data-bestaetigen
// halten beim Absenden an und öffnen <x-bestaetigung>. Erst «OK» sendet das Formular mit demselben Knopf erneut ab.
// Der Listener sitzt in der Capture-Phase am Dokument und stoppt das Ereignis, bevor Alpine («loading») oder
// der Fortschrittsbalken es sehen.
export function registriereBestaetigung() {
    document.addEventListener(
        'submit',
        (ereignis) => {
            const form = ereignis.target;
            if (!(form instanceof HTMLFormElement) || !form.dataset.bestaetigen) return;
            if (form.dataset.bestaetigt === '1') {
                delete form.dataset.bestaetigt;
                return;
            }

            ereignis.preventDefault();
            ereignis.stopImmediatePropagation();

            const dialog = document.getElementById('np-bestaetigung');
            // Ohne Dialog (fremdes Layout) bleibt die Rückfrage wenigstens nativ erhalten
            if (!(dialog instanceof HTMLDialogElement)) {
                if (window.confirm(form.dataset.bestaetigen)) absenden(form, ereignis.submitter);
                return;
            }
            if (dialog.open) return;

            oeffnen(dialog, form, ereignis.submitter);
        },
        true,
    );
}

function oeffnen(dialog, form, submitter) {
    const titel = dialog.querySelector('[data-titel]');
    const text = dialog.querySelector('[data-text]');
    const ok = dialog.querySelector('[data-ok]');
    const abbrechen = dialog.querySelector('[data-abbrechen]');
    const gefahr = form.dataset.bestaetigenArt !== 'normal';

    titel.textContent = form.dataset.bestaetigen;
    text.textContent = form.dataset.bestaetigenText ?? '';
    text.hidden = !form.dataset.bestaetigenText;
    ok.textContent = form.dataset.bestaetigenKnopf || submitter?.textContent.trim() || dialog.dataset.standardKnopf;
    ok.classList.toggle('np-knopf-gefahr-voll', gefahr);
    ok.classList.toggle('np-knopf-primaer', !gefahr);

    const ausloeser = document.activeElement;
    const schliessen = (bestaetigt) => {
        ok.removeEventListener('click', jaKlick);
        abbrechen.removeEventListener('click', neinKlick);
        dialog.removeEventListener('close', beimSchliessen);
        if (dialog.open) dialog.close();
        if (bestaetigt) {
            absenden(form, submitter);
        } else if (ausloeser instanceof HTMLElement && ausloeser.isConnected) {
            ausloeser.focus();
        }
    };
    const jaKlick = () => schliessen(true);
    const neinKlick = () => schliessen(false);
    // Escape schliesst den Dialog nativ – gilt als Abbrechen
    const beimSchliessen = () => schliessen(false);

    ok.addEventListener('click', jaKlick);
    abbrechen.addEventListener('click', neinKlick);
    dialog.addEventListener('close', beimSchliessen);

    dialog.showModal();
    // Zerstörende Aktion: Abbrechen ist der Standard, Return löscht nie versehentlich
    (gefahr ? abbrechen : ok).focus();
}

function absenden(form, submitter) {
    if (!form.isConnected) return;
    form.dataset.bestaetigt = '1';
    form.requestSubmit(submitter && submitter.isConnected && submitter.form === form ? submitter : undefined);
}
