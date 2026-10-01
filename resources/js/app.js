import './bootstrap';

import Alpine from 'alpinejs';
import { registriereBestaetigung } from './bestaetigung';
import { registriereCharts } from './charts';
import { registriereFeedback } from './feedback';
import { kopieren, registriereFormhilfen, registriereRadiogroup, registriereScrollbereiche, registriereSeitenleiste, registriereSofortSenden, registriereToast, registriereZeilenLinks, t } from './np';
import { registrierePwa } from './pwa';
import { registriereRechner } from './rechner';
import { registriereSitzung } from './sitzung';
import { registriereSuche } from './suche';
import { registriereTastenkuerzel } from './tastenkuerzel';

window.Alpine = Alpine;
// Übersetzungen und Zwischenablage auch für Inline-Skripte und Alpine-Ausdrücke in Blade:
// np.t(schluessel), np.kopieren(text)
window.np = { ...(window.np ?? {}), t, kopieren };

registriereBestaetigung();
registriereCharts(Alpine);
registriereFeedback(Alpine);
registriereRadiogroup(Alpine);
registriereFormhilfen(Alpine);
registriereToast(Alpine);
registriereSeitenleiste(Alpine);
registriereRechner(Alpine);
registriereSuche(Alpine);
registriereTastenkuerzel(Alpine);
registriereScrollbereiche();
registriereZeilenLinks();
registriereSofortSenden();
registrierePwa();
registriereSitzung();

Alpine.start();
