<?php

/**
 * Bereiche, deren englische Übersetzung noch in Arbeit ist (Pakete C/D/E, docs/i18n-plan.md).
 * Für Dateien unter diesen Pfaden dürfen EN-Übersetzungen vorerst fehlen; die Bereichsdatei
 * lang/areas/<bereich>/en.json wird noch nicht auf verwaiste oder abweichende Schlüssel geprüft.
 * learner/trainer/admin sind fertig (Rückmeldung #15 Phase 1) und darum nicht mehr hier drin –
 * Schluessel::geschlossen() behandelt einen Bereich schon dann als offen (Rabatt), wenn der
 * Schlüssel hier vorkommt, unabhängig vom Wert; ein blosses Leeren der Pfadliste genügt also nicht.
 *
 * @return array<string, list<string>> Bereich => Pfadpräfixe relativ zum Projekt
 */
return [];
