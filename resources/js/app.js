import './bootstrap';

import Alpine from 'alpinejs';
import { registriereCharts } from './charts';
import { registriereRechner } from './rechner';

window.Alpine = Alpine;

registriereCharts(Alpine);
registriereRechner(Alpine);

Alpine.start();
