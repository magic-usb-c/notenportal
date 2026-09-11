import './bootstrap';

import Alpine from 'alpinejs';
import { registriereCharts } from './charts';
import { registriereFeedback } from './feedback';
import { registriereRechner } from './rechner';
import { registriereSuche } from './suche';

window.Alpine = Alpine;

registriereCharts(Alpine);
registriereFeedback(Alpine);
registriereRechner(Alpine);
registriereSuche(Alpine);

Alpine.start();
