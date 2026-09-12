# Rückmeldungen Produkttest 12.09.2026 – priorisiert

Quelle: Test des Product Owners mit mehreren Rollen. Status: offen / in Arbeit / erledigt (Commit).
Der Login-419 ist nicht mehr relevant: Im privaten Tab funktionierte der Login, die Ursache war alter Browserzustand.

## P1 – Fehler (zuerst)
| # | Punkt | Status |
|---|---|---|
| 1 | Lernende, /grades → «Neue Note» im Seitenpanel: Felder lassen sich nicht bearbeiten, Speichern geht nicht (/grades/create als Seite geht) | erledigt: Ursache war `x-html`, das das Formular bei jeder Eingabe neu setzte |
| 2 | Feedback-Screenshot ist komplett einfarbig | erledigt: Alpine-Attribute (`@click`, `:class`) sind keine gültigen XML-Namen, das SVG liess sich nicht dekodieren; sie werden in der Kopie entfernt |
| 3 | Links in Mails führen zu 403: Weiterleitung nach dem Login auf fremde Rollen-URL; die 403-Seite bietet keinen Kontowechsel | erledigt: Ziel-URL nach dem Login nur, wenn die Rolle sie öffnen darf; 403-Seite zeigt das Konto und bietet «Mit anderem Konto anmelden» |
| 4 | Knopf für das Kalender-Abo fehlt oder funktioniert nicht in allen Rollen | erledigt: Knopf bei BB/Admin ohne Alpine-Bereich (reagierte nicht); «Kopieren» über http mit Fallback (`np.kopieren`). Fester Platz in den Einstellungen folgt mit #8 |
| 5 | Englisch: gefühlt die Hälfte noch Deutsch; lange englische Wörter brechen unschön um | Übersetzungen vollständig: **0 von 935** `__()`-Schlüsseln ohne EN-Fassung, geprüft mit der Logik von `tests/Feature/I18n/Schluessel.php` (1348 verwendete Schlüssel, 1408 Übersetzungen). Die frühere Angabe «665 fehlend» war ein **Messfehler** von `sprache.mjs`: es verglich nur gegen `lang/en.json` und übersah `lang/areas/*/en.json`, das `AppServiceProvider` per `Lang::addJsonPath` global registriert – Werkzeug korrigiert, Gast-Stichprobe mit `Accept-Language: en` besteht. Offen bleibt allein der zweite Teil: Umbruch langer englischer Wörter (`umbruch.mjs`) |

## P2 – Automatische Prüfungen (fangen P1-artige Fehler künftig ab)
| # | Punkt | Status |
|---|---|---|
| 6 | Gezielte, günstige Prüfungen (Playwright-Skripte + Haiku-Auswertung per Skill): Felder in Drawern bedienbar, Formularknöpfe senden ihren Wert, deutsche Reste im englischen Modus, Umbrüche, leere/einfarbige Screenshots, Mail-Links je Rolle, Funktionen je Rolle | erledigt (Block A): Werkzeuge in `~/tools/visual`, Skill `notenportal-pruefwerkzeuge` |

## P3 – Bedienung
| # | Punkt | Status |
|---|---|---|
| 7 | Feedback-Kategorie «Lob» → «Sonstiges» | erledigt (Block G) |
| 8 | Benutzermenü entschlacken (Einstellungen, Feedback, Abmelden); eigene Einstellungsseite für Tastenkürzel, Benachrichtigungen, Datenexport, Sprache, Themes, Kalender und Integrationen | erledigt (Block C, 136a57d) |
| 9 | Semesteranzeige relativ je Lernender («1. Semester» statt «24/25-1»), überall im GUI | erledigt (Block B, 6b1c156) |
| 10 | Notenrechner übersichtlicher und verständlicher; öffnet standardmässig den relevantesten Tab (Fach/Modul), nicht «Gesamt» | erledigt (Block E) |
| 11 | Restdauer des aktuellen Semesters mit passender Einheit (Monate → Wochen → Tage) | erledigt (Block B, 6b1c156) |

## P4 – Funktionen
| # | Punkt | Status |
|---|---|---|
| 12 | Feedback: technische Details standardmässig mitsenden (abwählbar), schwebender Feedback-Knopf während der Testphase (abschaltbar), Hover-Beschriftung, kleines schwebendes Panel, eigene Screenshots/Anhänge | erledigt (Block G) |
| 13 | Layout nutzt das Browserfenster zu wenig: breitere Container, mehrspaltig auf grossen Bildschirmen, Skalierung prüfen | offen |
| 14 | Modulstatus: Dauer seit Beginn, Abgabetermine je Modul/Fach (verknüpft mit Prüfungen), Fortschritt bis zum nächsten und letzten Termin, bewerteter Anteil nach Gewichtung | erledigt (Block F) |
| 15 | Kalender bearbeitbar synchronisieren (Outlook, Nextcloud CalDAV u. a.), nicht nur iCal lesend; Termine auch aus externem Kalender ins Portal | offen (Design läuft) |
| 16 | Datenimporte intelligenter: exakt so, wie die Datenbank es erwartet (Zuordnung, Vorschau, Validierung) | in Arbeit (H1–H3: Vorschau prüft die DB-Regeln, alles-oder-nichts) |
