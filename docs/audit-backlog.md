# Audit-Backlog (Stand 11.06.2026, Abend-Session)

Findings aus dem Multi-Agent-Audit vom 11.06. — Status nach der Umsetzungs-Session.

## Erledigt (verifiziert + umgesetzt, siehe git log 11.06.)

- Doppelte Erfolgsmeldungen (Toast + Inline-Banner): 15 Inline-Banner entfernt, Toast einzige Quelle; Maschinen-Token profile-/password-updated ersetzt
- Glass-Konsistenz portalweit inkl. profile/edit, admin/benutzer/edit, Stammdaten/Lernende/Berufsbildner/Berichte (Commits 9d73fec, a6661c4)
- rgba()-Misch-Syntax → rgb(var()/a) (6867acc); Glass-Light-Mode-Alphas angehoben (Border 0.12, Schatten 0.13, Deckung 0.70); glass-subtle hell auf Card-Weiss
- Notenfarb-Skala Admin-Bericht 4-stufig; Bericht-Empty-State-Variable korrigiert
- bg-white-Hardcodes (Badge, kbd) tokenisiert; simple-tailwind-Pagination auf Tokens
- WCAG AA: Notenfarben hell auf -700, text-red-500 (87×) → red-600 + dark-Variante, --muted hell auf Slate-600 — Konvention in CLAUDE.md nachgeführt
- Viertelnoten: step=0.05 (create/edit/rechner)
- Feldgenaue @error-Anzeige + Pflichtfeld-* in Noten-create/edit
- Drucken-Zurück (window-target-Fallback); destroy() mit semester_id; markGesehen/markAlleGesehen via back() + opened_note
- Notiz-Inline-Speichern: Fehleranzeige bei !res.ok/Netzwerkfehler
- Dreifach-Empty-State (war bereits durch @if($notes->isNotEmpty()) behoben)
- Rechner: Status «Gewichtung fehlt» bei g≤0, min=5/max=100
- Edit-Formular: Live-Semester-Hinweis wie im Create
- Betreuung: neue Betreuung beendet offene automatisch (gueltig_bis = Vortag), Flash-Hinweis
- «Alle gesehen»: Zähler über alle Seiten/ungefiltert im Controller, Button mit Anzahl
- Admin-Lernende: Betreuung/Tracks-Aktionen auch <lg sichtbar, opacity-Hover entfernt
- BB-Kommentarfeld: textarea (2 Zeilen) + Ctrl/Cmd+Enter
- CSV-Formel-Injection: App\Support\Csv::safe() in allen 5 Exporten
- Rollen-ID 2 → dynamisch (Berufsbildner via rollen-Tabelle)
- Admin updateProfil: lehrberuf/lehrbeginn required (NOT-NULL-500 behoben)
- Deaktivierte Benutzer: EnsureUserIsActive-Middleware global (web-Gruppe)
- Semester-Überlappungs-Check in store/update
- Inaktive Kategorien: Formular gefiltert, store-Validierung mit aktiv=1 (update bewusst offen für Legacy-Noten)
- Nav-Dropdowns: Klick/Tastatur/aria-expanded/Escape/click.outside; Mobile-Menü Escape; Benutzer-Dropdown Escape
- Stammdaten-Tabellen: overflow-x-auto-Container (5 Views)
- Touch-Targets: Bearbeiten/Löschen im Accordion als 36px-Flächen; ×-Abbrechen 36px + aria-label
- Kommentar-Inputs: aria-label (3 Views)
- Loading-States: 45+ mutierende Formulare (x-data loading + defaultPrevented-Guard)
- Stammdaten create/edit: 10 Browser-Titel
- Label-for/id-Verknüpfung portalweit (Workflow-Session, Rest siehe unten)

## Offen (bewusst zurückgestellt)

- ~~**[niedrig] Admin-Formular-Labels** weichen vom CLAUDE.md-Label-Standard ab~~ erledigt mit GUI-Paket 5 (einheitlich `text-sm font-medium text-text`, 12.09. geprüft: kein `<label … uppercase>` mehr unter `resources/views/admin`).
- ~~**[niedrig] Lichtkanten-Insets / Glow-Alphas im Light-Mode**~~ obsolet (12.09. geprüft): `np-glow-*`, `glass-lift`, `np-card-lift` und die Insets wurden in GUI-Paket 1/2 (f82b7e2, 5a6aad0) ersatzlos entfernt, siehe `docs/gui-konzept.md` «Entfallen».
- ~~**[niedrig] BenutzerController::update speichert vor zweiter Validierung**~~ erledigt (12.09.): bereits ein einziges `validate()` + `DB::transaction` in `Admin\BenutzerController::update` und `Verwaltung\LernendeController::update`; Tests `BenutzerControllerTest::ungueltige_eingabe_speichert_nichts` und `AnlegenUndBearbeitenTest::ungueltiges_bearbeiten_speichert_nichts` grün.
- ~~**[mittel] markAlleGesehen markiert ALLE Noten, auch ausserhalb des aktiven Filters**~~ erledigt (12.09.): Filter (`kategorie_id`/`semester_id`) wird im Formular mitgeschickt und serverseitig über `NotenGesehenController::gefilterteNoten()` angewendet (geteilt mit dem Zähler in `LernendeNotenController::index`); Test `AlleGesehenTest::markiert_nur_noten_im_aktiven_filter` grün.
- ~~**[niedrig] BB-Soft-Delete-Inkonsistenz** (Berufsbildner-Model ohne SoftDeletes trotz geloescht_am-Spalte)~~ erledigt (12.09. geprüft): `Berufsbildner` nutzt `SoftDeletes` mit `DELETED_AT = 'geloescht_am'`, Raw-Queries filtern `bb.geloescht_am`.

## Qualitätsblock Notenlogik & Dashboards (10.09.2026) – bewusst weggelassen

- **Zwischengruppen innerhalb eines Moduls** (`modul_note_gruppen`, `noten.gruppe_id`) entfernt statt ausgebaut: keine UI konnte sie anlegen, das Modul rechnet mit Ziel-Gewichtssumme. Falls ein Lehrbetrieb Teilnoten pro LB braucht, als eigene Ebene im Rechenkern nachrüsten.
- **`bewertungsregeln`** entfernt: nie befüllt; Grenzwerte liegen in `einstellungen`, Rundung/Promotion pro Kategorie.
- **Zielrechner mit unterschiedlichen Noten je offener Prüfung**: alle Unbekannten erhalten dieselbe Note (verständlichste Antwort auf «was brauche ich im Schnitt»). Individuelle Werte gehen über den Was-wäre-wenn-Modus.
- **Aktivitätsdiagramm Admin nach `erstellt_am`**: in der Demo-DB wirken alle Noten am Seed-Tag erfasst; in echten Daten korrekt. Kein Umbau auf `pruefungsdatum`, weil «Erfassungsaktivität» die Frage ist.
- ~~**Lichtkanten-/Glow-Feinheiten**~~ obsolet, siehe oben.
- **Zebra-Streifen und sticky thead** in Stammdaten-, Berichts- und Dashboard-Tabellen nicht ergänzt: Tabellen liegen in Glass-Karten mit Trennlinien, Zebra auf transparentem Glass wirkt unruhig; die Tabellen sind kurz (< 30 Zeilen) oder paginiert. Bei langen Listen (Lernende > 50) nachrüsten.

## Erstinbetriebnahme & Stammdaten (10.09.2026) – bewusst weggelassen

- **Lernende und Berufsbildner endgültig löschen**: nicht in der UI. Deaktivieren sperrt sofort, Notenhistorie und Betreuungsnachweis bleiben erhalten. Löschen nach Ablauf der Aufbewahrungsfrist gehört in einen eigenen Datenschutz-Prozess (Export, dann Anonymisierung), nicht als Knopf neben «Bearbeiten».
- **Modul wiederholen nur durch Lernende**: sie erfassen ihre Noten selbst und wissen, wann ein Modul neu beginnt. Berufsbildner sehen den Versuch über die Noten; eine Verwaltungsaktion folgt, falls im Pilot gewünscht.
- **Keine offiziellen Modulkataloge in den Lehrberuf-Vorlagen**: die Modullisten ändern sich je Bildungsverordnung und Kanton; falsche Vorgaben wären schlimmer als eine leere Liste. Module werden in der Einrichtung zeilenweise eingefügt (Nummer + Titel, Lernort je Feld).
- **Aktiv-Schalter für berufsspezifische Fächer** (`lehrberuf_faecher.aktiv`): wird nirgends gelesen, deshalb kein Schalter in der UI; Zuordnung entfernen genügt.
- **Letzter Admin**: die eigene Admin-Rolle lässt sich nicht entziehen; damit bleibt immer mindestens der handelnde Admin bestehen.

## Dokumente & Importe (10.09.2026) – bewusst weggelassen

- **Texterkennung (OCR) für eingescannte Zeugnisse und Fotos**: kein Tesseract auf dem Server; ein zusätzlicher Systemdienst mit Sprachdaten ist für den Pilot zu viel Betrieb. Scans werden abgelegt, der Abgleich meldet «Kein Text im PDF erkannt». Nachrüstbar über `thiagoalessio/tesseract_ocr` + Paket `tesseract-ocr-deu`.
- **Virenscan beim Upload**: kein ClamAV im Lab. Schutz über Typ-Whitelist (Endung und vom Server erkannter MIME-Typ), private Ablage, Auslieferung mit `nosniff` und Sandbox-CSP; SVG und HTML sind ausgeschlossen.
- **Import aus Excel: mehrere Blätter**: gelesen wird das erste Blatt. Mehrblättrige Mappen sind bei Notenlisten selten; wer eine hat, speichert das Blatt einzeln.
- **Ungültige Noten in der Vorschau** (z. B. 4.33): das Feld bleibt leer statt den Rohwert zu zeigen; die Zeile ist rot markiert und abgewählt.
- **Dateien im Backup**: erledigt mit Block F (Sicherung enthält `dateien/lernende`).

## Datensicherung (10.09.2026) – bewusst weggelassen

- **Wiederherstellen im Browser**: ein Knopf, der die laufende Datenbank überschreibt, ist ein zu grosses Risiko (Fehlklick, halbe Wiederherstellung bei Zeitüberschreitung). Wiederherstellung bleibt eine Notfallprozedur im Terminal (betrieb.md, LIESMICH.txt im ZIP).
- **Kopie ausser Haus**: erledigt 11.09.2026 (rsync in Ordner oder per SSH, Betrieb → Sicherungen). S3 bewusst nicht: braucht SDK und Zugangsschlüssel; ein SSH-Ziel oder eingebundenes Netzlaufwerk deckt den Pilot ab.
- **Verschlüsselung der ZIP-Datei**: Download nur für Admins über HTTPS-Ziel geplant; ein Passwort, das niemand mehr findet, macht die Sicherung wertlos.
- **Wochen-/Monatsstände**: 14 Tagesstände reichen für den Pilot; Speicherplatz wächst mit den Dokumenten.

## E-Mail, Agenda-Backend, Go-Live, echte Daten (11.09.2026) – bewusst weggelassen

- ~~**Tracks nach Datum der Note**: `NoteService::erlaubteFaecher` gibt Fächer nach den heute aktiven Tracks frei. Wer die BM verlassen hat, kann alte BM-Zeugnisnoten nicht übernehmen. Richtig wäre: Track gültig am Prüfungsdatum. Eingriff in den Rechenkern → eigener Block mit Tests.~~ erledigt (12.09.): `erlaubteFaecher(…, ?$stichtag)` (Standard heute, Rechner unverändert); Auswahllisten zeigen Fächer aller bisherigen Tracks (`auswahlFaecher`), Speichern/Bearbeiten/Import/Agenda prüfen den Track am Prüfungsdatum mit klarer Meldung; Import-Vorschau markiert «Track am Datum nicht aktiv». Tests: `TrackNachDatumTest`.
- **Schulnetz-«Zeugnisnoten» im Notenimport ohne Datum**: das PDF enthält nur Semesterspalten; das Datum wird in der Vorschau gesetzt. Automatisch aus dem Semesterende ableiten wäre möglich, falsch zugeordnete Semester wären aber schwer zu sehen.
- **Scans ohne Text** (siehe OCR oben) und **Ø-Spalte ohne Tabulator** im Zeugnis: nicht zuverlässig von einer Semesternote zu trennen.
- **S3/WebDAV als Kopie-Ziel**: siehe Datensicherung.
- **Let's Encrypt**: die VM ist nur im internen Netz erreichbar (keine öffentliche DNS/Port 80 von aussen); deshalb eigene CA. Mit öffentlichem Namen: `certbot --apache`.
- **HTTP → HTTPS-Umleitung**: bewusst noch nicht, solange Geräte die Lab-CA nicht vertrauen; Go-Live-Liste in betrieb.md.

## Hinweise

- ~~`bemerkung`-Feld auf `lernende` fehlt~~ erledigt (12.09. geprüft): Spalte auf Prod vorhanden, genutzt in `Verwaltung/LernendeController` und `verwaltung/lernende/edit`.
- border-red-500 vs border-border auf demselben Element: Gewinner hängt von CSS-Reihenfolge ab — falls roter Fehler-Rahmen nicht sichtbar, `!border-red-500` verwenden

## Sprache (11.09.2026) – bewusst weggelassen

- **Klassen, Methoden, Variablen, Views, DB-Tabellen auf Englisch**: Pflichtteil (Routennamen und URL-Pfade) ist umgesetzt, mit 301 von den alten Pfaden (`App\Support\LegacyPaths`). Der Rest berührt ~400 Dateien und das DB-Schema (Tabellen `benutzer`, `lernende`, `noten` … mit Fremdschlüsseln); für den Go-Live am 30.09. zu riskant. Vorgehen danach: pro Bereich ein Workflow mit Haiku-Agents (Umbenennen) und Tests als Netz, DB zuletzt mit Dump/Tag und Views/Aliassen.
- ~~**Sprachdateien und Umschaltung Deutsch/Englisch pro Benutzer**~~ überholt: Gerüst umgesetzt am 12.09. (siehe «Sprache DE/EN» unten, `docs/i18n-plan.md`).

## Agenda, Feedback, Themes (11.09.2026)

- iCal-Export-Oberfläche für Berufsbildner/Admins: Server kann es (`CalendarExport::forUser`), Anzeige nur für Lernende gebaut – Bedarf im Pilot abwarten.
- Feedback: keine Screenshot-Vorschau vor dem Senden (Aufnahme erst beim Senden, robuster); keine Duplikaterkennung.
- Agenda-Query-Parameter (`ansicht`, `monat`) noch deutsch; bei der späteren Code-Umbenennung mitziehen (LegacyPaths betrifft nur Pfade).
- Mobile Filterformulare (Lernende, Benutzer) sehr lang: Filterleiste in GUI-Paket 5.
- `Uebersicht::berufsbildner()` liefert `vergleich` noch, das BB-Dashboard nutzt seit Paket 6 Small Multiples – entfernen, sobald Paket 4 das Dashboard umbaut.
- ~~Senkrechte Genügend-Linie für `balken()` (Lernenden-Dashboard) mit Paket 3.~~ erledigt.

## Paket 3 – Lernende (11.09.2026) – bewusst weggelassen

- ~~Drawer für «+ Note» nur auf `/grades`; auf der Übersicht führt «+ Note» noch auf die Seite `grades/create`.~~ erledigt (11.09., Reste-Session): `NoteService::drawerNachFehler()` + neue Komponente `<x-noten-drawer>` extrahiert, Dashboard (`dashboards/lernender.blade.php`) nutzt jetzt denselben Drawer wie `/grades`.
- ~~Kein «Als Tabelle»-Umschalter für die Diagramme «Wo stehe ich» und «Verlauf» (Screenreader bekommen nur `aria-label`/Tooltip).~~ erledigt (12.09.): `<details>`-Tabellenalternative (gleiches Muster wie `<x-diagramm>`) unter beiden Diagrammen ergänzt, serverseitig aus denselben Daten gerendert (`resources/views/dashboards/lernender.blade.php`); Test `UebersichtUndNotenTest::dashboard_bietet_tabellenalternative_fuer_wo_stehe_ich_und_verlauf`.
- ~~Segment-Umschalter (`role="radiogroup"`) ohne Pfeiltasten-Navigation; Tab + Enter funktioniert.~~ erledigt (11.09., Reste-Session): `x-radiogroup`-Direktive in `np.js` (Roving Tabindex + Pfeiltasten), angewendet auf alle `role="radiogroup"`-Container in `resources/views` (native `<input type="radio">` in `notifications/settings.blade.php` bewusst unverändert, dort bereits nativ bedienbar).
- ~~`np.js` `notenKlasse` nutzt noch Palettenfarben statt Noten-Tokens (nicht im Paket).~~ erledigt (11.09., Reste-Session): `TEXT`-Map auf `text-note-*`-Tokens umgestellt.
- ~~Bullet Graph ohne Bandnamen~~ erledigt (12.09.): Legende ungenügend/knapp/genügend/gut unter «Wo stehe ich», Spalte «Stufe» in «Als Tabelle». Offen: dieselben Stufennamen in den Admin-/Berichtsdiagrammen. Lange Fach-/Modulnamen mobil weiterhin auf 16 Zeichen gekürzt (voll im Tooltip).
- `x-sparkline` zeigt jetzt standardmässig den letzten Wert als Zahl – auch in der BB-Tabelle; dort ggf. `:zahl="false"` setzen (Paket 4).
- Einzelnoten-Tabelle mobil: Spalte «Schnitt vor Rundung» ab `sm` ausgeblendet.

## Paket 4 – Berufsbildner (11.09.2026) – bewusst weggelassen

- `Uebersicht::berufsbildner()` liefert `vergleich`/`vergleichDiagramm()` nicht mehr – die Small-Multiples-Karte «Verlauf im Vergleich» (Paket 6) entfällt, «Im Vergleich» ist jetzt die sortierbare Verlauf-Spalte in der Tabelle (siehe Zeile 104 oben, damit erledigt).
- ~~Spalten der Tabelle «Meine Lernenden» sind nicht klicksortierbar~~ erledigt (12.09.): Sortierlinks mit `aria-sort` (`?sort=name|status|semester|gesamt|trend&dir=`), Allowlist in `Uebersicht::BB_SORTIERUNGEN`, Standard weiter Status → Nachname; Test `DashboardSortierungTest`.
- ~~Segment-Filter in «Meine Lernenden» auf 390px ausgeblendet~~ erledigt (12.09.): auch mobil sichtbar, horizontal scrollbar unter der Suche.
- `x-sparkline` in Tabelle und Cockpit-Stand mit `:zahl="false"` verwendet, um die Redundanz mit der danebenstehenden Semester-/Gesamt-Note zu vermeiden (löst den Hinweis aus Paket 3 oben).
- Keine «Vorher»-Screenshots erstellt, bevor die Änderungen begannen (Vorgabe verpasst); nur «Nachher»-Screenshots (hell/dunkel/mobil) unter `~/tools/out/paket4/` liegen vor.
- Cockpit-Reiter «Noten», «Dokumente», «Rechner» verlinken auf die bestehenden Einzelseiten statt Inline-Panels zu zeigen (kein eigener Seiteninhalt für diese Tabs im Blueprint verlangt).

## Paket 5 – Tabellen & Admin (11.09.2026)

- Erledigt: Filterleiste (`x-filterleiste`) auf allen gefilterten Admin-Indexseiten (Benutzer, Lernende, Module, Semester, Feedback, Mail-Log); Tabellen auf Katalog-Design (44px-Zeilen, sticky `bg-surface-2`-Header ohne Versalien, rechtsbündige `tabular-nums`-Zahlen, Hover/Focus-Reveal-Aktionen, auf Mobil immer sichtbar).
- Erledigt: Formular-Labels von Versalien/`tracking-widest` auf `text-sm font-medium text-text` vereinheitlicht (Admin-Einrichtung, Benutzer, Stammdaten, Lernende, Noten, Profil, Rechner, Dokumente, Import) – absichtlich unverändert gelassen: Statistik-Kacheln/Sektionsüberschriften im Dashboard-Stil (z. B. `einrichtung/modules.blade.php`, `rechner/index.blade.php`, `dokumente/index.blade.php`, `import/index.blade.php`) sowie ein erzwungenes Versal-Kürzelfeld (`einrichtung/professions.blade.php`).
- Erledigt: Admin-Übersicht neu strukturiert – eine «Handlungsbedarf»-Liste oben (Einrichtungslücken, Sicherung älter als 2 Tage, offene Meldungen, kritische Lernende) statt vier Karten, Kennzahlen als Statuszeile, Berufsbildner-Last- und Erfassung-12-Wochen-Karten bleiben. Backend dafür: `Uebersicht::admin()` liefert jetzt `handlungsbedarf` statt `einrichtung`/`kritisch`/`jahrgaenge`, neue private Methode `adminHandlungsbedarf()`.
- Erledigt: `admin/einrichtung/*` und `admin/berichte/noten` auf `<x-seitenkopf>` umgestellt; Diagramm-Logik in `admin/berichte/noten` unverändert gelassen.
- Erledigt: Bare-`glass`-Alias durch Tokens (`rounded-2xl border border-border bg-card` bzw. `rounded-xl …`) ersetzt, `glass-btn` unverändert gelassen; dabei auch verstreute Farb-Hardcodes (`red-*`, `green-*`, `yellow-*`) auf Noten-Tokens umgestellt.
- Erledigt (Reste-Session, 11.09.): letzter verbliebener Nutzer des Alias (`layouts/guest.blade.php`) auf Tokens umgestellt; `@utility glass` danach ohne Nutzer und aus `resources/css/app.css` entfernt (`glass-overlay`/`glass-bar`/`glass-scrim`/`glass-btn` bleiben bestehen).
- Test: `tests/Feature/Admin/DashboardTest.php` neu (Statuszeile/Handlungsbedarf, Sicherung-Alter-Hinweis) – zusammen mit dem restlichen Admin/Verwaltung-Testset grün (67 bzw. 45 Tests via `DB_DATABASE=notenportal_d_test`).
- Bug gefunden und behoben (nicht Teil der eigentlichen Aufgabe, aber blockierend): `admin/benutzer/index.blade.php` mischte die Inline-Form `@php(...)` mit einer späteren Block-Form `@php … @endphp` im selben Template – Blade paart beim Kompilieren den *ersten* `@php` unabhängig von seiner Form mit dem *nächsten* `@endphp` und verschluckte dadurch den kompletten Abschnitt dazwischen (Filterleiste, Tabelle) als unkompilierten Rohblock, was zu einem 500er auf `/admin/users` führte. Fix: Zeile auf reines `<?php … ?>` umgestellt; nur diese eine Zeile geändert, keine Alt-Logik berührt.
- ~~Bewusst weggelassen: Migration der Karte «Gesamtschnitt nach Lehrjahr» vom Admin-Dashboard in den Notenbericht (`admin/berichte/noten`).~~ erledigt (12.09.): kompakte Karte in `admin/berichte/noten.blade.php` (Tabelle Lehrjahr/Ø/Lernende), `Bericht::nachLehrjahr()` gruppiert die bereits vorhandenen `gesamt`-Werte aus dem Rechenkern nach `Lernender::lehrjahr()`; Test `BerichtTest::gesamtschnitt_nach_lehrjahr_gruppiert_und_zaehlt_lernende`.
- Keine «Vorher»-Screenshots erstellt (wie schon in Paket 4); nur «Nachher» (hell/dunkel/mobil) unter `~/tools/out/paket5/`.

## Sichtprüfung Pakete 3–5 (11.09.2026)

- ~~Admin «Erfasste Noten pro Woche»: KW-Beschriftungen schräg; laut Konzept jede 4. KW beschriften, nicht schräg (`charts.js`).~~ erledigt (Reste-Session): `maxRotation`/`minRotation` 0, Tick-`callback` beschriftet nur jede 4. KW (von rechts gezählt).
- ~~`/admin/learners` mobil: jede Karte hat einen gefüllten «Noten»-Button → mehrere Primäraktionen; «Noten» sekundär oder Zeilenklick.~~ erledigt (Reste-Session): «Noten»-Link auf sekundären Stil (Rahmen statt `bg-accent`) umgestellt, «Profil» bleibt einzige Primäraktion.
- ~~Lernenden-Verlauf: Direktlabels kürzerer Reihen («ÜK», «ABU») stehen auf der Linie am Reihenende statt rechts neben der Grafik.~~ erledigt (Reste-Session): `direktlabelPlugin` verankert die X-Position jetzt immer am rechten Plot-Rand (`chartArea.right + 6`) statt am eigenen letzten Datenpunkt der Reihe.
- ~~«Gesamtschnitt nach Lehrjahr» vom Admin-Dashboard entfernt, im Notenbericht noch nicht ergänzt.~~ erledigt (12.09.), siehe Paket 5 oben.

## Nachbesserungen Sichtprüfung Paket 5 (11.09.2026, Reste-Session)

Drei zusätzliche Befunde aus dem Review, im Rahmen derselben Session behoben:

- Filterformular in `verwaltung/noten/index.blade.php` liess sich ohne JS nicht absenden (nur `onchange` auf den `<select>`n) → `<noscript>`-Button «Filtern» ergänzt; Test `NotenKorrekturTest::notenliste_filter_hat_ohne_js_einen_absende_button`.
- `filterleiste.blade.php`: «Weitere Filter»-Panel nutzte `x-cloak` und blieb ohne JS dauerhaft unsichtbar (z. B. «Inaktive anzeigen» in `verwaltung/lernende/index`) → `x-cloak` entfernt, Alpine-State startet offen (`offen: true`), wenn in «Weitere» aktive Filter stehen.
- `scope="col"` auf allen `<th>` ergänzt in: `verwaltung/lernende/index`, `admin/benutzer/index`, `admin/feedback/index`, `admin/berufsbildner/index`, `admin/stammdaten/{module,kategorien,lehrberufe,faecher,semester}/index`, `admin/mail-log/index`, `dashboards/admin` (Berufsbildner-Last-Tabelle). `admin/notifications/index` und `verwaltung/noten/index` enthalten keine `<th>` (keine Tabelle) — nichts zu ändern.

## Sprache DE/EN (12.09.2026, Gerüst fertig, Sprachwahl aus)

- Vor dem Einschalten von `sprachwahl_aktiv`: englische Texte von einem Menschen gegenlesen lassen (`lang/en.json`, `lang/areas/*/en.json`).
- ~~Mails mit vorgebautem `MailContent` gehen in der Sprache des Auslösers statt des Empfängers: `KommentarController`, `Lernender/NotenController`, `NotenImportController`, `FeedbackController`, `Verwaltung/{NotenGesehen,Betreuung,LernendeNoten}Controller`, `Admin/{Feedback,MailSettings}Controller`, `Auth/{NewPassword,PasswortWechsel,Password}Controller`, `CheckNotifications`, `SicherungErstellen`, `GradeWatcher`, `AccountMails` → Inhalt erst im `Notifier` pro Empfänger bauen.~~ erledigt (12.09.): alle Aufrufe geben den Inhalt als Closure an `Notifier::send`/`dispatch`, `CheckNotifications::melden()` nimmt jetzt `MailContent|Closure`. `AccountMails` und `Auth/{NewPassword,PasswortWechsel,Password}Controller` bauen ihren Text weiterhin ohne `__()` (fest Deutsch) – Closure-Form vorbereitet, aber noch nicht wirklich mehrsprachig; `Admin/MailSettingsController::test()` unverändert, da der Test-Empfänger kein `User` mit eigener Locale ist.
- Übersetzungen sind portalweit flach (ein Wert pro deutschem Schlüssel, 26 Kollisionen beim Zusammenführen nach Glossar vereinheitlicht). Mehrdeutige Wörter («Semester», «Berufsbildner», «Fehler») bei Bedarf mit Kontext-Schlüsseln lösen.
- ~~`Dokument::ARTEN`-Labels, Einrichtungs-Info-Texte, Import-Meldungen, Konto-/Passwort-Mails unübersetzt~~ erledigt (12.09.): `Dokument::label()`, `Einrichtung::stand()`, `NotenImport` (Status über internen Code), `AccountMails`/Auth-Controller mit `__()`; `i18n-scan` 26 → 21 Textknoten.
- Rest laut `notenportal:i18n-scan` (bewusst offen): `vendor/pagination/*` (eigene deutsche Laravel-Views), CSV-Vorlage `NotenImport::vorlage()` (Kopfzeilen bleiben deutsch, der Import erkennt beide), Beispielwerte in Platzhaltern, Kürzel BMS/ABU.
- ~~Pint-Altlasten: `KommentarController`, `routes/web.php`~~ erledigt (12.09.).
- Nach dem Go-Live: Umbenennungen Code/Model/DB gemäss `docs/i18n-plan.md`.
