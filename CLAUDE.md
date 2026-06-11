# Notenportal – Claude Code Projektdokumentation

## Projektübersicht

Webportal das Excel-Notentabellen bei Hamilton Bonaduz AG ersetzt.
Lernende erfassen selbstständig Noten aus Schule, ÜK und BMS.
Berufsbildner behalten den Überblick ohne direkten Schulportal-Zugriff.

**Zielgruppe:** Lernende 16–22 Jahre + Berufsbildner + Admins Hamilton Bonaduz AG
**Status:** Aktive Entwicklung, noch nicht produktiv

---

## Umgebung

```
Server:     Ubuntu 24.04 VM, /var/www/notenportal
Webserver:  Apache2
DB:         MariaDB, Datenbank: notenportal
Branch:     feature/claude-fertigstellung
Remote:     github.com/magic-usb-c/notenportal (Token in Remote-URL, push direkt)
PHP:        8.3
Node:       für Vite/Tailwind Build
```

**Testbenutzer (Passwort überall: Chur7000)**
| User    | Rolle          | URL-Bereich     |
|---------|----------------|-----------------|
| admin   | Admin          | /admin          |
| peter   | Berufsbildner  | /berufsbildner  |
| david   | Lernender      | /noten          |
| nando   | Lernender      | /noten          |
| jan     | Lernender      | /noten          |
| lukas   | Lernender      | /noten          |
| nils    | Lernender      | /noten          |

---

## Tech-Stack

- **Backend:** Laravel 12, PHP 8.3
- **Frontend:** Blade Templates, Tailwind CSS, Alpine.js, Vite
- **Datenbank:** MariaDB (np_web User)
- **Sprache UI:** Schweizer Hochdeutsch (ss statt ß, NIEMALS ß verwenden)

---

## Datenbank-Constraints (KRITISCH)

```
DB-User np_web hat: SELECT, INSERT, UPDATE, DELETE auf notenportal.*
                    CREATE, DROP, INDEX, ALTER nur auf notenportal.migrations
```

**Was das bedeutet:**
- `php artisan migrate` schlägt fehl wenn Migrations `ALTER TABLE` auf andere Tabellen machen
- `php artisan test` schlägt fehl (Test-DB kann nicht migriert werden) → IGNORIEREN
- Neue Spalten zu bestehenden Tabellen: nur via direktem `sudo mysql` möglich
- Neue Tabellen via Migration: funktioniert (CREATE auf allen Tabellen erlaubt)
- Kein `sudo` ohne Passwort in Claude Code Sessions verfügbar

**Workaround für neue Spalten:** Direkt dokumentieren und User informieren,
dass sie `sudo mysql notenportal -e "ALTER TABLE ..."` manuell ausführen müssen.

---

## Design-System (NIEMALS überschreiben)

### CSS Custom Properties (in resources/css/theme.css)
```css
--bg, --bg-rgb           /* Seitenhintergrund */
--card, --card-rgb       /* Card-Hintergrund */
--text                   /* Primärtext */
--muted                  /* Sekundärtext/Labels */
--border                 /* Rahmenfarben */
--input                  /* Input-Hintergrund */
--accent, --accent-rgb   /* Akzentfarbe (Indigo/Blau) */
--ring                   /* Focus-Ring */
```

### Tailwind-Klassen (immer verwenden)
```
bg-bg, bg-card, bg-input, bg-accent
text-text, text-muted, text-accent
border-border
```

**NIEMALS hardcoden:** `bg-white`, `bg-gray-800`, `bg-gray-900`, `text-black`,
`text-white` (ausser in SVG/Print/Druckansicht), `#[hex-farbe]` in Blade-Views.

### Liquid-Glass-System (bereits implementiert in app.css)
```css
.glass          /* Primäre Cards, Panels – backdrop-filter: blur(20px) saturate(160%) */
.glass-subtle   /* Nav, Sidebars – blur(12px) */
.glass-btn      /* Sekundäre Buttons */
.glass-lift     /* Hover-Lift mit Tiefe */
.np-card-lift   /* Leichter Lift ohne Glass */
.accent-glow    /* Accent-farbiger Glow für wichtige Elemente */
```

### Farb-Logik Noten (konsistent ÜBERALL verwenden)
```php
>= 5.0 → text-green-600 dark:text-green-400   / bg-green-500 (Balken)
>= 4.0 → text-emerald-600 dark:text-emerald-400 / bg-emerald-500
>= 3.5 → text-yellow-600 dark:text-yellow-400   / bg-yellow-500
<  3.5 → text-red-600 dark:text-red-400         / bg-red-500
```

---

## Architektur

### Verzeichnisstruktur (wichtigste Pfade)
```
app/Http/Controllers/
  Admin/           BenutzerController, LernendeController, BerufsbildnerController,
                   NotenController, StammdatenControllers, BerichtController
  Berufsbildner/   LernendeController, NotenController
  Lernender/       NotenController
  DashboardController (alle 3 Rollen)
  KommentarController
app/Models/        User (= Benutzer), Note, Kategorie, Semester, ...
app/Services/      NoteService (fachModulStats() zentral)
resources/views/
  layouts/         app.blade.php, navigation.blade.php
  dashboards/      admin.blade.php, berufsbildner.blade.php, lernender.blade.php
  lernender/noten/ index, create, edit, drucken, rechner, partials/notes-table.blade.php
  berufsbildner/   noten/index, lernende/index, lernende/show
  admin/           benutzer/, lernende/, berufsbildner/, stammdaten/, berichte/
  components/      x-noten-verlauf (SVG Liniendiagramm), x-fach-modul-stats
resources/css/
  app.css          Liquid-Glass-System, Animationen, globale Utilities
  theme.css        CSS Custom Properties (Hell + Dunkel)
```

### Rollenmodell
```
Admin          → Alle Bereiche, Benutzerverwaltung, Stammdaten, Berichte
Berufsbildner  → Noten lesen, kommentieren, als gesehen markieren,
                 Lernenden-Profil (read-only + Lehrdaten inline bearbeiten)
Lernender      → Eigene Noten CRUD, Rechner, Export, Druckansicht
```

### Datenmodell (Haupttabellen)
```
benutzer          id, benutzername, email, vorname, nachname, passwort_hash, aktiv
rollen            id, name
benutzer_rollen   pivot
lernende          id, benutzer_id, lehrberuf_id, lehrbeginn, lehrende
berufsbildner     id, benutzer_id
betreuungen       id, berufsbildner_id, lernender_id, gueltig_von, gueltig_bis
noten             id, lernender_id, kategorie_id, semester_id, fach_id,
                  modul_belegung_id, titel, pruefungsdatum, note_wert,
                  gewichtung_prozent, erstellt_am, geloescht_am (soft-delete)
noten_gesehen     note_id, gesehen_von_benutzer_id, gesehen_am
noten_kommentare  id, note_id, autor_benutzer_id, kommentar_text, erstellt_am
semester          id, bezeichnung, start_datum, end_datum, sortierung
kategorien        id, code, name, sortierung (SCHULE, UEK, BMS, ABU)
faecher           id, name, track_typ
module            id, modul_nummer, titel, ziel_gewicht_summe_default
modul_belegungen  id, lernender_id, modul_id
lehrberufe        id, name, kuerzel, aktiv
lernender_tracks  id, lernender_id, track_typ (BMS/ABU), start_datum, end_datum
```

---

## Was bereits implementiert ist

**Lernender:**
- Noten CRUD mit Validierung, Soft-Delete
- Accordion-Ansicht nach Fach/Modul, gewichtete Durchschnitte
- Semester-Navigation (Pfeiltasten ←/→, schwebende Pill)
- Kategorie-Auswertung als 4er-Grid (SCHULE/ÜK/BMS/ABU)
- Semester-Übersicht collapsible mit Balkendiagramm
- CSV-Export, Druckansicht mit Unterschriften-Zeile
- Noten-Rechner (Alpine.js Live-Berechnung)
- Live Ø-Vorschau beim Erfassen (mit Delta-Pfeil)
- Notiz inline bearbeiten (PATCH-AJAX, kein Reload)
- Gewichtungs-Schnellwahl (25/50/100%)
- Tastatur: N=Neu, Esc=Accordions schliessen, J/K=Navigation
- Deep-Link ?_open=<note_id> öffnet Semester + Accordion automatisch

**Berufsbildner:**
- Noten aller betreuten Lernenden lesen
- Kommentare erfassen, als gesehen markieren, Neu-Badge
- Lernenden-Profil (read-only + Lehrdaten inline PATCH)
- CSV-Export aller Noten betreuter Lernender
- «Zu tun»-Bereich: inaktiv 30d, Ø<4.0, Lehrende <30d mit mailto

**Admin:**
- Benutzerverwaltung CRUD (alle Rollen), Passwort-Generator
- Rollen-Auswahl als Card-Radios mit Alpine-Transition
- Schnellsuche Ctrl+K in Admin-Nav
- Quick-Actions auf Dashboard (Neuer Benutzer, Betreuungen, Semester, Berichte)
- Berufsbildner-Übersicht mit Statistiken
- Stammdaten CRUD: Lehrberufe, Module, Fächer, Semester, Kategorien
- Schulweite Berichte mit Kategorie-Auswertung, Histogramm, BB-Filter
- Sortierbare Spalten in Berichten
- Lernenden-Detail mit Semester-Stats

**Global:**
- Dark-Mode-Toggle (localStorage-persistiert) inkl. Mobile-Menü
- Flash-Toast (success/error, Alpine.js, auto-dismiss)
- Liquid-Glass-CSS-System (glass, glass-subtle, glass-btn, glass-lift)
- Page-Progress-Bar beim Navigieren
- Notenverlauf-Liniendiagramm (SVG, x-noten-verlauf Komponente)
- Fach/Modul-Stats-Bars (NoteService::fachModulStats())
- Avatar-Initialen in Admin-Tabellen
- Sticky thead, Zebra-Stripes in Tabellen
- Count-up-Animation auf Zahlen (Lernender-Dashboard Score-Card)
- Accent-Orbs als Hintergrund-Tiefe auf Dashboards
- Fehlerseiten (404, 403, 500) auf Deutsch
- Footer, seitenspezifische Browser-Titel
- Schweizer Datumsformat in CSV-Exporten

---

## Coding-Konventionen

```php
// Controller: immer abort_if für Berechtigungsprüfung
abort_if($note->lernender_id !== $lernender->lernender_id, 403);

// Flash-Messages nach jeder mutierenden Action
return redirect()->route('...')->with('success', 'Note erfasst.');
return redirect()->route('...')->with('error', 'Fehler beim Speichern.');

// DB-Queries: DB::table() oder Eloquent, NIE Raw-SQL ohne Binding
// Soft-Delete: ->whereNull('geloescht_am') bei allen Noten-Queries
// Timestamps: ->whereNull('geloescht_am'), 'erstellt_am', 'aktualisiert_am'

// Deutsche Variablennamen in Blade-Views konsistent:
// $lernender, $berufsbildner, $noten, $semester, $avgWeighted
```

```blade
{{-- Labels in Formularen --}}
<label class="text-xs uppercase tracking-widest text-muted font-medium">

{{-- Primär-Button Standard --}}
<button class="w-full h-12 rounded-xl bg-accent text-white font-semibold
               hover:opacity-90 active:scale-[0.97] transition-all duration-150
               inline-flex items-center justify-center gap-2">

{{-- Note-Badge Standard --}}
<span class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1
             rounded-xl font-bold text-sm [FARBKLASSE]">

{{-- Accordion Standard (np-details + np-chevron für JS-Toggle) --}}
<details class="np-details bg-card border border-border rounded-2xl shadow-sm">
  <summary class="cursor-pointer select-none px-4 py-3 flex items-center
                  justify-between list-none hover:bg-accent/5 transition-colors">
    <span class="np-chevron transition-transform duration-200">...</span>
```

---

## Häufige Befehle

```bash
# Build (nach CSS/JS Änderungen — PFLICHT)
npm run build

# Cache leeren + neu aufbauen
php artisan view:clear && php artisan view:cache
php artisan route:clear && php artisan config:clear
php artisan optimize

# Syntax-Check aller Controller
find app/Http/Controllers -name "*.php" | xargs php -l 2>&1 | grep -v "No syntax"

# Alle Route-Namen ausgeben
php artisan route:list --json 2>/dev/null | php -r "
  \$r = json_decode(file_get_contents('php://stdin'), true);
  foreach(\$r as \$x) if(\$x['name']) echo \$x['name'].PHP_EOL;
" | sort

# Hardcodierte Farben finden (sollte 0 Treffer geben)
grep -rn "bg-white\|text-black\|bg-gray-800" resources/views/ | grep -v ".git" | grep -v "drucken\|print"

# Kein ß in UI-Texten
grep -rn "ß" resources/views/ | grep -v ".git"

# Git Workflow
git add -A && git commit -m "Kurze präzise Message" && git push
```

---

## Bekannte Einschränkungen / Offene Punkte

- `php artisan test` schlägt fehl → vorbestehend, ignorieren (Test-DB ohne ALTER/DROP)
- `bemerkung`-Feld auf `lernende`-Tabelle fehlt → braucht manuelles `sudo mysql`
- Liquid-Glass SVG-Filter (echte Refraktion): nur Chromium → aktuell nur backdrop-filter
- public/build ist gitignored → VM ist gleichzeitig Server, build lokal auf VM

---

## Git-Workflow

```bash
# Immer auf feature/claude-fertigstellung arbeiten
git status
git add -A
git commit -m "Kategorie: Was wurde gemacht (präzise)"
git push  # Token in Remote-URL, funktioniert direkt
```

**Commit-Message Kategorien:** `Fix:` `Feat:` `GUI:` `Refactor:` `Docs:`
