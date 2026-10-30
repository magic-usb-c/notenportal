import './bootstrap';

import Alpine from 'alpinejs';
import { registriereBestaetigung } from './bestaetigung';
import { registriereCharts } from './charts';
import { registriereFeedback } from './feedback';
import { kopieren, registriereAuswahlliste, registriereDezimalfelder, registriereFenster, registriereFormhilfen, registriereLeiste, registriereLicht, registriereRadiogroup, registriereScrollbereiche, registriereSeitenleiste, registriereSofortSenden, registriereZeilenLinks, bewegungRuhig, escapeGilt, mitRichtung, morphStarten, morphUrsprung, t, zahl } from './np';
import { registrierePraeferenzen } from './praeferenzen';
import { registrierePwa } from './pwa';
import { registriereRechner } from './rechner';
import { registriereSitzung } from './sitzung';
import { registriereStatistik } from './statistik';
import { registriereSuche } from './suche';
import { registriereTastenkuerzel } from './tastenkuerzel';

window.Alpine = Alpine;
// Übersetzungen und Zwischenablage auch für Inline-Skripte und Alpine-Ausdrücke in Blade:
// np.t(schluessel), np.kopieren(text), np.morphUrsprung(ausloeser, panel) (Menü wächst aus dem Auslöser),
// np.mitRichtung('vor'|'zurueck', aktualisieren) (Seitenwechsel mit Richtung, z. B. Monat der Agenda)
window.np = { ...(window.np ?? {}), t, kopieren, morphUrsprung, morphStarten, mitRichtung, bewegungRuhig, escapeGilt, zahl };
// Menü-Morph aus Blade (x-init der Menüs): window.npMorph?.(ausloeser, panel)
window.npMorph = morphStarten;

registriereBestaetigung();
registriereCharts(Alpine);
registriereStatistik(Alpine);
registriereFeedback(Alpine);
registriereAuswahlliste(Alpine);
registriereRadiogroup(Alpine);
registriereFormhilfen(Alpine);
registrierePraeferenzen(Alpine);
registriereSeitenleiste(Alpine);
registriereRechner(Alpine);
registriereSuche(Alpine);
registriereTastenkuerzel(Alpine);
registriereLicht(Alpine);
registriereFenster();
registriereDezimalfelder();
registriereLeiste();
registriereScrollbereiche();
registriereZeilenLinks();
registriereSofortSenden();
registrierePwa();
registriereSitzung();

Alpine.start();
