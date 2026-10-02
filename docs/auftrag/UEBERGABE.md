# Übergabebrett

Gemeinsames Brett aller parallel laufenden Sitzungen. Beim Start lesen, nach jedem abgeschlossenen
Block ergänzen. Neueste Einträge unten.

Format: `[Kürzel] TT.MM. HH:MM  Was ist passiert / was wird gebraucht.`
Kürzel: **K** Kern und Notenlogik · **V** Verwaltung und Stammdaten · **O** Oberfläche ·
**Q** Qualität, Tests, Dokumentation.

Wer eine Änderung ausserhalb seiner Verzeichnisse braucht (`docs/auftrag/ARBEITSTEILUNG.md`),
editiert nicht selbst, sondern schreibt sie hier hinein.

Entscheide, die David treffen muss, kommen unter «Offen für David» — nicht selbst entscheiden.

---

## Verlauf

```
[—] 30.09. 14:39  Ausgangslage festgehalten: docs/auftrag/LAGE.md, NOTENBAUM.md, ARBEITSTEILUNG.md.
                  Dump notenportal-20260930-1439-vor-notenbaum.sql + Tag vor-notenbaum gesetzt.
[K] 30.09. 16:50  Block 1 Notenbaum fertig (3458e6c a6946be b12a955 90e6cf2 a3f664c, Prüfer-Fixes
                  7b229e5 a9a6274). Rechenkern je Knoten Gewicht/Rundung/Fallnote/max. ungenügend/
                  Minuspunkte/zählt/entfällt mit Track; Stufen (Sport) und «zählt nicht» je Fach;
                  Vorlagen EFZ BiVo 2020 und BM TALS1 als JSON mit Import/Export/Artisan; Seite
                  «Abschluss» für Lernende und Verwaltung; Stammdaten → Notenbäume.
                  Prüfer: 9 Befunde, alle behoben mit Test, der ohne Fix fällt (Positionen überleben
                  Reimport/Wechsel, Stufen in CSV und Notenblatt, Ampel kennt den Baum, BM-Promotion
                  per Migration 2026_10_01_000003 auch auf bestehenden DBs, Rollback bricht vor DDL ab).
                  Prod braucht: notenportal:migrate (3 Migrationen 2026_10_01_*).
[V] 30.09. 16:50  Block 2 Stammdaten nutzbar (a3f664c 2a58562). Einrichtung Schritt 4 liest Vorlagen
                  aus resources/vorlagen/stammdaten (Standard Graubünden: Italienisch statt
                  Französisch, Sport als Stufe, IDAF zählt nicht; zweite Vorlage «Schweiz ICT»), lädt
                  passende Notenbäume mit und verlinkt Fächer- und Baumpflege. Lücken 7/8 aus LAGE
                  geschlossen; 3–6 bewusst gelassen (audit-backlog).
[K] 30.09. 18:55  Nachträge Notenbaum/Stammdaten nach Prüfer: Wechsel verschiebt Positionen statt
                  sie zu löschen (0b655d1), holt sie aus allen Bäumen, zuletzt erfasste gewinnt
                  (8a0429a); Wechsel ohne Deadlock, liest Positionen sperrend, Speichern während
                  eines Wechsels bricht mit Meldung ab statt im alten Baum zu landen (5aad6ed, mit
                  zwei Prozessen gegen MariaDB nachgemessen). Einrichtung Schritt 4 ganz oder gar
                  nicht (31a8638); Vorlage meldet unbekannte Baumverweise (6f7a272). Demo hat den
                  QV-Baum (20805e1). Notenimport: 60 Zeilen 17 statt 163 Abfragen (5f0d8ab), #16 erledigt.
[Q] 30.09. 18:55  Block 4 install.sh (8e8af07 6341f34 7955478 a6c6047). Zieht aus dem Home-Verzeichnis
                  nach /var/www um, prüft den Dienststart statt «fertig» zu melden, npm vertraut der
                  System-CA, hält vor fremder DB/belegtem vhost-Namen/fremdem Port an, verliert das
                  Startpasswort bei Abbruch nicht, setzt DB_TIMEZONE samt Zeitzonentabellen.
                  Gemessen in frischen Containern (Ubuntu 24.04 mit systemd, nicht 26.04), zweite
                  Instanz daneben auf Port 8082. Ablauf: docs/betrieb.md «Neuinstallation».
[O] 30.09. 20:30  Block 3 Oberfläche nach Apple-HIG (efcfc5d 8551493 0e3f91a 9914e1f 6341f34 76c8573
                  64fbfaf 6f8e1ee 8933e00 bda1608 872e615 5effc77 4d7b0a2 b0c6197 0d19dfc 3fdf3ff
                  7c7ac01 c97ae3e 95e84ea faa42aa 708cac2 6ec839e 39b9e56 8a96715 1c358d1 0615045
                  06fd5aa e326d82). Seitenleiste nach HIG je Person umschaltbar, Topbar bleibt und legt
                  Überzähliges in «Mehr» (Priority+). Tabellen schalten Spalten nach Kartenbreite
                  (Container-Queries) statt Fenster; schmal Karten oder Werte in der Namenszelle.
                  Gemessen: alle GET-Seiten je Rolle bei 320–1280 px und mit Seitenleiste ohne
                  Querscrollen, einzige Ausnahme die Zeugnisnoten-Heatmap auf dem Telefon (audit-backlog).
                  Prüfer: 2 Befunde + Nebenbefunde, behoben mit Tests, die gegen den alten Stand fallen.
                  Achtung: Commit-Text von bda1608 ist verdreht – die Tabelle war 1625 px breit in
                  einem 1374 px breiten Container, nicht umgekehrt.
[Q] 30.09. 20:30  Block 5 Aufräumen (835b08c 0204bd7 924496a 2a0ddac ea7e431 36dc9ea). Rückmeldungen
                  12.09. alle erledigt (#15 Phase 2 bewusst nach Go-Live). i18n-Scan von 26 auf 0,
                  Test hält die Views dort. Toter Code (Note-Scopes, Leistung::toArray) entfernt.
                  Rollentrennung: jede Berufsbildner-Route mit Lernenden-/Prüfungsbezug und jedes
                  fremde Objekt der Lernenden per Sweep mit Gegenprobe und Vollständigkeitsprüfung.
                  Dabei gefunden und behoben: Abgabetermin ohne Gewichtung ergab 500 (ea7e431).
                  «Backlog A» ist nirgends definiert; ausgelegt als die offenen Punkte in
                  audit-backlog.md – erledigte mit Beleg markiert, Rest begründet gelassen.
                  Prüfer: Scan-Regel zu grob (3ac27d2), Berufsbildner konnten eigene Kommentare nach
                  Ende der Betreuung noch löschen (c82bad2) – beide mit Test, der vorher fällt.
[Q] 01.10. 08:32  Setup für Fable-Sitzungen in der Cloud (13f0edb + Folgecommit). Hook
                  .claude/hooks/session-start.sh richtet Container ein (MariaDB, Test- und Demo-DB,
                  Demo-Server 8099, npm/composer); Prüfwerkzeuge liegen jetzt im Repo unter
                  tools/pruefung/ (browser, shot, klick, rundgang, demo-server.sh), Passwort nur per
                  NP_TEST_PW. .claude/settings.json: model fable, effort xhigh, advisor fable,
                  ultracode, workflowSizeGuideline unrestricted, Allow-/Deny-Listen. Neue Skills
                  notenportal-orchestrierung (Modell/Effort je Aufgabe) und notenportal-dunkelmodus
                  (HIG-Regeln aus der Primärquelle, DocC-JSON). Agents mit model/effort, neu
                  bildpruefer (opus xhigh). Regel .claude/rules/oberflaeche.md. Workflows
                  notenportal-audit.js und notenportal-dunkel-rundgang.js. Befehl /weiter. Massstab
                  neu: Desktop 1920–2560 px, Dunkelmodus; hell und schmal nur «darf nicht brechen».
                  Alles in docs/auftrag/SETUP-CLAUDE.md (Abbild, /-Befehle, Modelltabelle, Lücken).
[Q] 01.10. 09:40  Setup abgeschlossen (ed52264 2dd5acf + Folgecommits): Rauchtest beider Workflows.
                  notenportal-audit lief durch (Aufnahme, zwei Linsen, Verwerfungen mit Grund);
                  notenportal-dunkel-rundgang lief bis zur Verifikation, dann Nutzungslimit – 22 von
                  29 Agenten abgebrochen (SETUP-CLAUDE §12.11). Beide Skripte zählen ausgefallene
                  Prüfer jetzt als «ungeprüft» und prüfen in Bündeln. Bestätigte Befunde behoben:
                  Overlays im Dunkeln eine Stufe über der Karte (e52ab7a, HIG base/elevated,
                  gemessen), Direktlabels im Verlauf in Textfarbe mit Punkt in Linienfarbe.
                  Offen aus dem Rundgang: 10 Sichtbefunde ohne Urteil – kommen im vollen R4-Lauf dran.
[Q] 01.10. 15:45  Cloud-Hook in frischem Container nachgemessen und repariert (99940e5): MariaDB startete
                  nicht (/run/mysqld gehörte root, mysqld_safe läuft als mysql), composer install scheiterte
                  (api.github.com 403 hinter dem Proxy → composer-spiegel.sh schreibt Dist-URLs auf einen
                  Packagist-Spiegel um), APP_KEY fehlte, Marktplatz veraltet (php-lsp unbekannt), ssh-keygen
                  fehlte (SicherungKopieTest), ohne .env zählte Collision 1066 unterdrückte Warnungen.
                  Suite jetzt 199 bestanden, 0 Warnungen. Details SETUP-CLAUDE.md §4.3, §12.12–13.
                  R4 Lernende begonnen: Basis-Rundgang 1920 Exit 0 für alle drei Rollen, Lernende bei
                  1920 und 2560 gesichtet (Scratchpad), Baustellen-Inventar liegt vor.
[O] 01.10. 17:45  R4 Lernende abgeschlossen, Runde 1 (19572a9 Rechnerlogik, 9787b6e Oberfläche) und
                  Runde 2 (5e8a863 Fix, 842b2c2 GUI, Suite 1270 grün, siehe unten). Runde 1: zehn
                  Umsetzungsgruppen parallel (Rechner, Leerzustände, Noten, Module, Import, Dashboard,
                  Abschluss, Agenda, Profil, Demo-Lernende «Livia Gerber» ohne Noten), Bildprüfer 1920/2560,
                  Reviewer ohne Befund, Suite 1269 grün. Rechner: gesuchte Note nur in einfliessende
                  Prüfungen (Test fällt ohne Fix), Differenzen und «?» neutral, Lernende ohne Noten sehen
                  «Noch keine Noten …» statt «bei 0.0». Dashboard: Leerzustand «Willkommen» statt zweier
                  leerer Karten, Fach-Labels werden gemessen statt gekappt. Tabellen als np-tabelle mit
                  colgroup, Leerzustände ohne Karte, Lade-Zustände im Import, Stufen-Auswahl als Segment.
                  Begriff «Lehrende» (mehrdeutig) portalweit zu «Ende der Lehre»/«Lehre endet bald».
                  Bewusst gelassen: docs/audit-backlog.md «R4 Lernende». Git-Historie beginnt bei 88905a4
                  (01.10. 01:56); ältere Hashes in diesem Brett (3458e6c, 8e8af07, 171ed72 …) existieren
                  nicht mehr – die Rotation des Prod-Testpassworts bleibt trotzdem bei David.
                  Runde 2 (ui-checker 14 Befunde, Prüfer, Rundgang-Workflow 1920/2560): Tastatur
                  (Ebenen-Segment als Radiogroup, Semesterpfeile ohne Ziel nicht fokussierbar), aria-busy
                  und aria-labels, Leerzustände als x-leer (Module, Abgleich, Feedback mit Aktion),
                  colgroup überall, Kennzahlen text-xl, Einstellungs-Tabs ortsfest unter dem Titel,
                  Agenda-Liste auf 78 rem begrenzt, Modulliste mit Scroll-Kante unten, Verlauf-Tabelle
                  ohne abgeschnittene Köpfe, Rechner-Fehler mit Grund (429/419) und «Erneut berechnen»,
                  Feedback-Symbolleistenknopf auf «Meine Meldungen» ausgeblendet. Backend: throttle ohne
                  Präfix teilte den Zähler je Benutzer über alle Routen (nach 11 Rechner-Aufrufen war
                  Feedback gesperrt) → eigener Präfix je Route, Test ThrottleSchluesselTest. Agenda-Drawer:
                  Schleifenvariable überschrieb die Feldklasse (Felder ohne Gestaltung) → behoben, Test.
                  Prüfer-Befunde 1–3 behoben (Altbezeichnung in zwei Fehlermeldungen, DashboardKartenTest
                  ohne Beweiskraft, Hero-Satz «bei –»); Befund 4 (Leerzustand-Bedingung) bewusst so.
                  Prüfwerkzeuge: Chromium mit LANG=de_CH (Datumsfelder 01.08.2026 statt 08/01/2026),
                  clip.mjs (Bildausschnitt), rundgang.mjs lässt die lesenden Rechner-POSTs durch (vorher
                  zeigte jeder Rundgang «Berechnung fehlgeschlagen» – Werkzeug, kein Portalfehler).
                  Runde 3 nach Bildprüfer (10 Befunde) und Reviewer (3 niedrige): Scroll-Kanten als Maske
                  nur bei Überlauf (np.js setzt data-np-kante; vorher helle Bänder in der Seitenleiste),
                  Bullet-Graph mit sichtbaren Bändern, Kategoriewerte «Lehrzeit» beschriftet, Sparkline
                  bei der Zahl, Semester-Kapsel mittig, Monatskarte bündig, leerer Rechner mit «Note
                  erfassen», Platzhalter «–» dünn, Throttle-Test prüft doppelte Präfixe, Feedback-Tipp
                  nicht auf «Meine Meldungen». Nächster Schritt: R4 Berufsbildner (Agents laufen).
[O] 02.10. 02:40  R4 Berufsbildner abgeschlossen (bd769cc, 9c88675, 97afc07, 6457635, 8037bac und der
                  Abschluss-Commit, siehe Git). Dunkelmodus nach HIG für Dashboard, Lernendenliste, Cockpit
                  mit Reitern (Übersicht, Noten, Dokumente, Rechner, Profil & Betreuung), Notenliste mit
                  Inspektor, Abschluss, Prüfungstermine, Fehlerseiten. Abschlussrunde: Rundgang 1920 Exit 0
                  (21 Seiten), Bildprüfer 1920 Kern/Module/Einstellungen und 2560 (13 Seiten, kein Befund
                  «hoch»), Reviewer (ein Befund: Hook), UI-Checker (13 Befunde), Suite 1301 bestanden /
                  1 übersprungen, Prüfer: Behauptung hält, ein Befund – aria-label des Termin-Notenlinks rundete
                  Viertelnoten (4.25 → «4.3»), jetzt ungerundet wie der Badge, mit Regressionstest. Umgesetzt:
                  Zeilenaktion «Noten» rechtsbündig, «Modul anlegen» als Primäraktion, Kommentarfeld mit
                  @error/aria (Fehler landet an der richtigen Note, old('note_id') öffnet sie wieder),
                  Datumskachel im Dashboard wie in der Terminliste (text-muted, keine Versalien), Kacheln
                  text-xl, Gewichtung in «Nächste 14 Tage» beschriftet, tabular-nums, Notenflächen
                  einheitlich /14, Marken auf bg-fill, Sparkline-Band fill-fill, Formularfuss im Profil
                  ohne bg-fill-2, Fehlerseiten-h1 bold, Leerzeilen mit Weg zurück («Filter zurücksetzen»,
                  «Alle anzeigen», «Alle Termine»), «Kopiert» per aria-live, Termin-Note mit aria-label.
                  Roter Test AlleGesehenTest: Knopftext bei einer neuen Note heisst seit 8037bac «Neue Note
                  als gesehen markieren» – Test angepasst, prüft jetzt beide Texte. Bewusst gelassen (18
                  Punkte mit Grund): docs/audit-backlog.md «R4 Berufsbildner». Hook: Composer-Schritt mit
                  echter Archiv-Probe (codeload 403, api 200 mit abbrechenden Downloads) und Kreuz-Rückfall;
                  Spiegel-Vertrauen unter «Offen für David». Annahmen dieser Sitzung: Arbeit auf Branch
                  claude/friendly-meitner-ju7e8a mit Entwurfs-PR gegen main (Vorgabe der Cloud-Sitzung,
                  nicht main direkt); «Leerung» der Bretter heisst: «bewusst gelassen» sind Entscheide,
                  «Offen für David» bleibt, alles andere wird umgesetzt oder mit Grund als erledigt/obsolet
                  markiert. Nächster Schritt: R4 Admin.
[O] 02.10. 01:15  R4 Admin abgeschlossen (Abschluss-Commit, siehe Git). Dunkelmodus nach HIG für Übersicht,
                  Lernendenliste, Noten-Inspektor, Rechner, Abschluss, Berufsbildner, Benutzerkonten (anlegen,
                  bearbeiten, Passwort), Stammdaten (Lehrberufe, Module mit Katalog, Fächer, Kategorien,
                  Notenbäume, Semester), Betrieb (Einstellungen mit Darstellung/E-Mail/Sitzung, Einrichtung
                  Schritte 1–8, Benachrichtigungen, Versand- und Aktivitätsprotokoll, Berichte, Feedback).
                  Fünf Umsetzungs-Agents parallel (A Übersicht/Listen, B Betrieb/Benutzer, C Einrichtung,
                  D Stammdaten, E Verwaltung), Diffs in der Hauptsession gelesen. Umgesetzt u. a.: neutrale
                  Kacheln und ein Akzent je Ansicht, Leerzustände mit Weg weiter («Lernende erfassen»,
                  «Berufsbildner erfassen», «E-Mail einrichten», «Modul/Fach anlegen»), aria-invalid/
                  aria-describedby an jedem Feld (Hinweis- und Fehler-id), Darstellung speichert per
                  data-sofort beim Wechsel, Passwort-Augen im Feld, Einrichtung mittig (max-w-5xl) mit
                  Zeilenfeldern je Person (_zeilenfeld), Katalogseite ohne CLI-Befehl (steht jetzt in
                  docs/betrieb.md), Zahlen aus der DB ohne Nachkommanullen, gesperrtes «Entfernen» mit
                  Grund statt verstecktem Knopf, Rechner zeigt Promotionswechsel «vorher → nachher»,
                  Statusspalte der Lernendenliste wächst (alle Zeilen 44 px). Abschlussrunde: Suite 1303
                  bestanden / 1 übersprungen, Rundgang Admin 56 Seiten und Berufsbildner 21 Seiten Exit 0,
                  24 Screenshots 1920/2560 angesehen, SchluesselTest grün (9 EN-Schlüssel ergänzt, 4 Waisen
                  entfernt), Hook ohne Meldung, ß-Grep leer. Bewusst gelassen (28 Punkte mit Grund):
                  docs/audit-backlog.md «R4 Admin». Abschluss-Commit 68c0e24. Nachtrag nach Review:
                  Reviewer ohne Befund (XSS-Stellen nur Literale, Sperren serverseitig, Fehlerindizes und
                  Sprachschlüssel stimmen, 122 gefilterte Tests). UI-Checker 13 Befunde, 10 umgesetzt:
                  «0» im Dashboard text-muted statt text-faint, Kacheln text-xl, bg-bg in Karten durch
                  bg-fill ersetzt (Einrichtung Module/Semester), Zeilen-Entfernen und «Weitere …» mit
                  x-symbol statt Glyphe, Hinweistexte in Einrichtung Module und Katalog gestrichen,
                  Leerzustand Einrichtung Module über x-leer, Abschluss-Status mit check-circle wie der
                  Stepper, h3→h2 unter dem Seitentitel, Leerzustandstitel ohne Punkt, Berichte-Leerzeile
                  mit «Filter zurücksetzen» und th scope, Platzhalter «–» einheitlich text-muted,
                  Zahlenfelder rechtsbündig, Rechner «Ziel entfernen» mit Loading-State. Drei Punkte mit
                  Grund im Backlog (colgroup, Lehrberuf-Felder nur aria-label, Abschluss-Fuss).
                  Prüfer (opus, 22d989a..68c0e24): Behauptung hält, zwei Lücken behoben – Auswahlfelder in
                  `_zeilenfeld` ohne aria-invalid/aria-describedby, und «Fach löschen» zeigte den Hinweis nur
                  bei ungelöschten Noten, während destroy() auch bei Prüfungen, Zielen und gelöschten Noten
                  sperrt (jetzt eine Regel `loeschSperre()` für Hinweis und Sperre, Test
                  `fach_mit_pruefung_zeigt_loeschsperre…`, Backlog-Punkt gestrichen). Bestätigt: Theme-Radios
                  senden einmal, in_gebrauch-Sperre serverseitig, Browser-Sonde auf 16 Admin-Seiten ohne
                  doppelte ids oder tote aria-Verweise. Nicht geprüft: Fehlerzustände von people, professions,
                  mail, theme, notifications im Browser (nur am Code).
                  Nächster Schritt: R5 Gesamtprüfung.
[O] 02.10. 02:30  R5 Gesamtprüfung begonnen (nach Nachtrag f24bc48). Umfang, da nirgends definiert, nach
                  gui-konzept «Prüfung» und notenportal-ui §10 festgelegt: (1) Workflow
                  notenportal-dunkel-rundgang über alle drei Rollen, 1920 vollständig beurteilt, 2560
                  gemessen und als Stichprobe beurteilt, jeder Befund zweifach verifiziert; (2) Workflow
                  notenportal-audit über alle sechs Dimensionen; (3) Hell-Stichprobe je Rolle von Hand
                  (darf nicht brechen); (4) bestätigte Befunde beheben, Suite, Prüfer; (5) LAGE §7,
                  gui-konzept-Stand, Übergabe, PR-Beschreibung. Die zehn ungeprüften Sichtbefunde des
                  Rauchtests vom 01.10. gelten damit als neu aufgenommen.
```

---

## Offen für David

- **Standard der Navigation**: Seitenleiste nach HIG ist gebaut und je Person umschaltbar
  (Einstellungen → Darstellung), die Topbar bleibt. Standard für neue Konten ist laut Code die
  Seitenleiste (`app/Support/Darstellung.php`, `NAVIGATION_SEITE`); nur wenn du die Topbar als
  Standard willst, ist das zu ändern.
- **Cloud-Sitzung, Berechtigungen**: `auto` wirkt laut Doku nicht aus Projekt-Einstellungen; in der
  Cloud deshalb im Berechtigungs-Menü der Sitzung «Auto» wählen, lokal in `~/.claude/settings.json`
  setzen (`docs/auftrag/SETUP-CLAUDE.md` Abschnitt 5 und 6). Die Projektdatei trägt `acceptEdits`.
- **Plugins**: Der Hook installiert in der Cloud `php-lsp` und `frontend-design` aus dem offiziellen
  Marktplatz (geprüft, CLI 2.1.286). Die Einträge `laravel`, `caveman`, `claude-skills`,
  `ui-ux-pro-max-skill` in `enabledPlugins` haben im Container keinen Marktplatz und wirken nur
  lokal; sollen sie in der Cloud laufen, braucht jeder einen `extraKnownMarketplaces`-Eintrag mit
  Quelle (`SETUP-CLAUDE.md` Abschnitt 8) – die Quellen kennst nur du. Lokal sind `php-lsp` und
  `laravel-lsp` beide eingeschaltet, also zwei PHP-Sprachserver: einen abschalten.
- **Gewichte nach BM-Dispens von der Allgemeinbildung**: Quellen widersprechen sich (hochrechnen
  57,14/42,86 vs. 50/50). Nicht voreingestellt; im Baum lässt sich «Entfällt mit BMS» je Knoten setzen.
- **Rundung der Allgemeinbildung und der IPA im QV**: nur aus Sekundärquellen, Primärquelle
  (Bildungsverordnung/Bildungsplan) war hinter dem Proxy nicht erreichbar.
- **Mehrere BM-Ausrichtungen**: ein BM-Baum hängt am Track BMS; zwei Ausrichtungen gleichzeitig
  (TALS und WD) gehen erst mit Bildungsgängen als eigene Tabelle (LAGE Lücke 3).
- **Kantonale BMV**: ob sie an die BMV 2025 angepasst ist, liess sich nicht klären
  (`LAGE.md` 4.8). Betrifft die BM-Vorlage.
- **INBE-Modulverteilung**: Lektionentafel und Modulverteilungs-PDF widersprechen sich.
- **Abgabetermin ohne Gewichtung**: zählt heute 100 % («leer = 100» aus `notenlogik.md`). In teilweise
  benoteten Modulen wäre das Restgewicht passender; Beispiel und Vorschlag in `audit-backlog.md`.
- **Lizenz und Meldeweg für Sicherheitslücken** sind weiterhin offen.
- **Prod-Testpasswort aus Commit `171ed72`** liegt in der Git-Historie und muss rotiert werden.
- **Packagist-Spiegel ohne Prüfsummen** (`SETUP-CLAUDE.md` §12.12): hinter dem Cloud-Proxy sind
  GitHub-Archive nicht erreichbar, der Hook lädt dann alle 122 Pakete von `mirrors.cloud.tencent.com`,
  und `composer.lock` trägt für GitHub-Dists keinen `shasum` – Composer prüft diese Archive gegen
  nichts. Entscheid: Netzrichtlinie der Umgebung um `codeload.github.com` erweitern (dann läuft der
  direkte Weg mit Packagist-Hashes), eigener Spiegel, oder das Restrisiko für Wegwerf-Container
  bewusst tragen.
- **Agent-Konfiguration vs. Doku**: die Frontmatter der Agents wurden am 01./02.10. umgestellt
  (pruefer `claude-fable-5-1` `effortLevel: low`, texter `claude-haiku`, bildpruefer sonnet high,
  ui-checker opus high …). `CLAUDE.md` Zeile 59 und die Tabelle im Skill `notenportal-orchestrierung`
  nennen noch opus xhigh für pruefer/bildpruefer/ui-checker; zwei Agents schreiben `effortLevel`,
  sechs `effort`, und keine Dokumentation belegt, welcher Schlüssel gelesen wird
  (`SETUP-CLAUDE.md` §12.14). Entscheid: Doku an die Konfiguration anpassen oder umgekehrt, und
  einen Schlüssel für alle acht wählen.
