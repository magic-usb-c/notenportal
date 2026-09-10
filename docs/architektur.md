# Architektur

## Verzeichnisse
```
app/Http/Controllers/
  Admin/           Benutzer (nur Admin/BB-Konten), Berufsbildner, Stammdaten*, Bericht, Feedback
  Verwaltung/      Lernenden-Verwaltung für Admin + BB: Lernende (Cockpit), LernendeNoten, NotenGesehen,
                   NotenExport (Notenblatt, CSV), Rechner, Betreuung, Track, Konto
                   (routes/verwaltung.php, 2× eingebunden)
  Lernender/       Noten, Rechner, Pruefungen, Ziele
  Auth/            Login/Logout, Passwort ändern
  DashboardController (alle Rollen), SucheController, KommentarController, ProfileController
app/Http/Middleware/  RoleMiddleware (role:Name), EnsureUserIsActive (global in web)
app/Models/        User (Tabelle benutzer), Lernender, Berufsbildner, Betreuung, Lehrberuf, Note, Kategorie,
                   Semester, Fach, Modul, ModulBelegung, Pruefung, Ziel, NotenGesehen, NotenKommentar, Rolle
app/Services/
  Auswertung/      Rechenkern (docs/notenlogik.md): Leistung → Element (Fach×Semester | Modul) → Kategorie → Gesamt;
                   Konfiguration (Regeln aus DB), NotenQuelle (Noten + geplante Prüfungen, gebündelt),
                   Auswertung, Rechner (Szenarien), Zielrechner (benötigte Note), Zielgroesse,
                   LernstandRechner/Lernstand (Ampel mit Gründen), Rundung
  Uebersicht       Dashboards je Rolle, Lernenden-Cockpit, Heatmap
  Bericht          Admin-Notenbericht; Notenblatt (Druckansicht)
  Noten/NoteService  Bezug Fach/Modul, Kategorie ableiten, normalizeForSave
  Benutzer/        LernendeErfassungService, Startpasswort
app/Policies/      LernenderPolicy (view, update, verwalten, betreuungVerwalten, noteAnlegen/-Korrigieren/-Loeschen)
app/Support/       Csv::safe(), Einstellungen, NotenSkala (Grenzen, Farben), Zahl, Navigation (Menü je Rolle, Befehle)
resources/views/
  layouts/         app, guest, navigation (Pills, Dropdowns, Befehlspalette, Mobile)
  dashboards/      admin, berufsbildner, lernender
  lernender/       noten (index, create, edit, partials/note), pruefungen
  verwaltung/      lernende (index, create, show = Cockpit, edit), noten (index, create, edit) – rollenneutral
  noten/           _formular (gemeinsames Notenformular), notenblatt (Druck, alle Rollen)
  rechner/         index (Lernender und Verwaltung)
  admin/           benutzer, berufsbildner, stammdaten, berichte
  components/      kachel, karte, note, status, heatmap, sparkline, note-geaendert
resources/js/      np.js (Helfer), charts.js (npChart: verlauf, kurve, balken, gruppen, saeulen), rechner.js, suche.js
resources/css/     app.css (Glass, Animationen), theme.css (Tokens hell/dunkel inkl. Chart- und Notenfarben)
tests/Unit/Auswertung/  Rechenkern, Zielrechner
tests/Feature/     Auth, Zugriffsschutz (jede role:-Route), Datenisolation, Sichtbarkeit, Rechner, Prüfungen, Suche, Bericht, Stammdaten
```

## Rollen
| Rolle | Darf |
|---|---|
| Admin | Alles: Admins, Berufsbildner, Lernende, Stammdaten, Berichte |
| Berufsbildner | Lernende verwalten wie Admin, aber nur aktiv betreute (`betreuungen.gueltig_von/bis`). Noten lesen, korrigieren (mit «geändert von»), kommentieren, als gesehen markieren. Keine Noten anlegen oder löschen. |
| Lernender | Nur eigene Daten: Noten, Prüfungen, Ziele, Rechner, Export, Druck, Profil |

Sichtbarkeit von Lernenden für Admin/BB an einer Stelle: `Lernender::sichtbarFuer($user)`; Controller laden Lernende nur darüber (sonst 404), Noten nur über den sichtbaren Lernenden. Rechte pro Aktion über LernenderPolicy (403).
Startpasswörter (Anlegen, Zurücksetzen) werden generiert, einmalig angezeigt, `passwort_wechsel_noetig = true`. `lernende.bemerkung` ist intern (nur BB/Admin).

Lernender-ID kommt immer aus der Session (`$request->user()->lernender`), nie aus dem Request.

## Notenlogik
Alle Durchschnitte kommen aus `App\Services\Auswertung` – keine Schnitte in Controllern, Views oder SQL. Regeln, Begriffe und Beispiele: `docs/notenlogik.md`. Kategorie einer Note wird abgeleitet (Fach → `faecher.kategorie_id`, Modul → `lehrberuf_module.kategorie_id` = Lernort), nie vom Formular übernommen.

## Datenmodell
```
benutzer          benutzer_id, benutzername, email (Login), vorname, nachname, passwort_hash, aktiv, geloescht_am
rollen            rolle_id, name (Admin, Berufsbildner, Lernender)
benutzer_rollen   benutzer_id, rolle_id
lernende          lernender_id, benutzer_id, lehrberuf_id, lehrbeginn, lehrende, bemerkung, geloescht_am
berufsbildner     berufsbildner_id, benutzer_id, geloescht_am
betreuungen       betreuung_id, berufsbildner_id, lernender_id, gueltig_von, gueltig_bis
lernender_tracks  lernender_id, track_typ (BMS/ABU), start_datum, end_datum, start/end_semester_id
lehrberufe        lehrberuf_id, kuerzel, name, aktiv
lehrberuf_faecher Beruf ↔ Fach (Fächer ohne Track)
lehrberuf_module  Beruf ↔ Modul: kategorie_id (Lernort FACH/UEK), pflicht, empfohlenes_lehrsemester_nr
kategorien        kategorie_id, code, name, sortierung, aktiv, rundung_element, rundung_schnitt, gewicht_gesamt,
                  promotion_min_schnitt, promotion_max_ungenuegend, promotion_max_minuspunkte
faecher           fach_id, kategorie_id, track_typ (nullable = ohne Track), name, kurzname
module            modul_id, modul_nummer, titel, ziel_gewicht_summe_default
modul_belegungen  modul_belegung_id, lernender_id, modul_id, start/end_datum (Wiederholung = neue Belegung, nur jüngste zählt)
noten             note_id, lernender_id, kategorie_id (abgeleitet), semester_id, fach_id XOR modul_belegung_id,
                  titel, pruefungsdatum, note_wert (1–6), gewichtung_prozent,
                  erfasst_von_benutzer_id, aktualisiert_von_benutzer_id, geloescht_am
pruefungen        pruefung_id, lernender_id, fach_id XOR modul_id, titel, datum, gewichtung_prozent
ziele             ziel_id, lernender_id, ebene (gesamt/kategorie/fach/modul), kategorie_id/fach_id/modul_id, zielwert
noten_gesehen     note_id, viewer_benutzer_id, gesehen_am
noten_kommentare  kommentar_id, note_id, autor_benutzer_id, kommentar_text
semester          semester_id, bezeichnung, start_datum, end_datum, sortierung
einstellungen     schluessel/wert: betrieb_name, note_gut/genuegend/kritisch, rundung_gesamt, frist_inaktiv_tage, frist_lehrende_tage
```
Zeitstempel heissen `erstellt_am`, `aktualisiert_am`, Soft-Delete `geloescht_am`. Integrität über CHECK-Constraints und Composite-FKs (MariaDB).

Migrationen laufen zuerst gegen `notenportal_probe` (Kopie von Prod), dann sofort gegen Prod – die Arbeitskopie ist gleichzeitig der Produktionsserver.
