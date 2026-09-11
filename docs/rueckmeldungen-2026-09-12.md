# Rückmeldungen Produkttest 12.09.2026 – priorisiert

Quelle: Test des Product Owners mit mehreren Rollen. Status: offen / in Arbeit / erledigt (Commit).
Der Login-419 ist nicht mehr relevant: Im privaten Tab funktionierte der Login, die Ursache war alter Browserzustand.

## P1 – Fehler (zuerst)
| # | Punkt | Status |
|---|---|---|
| 1 | Lernende, /grades → «Neue Note» im Seitenpanel: Felder lassen sich nicht bearbeiten, Speichern geht nicht (/grades/create als Seite geht) | erledigt: Ursache war `x-html`, das das Formular bei jeder Eingabe neu setzte |
| 2 | Feedback-Screenshot ist komplett einfarbig | erledigt: Alpine-Attribute (`@click`, `:class`) sind keine gültigen XML-Namen, das SVG liess sich nicht dekodieren; sie werden in der Kopie entfernt |
| 3 | Links in Mails führen zu 403: Weiterleitung nach dem Login auf fremde Rollen-URL; die 403-Seite bietet keinen Kontowechsel | offen |
| 4 | Knopf für das Kalender-Abo fehlt oder funktioniert nicht in allen Rollen | offen |
| 5 | Englisch: gefühlt die Hälfte noch Deutsch; lange englische Wörter brechen unschön um | offen |

## P2 – Automatische Prüfungen (fangen P1-artige Fehler künftig ab)
| # | Punkt | Status |
|---|---|---|
| 6 | Gezielte, günstige Prüfungen (Playwright-Skripte + Haiku-Auswertung per Skill): Felder in Drawern bedienbar, Formularknöpfe senden ihren Wert, deutsche Reste im englischen Modus, Umbrüche, leere/einfarbige Screenshots, Mail-Links je Rolle, Funktionen je Rolle | offen |

## P3 – Bedienung
| # | Punkt | Status |
|---|---|---|
| 7 | Feedback-Kategorie «Lob» → «Sonstiges» | offen |
| 8 | Benutzermenü entschlacken (Einstellungen, Feedback, Abmelden); eigene Einstellungsseite für Tastenkürzel, Benachrichtigungen, Datenexport, Sprache, Themes, Kalender und Integrationen | offen |
| 9 | Semesteranzeige relativ je Lernender («1. Semester» statt «24/25-1»), überall im GUI | offen |
| 10 | Notenrechner übersichtlicher und verständlicher; öffnet standardmässig den relevantesten Tab (Fach/Modul), nicht «Gesamt» | offen |
| 11 | Restdauer des aktuellen Semesters mit passender Einheit (Monate → Wochen → Tage) | offen |

## P4 – Funktionen
| # | Punkt | Status |
|---|---|---|
| 12 | Feedback: technische Details standardmässig mitsenden (abwählbar), schwebender Feedback-Knopf während der Testphase (abschaltbar), Hover-Beschriftung, kleines schwebendes Panel, eigene Screenshots/Anhänge | offen (nach Feedback II) |
| 13 | Layout nutzt das Browserfenster zu wenig: breitere Container, mehrspaltig auf grossen Bildschirmen, Skalierung prüfen | offen |
| 14 | Modulstatus: Dauer seit Beginn, Abgabetermine je Modul/Fach (verknüpft mit Prüfungen), Fortschritt bis zum nächsten und letzten Termin, bewerteter Anteil nach Gewichtung | offen (Migration) |
| 15 | Kalender bearbeitbar synchronisieren (Outlook, Nextcloud CalDAV u. a.), nicht nur iCal lesend; Termine auch aus externem Kalender ins Portal | offen (Design läuft) |
| 16 | Datenimporte intelligenter: exakt so, wie die Datenbank es erwartet (Zuordnung, Vorschau, Validierung) | offen |
