import './bootstrap';

import Alpine from 'alpinejs';
import { registriereCharts } from './charts';
import { registriereRechner } from './rechner';
import { registriereSuche } from './suche';

window.Alpine = Alpine;

registriereCharts(Alpine);
registriereRechner(Alpine);
registriereSuche(Alpine);

Alpine.start();
