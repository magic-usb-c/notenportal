# Architektur

## Verzeichnisse
```
app/Http/Controllers/
  Admin/           Benutzer, Lernende, Berufsbildner, Noten, Stammdaten*, Bericht
  Berufsbildner/   Lernende, Noten
  Lernender/       Noten
  Auth/            Login/Logout, Passwort ändern
  DashboardController (alle Rollen), KommentarController, ProfileController
app/Http/Middleware/  RoleMiddleware (role:Name), EnsureUserIsActive (global in web)
app/Models/        User (Tabelle benutzer), Lernender, Berufsbildner, Betreuung, Lehrberuf,
                   Note, Kategorie, Semester, Fach, Modul, ModulBelegung, ModulNoteGruppe,
                   NotenGesehen, NotenKommentar, Rolle
app/Services/      Noten/NoteService (fachModulStats, normalizeForSave), Benutzer/LernendeErfassungService
app/Support/       Csv::safe() (Formel-Injection-Schutz)
resources/views/
  layouts/         app, guest, navigation
  dashboards/      admin, berufsbildner, lernender
  lernender/noten/ index, create, edit, drucken (von allen Rollen genutzt), rechner, partials/notes-table
  berufsbildner/   noten, lernende
  admin/           benutzer, lernende, berufsbildner, stammdaten, berichte
  components/      noten-verlauf, fach-modul-stats
resources/css/     app.css (Glass, Animationen), theme.css (Tokens hell/dunkel)
tests/Feature/     Auth/LoginTest, Auth/ZugriffsschutzTest, ProfileTest
```

## Rollen
| Rolle | Darf |
|---|---|
| Admin | Alles: Admins, Berufsbildner, Lernende, Stammdaten, Berichte |
| Berufsbildner | Lernende verwalten wie Admin, aber nur aktiv betreute (`betreuungen.gueltig_von/bis`). Noten lesen, korrigieren (mit «geändert von»), kommentieren, als gesehen markieren. Keine Noten anlegen oder löschen. |
| Lernender | Nur eigene Daten: Noten CRUD, Rechner, Export, Druck, Profil |

Lernender-ID kommt immer aus der Session (`$request->user()->lernender`), nie aus dem Request.

## Datenmodell
```
benutzer          benutzer_id, benutzername, email (Login), vorname, nachname, passwort_hash, aktiv, geloescht_am
rollen            rolle_id, name (Admin, Berufsbildner, Lernender)
benutzer_rollen   benutzer_id, rolle_id
lernende          lernender_id, benutzer_id, lehrberuf_id, lehrbeginn, lehrende, geloescht_am
berufsbildner     berufsbildner_id, benutzer_id, geloescht_am
betreuungen       betreuung_id, berufsbildner_id, lernender_id, gueltig_von, gueltig_bis
lernender_tracks  lernender_id, track_typ (BMS/ABU), start_datum, end_datum, start/end_semester_id
lehrberufe        lehrberuf_id, kuerzel, name, aktiv
lehrberuf_faecher / lehrberuf_module   Zuordnung Beruf ↔ Fach/Modul (pflicht, empfohlenes_lehrsemester_nr)
kategorien        kategorie_id, code (FACH, UEK, BMS, ABU), name, sortierung, aktiv
faecher           fach_id, track_typ (BMS/ABU), name, kurzname
module            modul_id, modul_nummer, titel, ziel_gewicht_summe_default
modul_belegungen  modul_belegung_id, lernender_id, modul_id, start/end_datum
modul_note_gruppen gruppe_id, modul_belegung_id, bezeichnung, ziel_gewicht_summe
noten             note_id, lernender_id, kategorie_id, semester_id, fach_id XOR modul_belegung_id, gruppe_id,
                  titel, pruefungsdatum, note_wert (1–6), gewichtung_prozent,
                  erfasst_von_benutzer_id, aktualisiert_von_benutzer_id, geloescht_am
noten_gesehen     note_id, viewer_benutzer_id, gesehen_am
noten_kommentare  kommentar_id, note_id, autor_benutzer_id, kommentar_text
semester          semester_id, bezeichnung, start_datum, end_datum, sortierung
bewertungsregeln  Grenzwerte je Scope (GLOBAL/KATEGORIE/FACH/MODUL), derzeit leer
```
Zeitstempel heissen `erstellt_am`, `aktualisiert_am`, Soft-Delete `geloescht_am`. Integrität über CHECK-Constraints und Composite-FKs (MariaDB).
