import './bootstrap';

import Alpine from 'alpinejs';
import { registriereCharts } from './charts';
import { registriereFeedback } from './feedback';
import { registriereRadiogroup, registriereScrollbereiche, t } from './np';
import { registriereRechner } from './rechner';
import { registriereSuche } from './suche';

window.Alpine = Alpine;
// Übersetzungen auch für Inline-Skripte und Alpine-Ausdrücke in Blade: np.t(schluessel)
window.np = { ...(window.np ?? {}), t };

registriereCharts(Alpine);
registriereFeedback(Alpine);
registriereRadiogroup(Alpine);
registriereRechner(Alpine);
registriereSuche(Alpine);
registriereScrollbereiche();

Alpine.start();
