import './bootstrap';

import Alpine from 'alpinejs';
import { registriereCharts } from './charts';
import { registriereFeedback } from './feedback';
import { registriereRadiogroup } from './np';
import { registriereRechner } from './rechner';
import { registriereSuche } from './suche';

window.Alpine = Alpine;

registriereCharts(Alpine);
registriereFeedback(Alpine);
registriereRadiogroup(Alpine);
registriereRechner(Alpine);
registriereSuche(Alpine);

Alpine.start();
