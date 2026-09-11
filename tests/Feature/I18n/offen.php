<?php

/**
 * Bereiche, deren englische Übersetzung noch in Arbeit ist (Pakete C/D/E, docs/i18n-plan.md).
 * Für Dateien unter diesen Pfaden dürfen EN-Übersetzungen vorerst fehlen; die Bereichsdatei
 * lang/areas/<bereich>/en.json wird noch nicht auf verwaiste oder abweichende Schlüssel geprüft.
 * Der Hauptagent leert die Liste beim Zusammenführen – danach gilt überall 0.
 *
 * @return array<string, list<string>> Bereich => Pfadpräfixe relativ zum Projekt
 */
return [
    'learner' => [],
    'trainer' => [],
    'admin' => [],
];
