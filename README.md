# Notenportal

Webbasiertes Noten-Verwaltungssystem für Lernende in der Berufslehre (Schweiz).
Entwickelt mit Laravel 11, MariaDB, Tailwind CSS.

---

## Überblick

Das Notenportal unterstützt drei Rollen:

| Rolle | Funktion |
|---|---|
| **Lernender** | Eigene Noten erfassen, bearbeiten, löschen; Kommentare schreiben; Durchschnitte je Fach/Modul einsehen |
| **Berufsbildner** | Noten der betreuten Lernenden einsehen; Noten als „gesehen" markieren; Kommentare schreiben |
| **Admin** | Alle Noten aller Lernenden einsehen; Benutzer verwalten; Betreuungen zuweisen; BMS/ABU-Tracks setzen |

---

## Technischer Stack

- **Backend**: Laravel 11, PHP 8.2+
- **Datenbank**: MariaDB (keine MySQL-Annahmen; CHECK-Constraints via `DB::statement`)
- **Frontend**: Tailwind CSS mit CSS-Custom-Properties-Theming (`--bg`, `--card`, `--text`, `--accent`, …)
- **Auth**: Breeze (Form-basiert), angepasste Spalten (`passwort_hash`, `benutzer_id`, …)

---

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Datenbank einrichten (als DB-Admin):
```sql
-- .env auf np_web konfigurieren, dann:
source database/setup_migrations_table.sql
```

Danach Migrations als bereits ausgeführt markieren (Tabellen existieren bereits):
```bash
php artisan migrate:status   # sollte alle grün zeigen
```

### Erster Admin-Benutzer

Den ersten Admin-Benutzer direkt in der DB anlegen:
```sql
INSERT INTO benutzer (benutzername, email, vorname, nachname, passwort_hash, aktiv, erstellt_am, aktualisiert_am)
VALUES ('admin', 'admin@example.com', 'Admin', 'User', '<bcrypt-hash>', 1, NOW(), NOW());

INSERT INTO benutzer_rollen (benutzer_id, rolle_id)
SELECT b.benutzer_id, r.rolle_id
FROM benutzer b, rollen r
WHERE b.benutzername = 'admin' AND r.name = 'Admin';
```

Danach können weitere Benutzer über `/admin/benutzer/create` angelegt werden.

---

## Datenbankschema (Übersicht)

### Benutzerverwaltung

```
benutzer          – Alle Nutzer (Lernende, BB, Admin)
benutzer_rollen   – n:m Verknüpfung (ein Nutzer kann mehrere Rollen haben)
rollen            – Admin | Berufsbildner | Lernender
lernende          – Lernender-Profil (lehrberuf_id, lehrbeginn)
berufsbildner     – Berufsbildner-Profil (benutzer_id)
betreuungen       – Welcher BB betreut welchen Lernenden (gueltig_von/bis)
lernender_tracks  – BMS/ABU-Track-Zuweisung pro Lernender
```

### Noten

```
noten               – Haupttabelle (note_wert, gewichtung_prozent, pruefungsdatum, …)
kategorien          – Prüfungstyp (z.B. Semesterprüfung, Erfahrungsnote)
faecher             – Schulfächer (abhängig von Track / Lehrberuf)
module              – ÜK-Module (mit modul_nummer, titel)
lehrberuf_module    – Welche Module gehören zu welchem Lehrberuf
modul_belegungen    – Belegung eines Moduls durch einen Lernenden
modul_note_gruppen  – Optionale Gruppierung innerhalb eines Moduls
semester            – Semesterliste (start_datum, end_datum, sortierung)
```

### Kommunikation

```
noten_gesehen     – Wer hat eine Note wann gesehen (viewer_benutzer_id, gesehen_am)
                    UNIQUE(note_id, viewer_benutzer_id); gesehen_am wird bei
                    erneuter Markierung aktualisiert (Upsert)
noten_kommentare  – Kommentare zu Noten (autor_benutzer_id, kommentar_text, erstellt_am)
                    Immutable – keine Bearbeitungsmöglichkeit
```

---

## Benutzerdefiniertes Auth-Setup

Das Standard-Laravel-Auth wurde für das Schweizer Namensschema angepasst:

| Standard Laravel | Notenportal |
|---|---|
| `users` | `benutzer` |
| `id` | `benutzer_id` |
| `created_at` | `erstellt_am` |
| `updated_at` | `aktualisiert_am` |
| `deleted_at` | `geloescht_am` |
| `password` | `passwort_hash` |

Das `User`-Modell überschreibt `getAuthPassword()` und `getAuthPasswordName()` entsprechend.

---

## Routen-Übersicht

| Methode | URL | Beschreibung |
|---|---|---|
| GET | `/noten` | Lernender: eigene Noten (Accordion, je Fach/Modul) |
| GET | `/noten/create` | Lernender: neue Note erfassen |
| POST | `/noten/{id}/kommentare` | Lernender + BB: Kommentar schreiben |
| POST | `/noten/{id}/gesehen` | Lernender: Note als gelesen markieren (AJAX/JSON) |
| GET | `/berufsbildner/lernende/{id}/noten` | BB: Noten eines betreuten Lernenden |
| POST | `/berufsbildner/lernende/{lid}/noten/{nid}/gesehen` | BB: Note als gesehen markieren |
| GET | `/admin/benutzer` | Admin: Benutzerliste |
| GET | `/admin/benutzer/create` | Admin: Neuen Benutzer anlegen |
| GET | `/admin/lernende/{id}/betreuung` | Admin: Betreuungen verwalten |
| GET | `/admin/lernende/{id}/tracks` | Admin: BMS/ABU-Tracks verwalten |

---

## Bekannte Einschränkungen / Offene Punkte

- **Passwort-Reset durch Admin**: Noch nicht implementiert. Passwörter müssen derzeit direkt in der DB zurückgesetzt werden (`bcrypt`-Hash).
- **Kein E-Mail-Versand**: Badges sind UI-only; keine Benachrichtigung bei neuen Kommentaren oder Gesehen-Markierungen.
- **Keine Bulk-Aktionen im Admin**: Benutzer müssen einzeln angelegt werden.

---

## Geplante Erweiterung: Bewertungsregeln (`bewertungsregeln`)

Die Tabelle `bewertungsregeln` ist im Datenbankschema vorhanden, wird aber von der Applikation noch **nicht ausgewertet**.

### Schema

```sql
CREATE TABLE bewertungsregeln (
    regel_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_typ        ENUM('GLOBAL','KATEGORIE','FACH','MODUL') NOT NULL,
    kategorie_id     INT UNSIGNED NULL,   -- nur wenn scope_typ = 'KATEGORIE'
    fach_id          INT UNSIGNED NULL,   -- nur wenn scope_typ = 'FACH'
    modul_id         INT UNSIGNED NULL,   -- nur wenn scope_typ = 'MODUL'
    grenzwert_note   DECIMAL(3,1) NOT NULL,  -- z.B. 4.0 = bestanden
    gueltig_ab       DATE NOT NULL,
    gueltig_bis      DATE NULL,
    erstellt_am      DATETIME NOT NULL,
    aktualisiert_am  DATETIME NOT NULL
);
```

### Geplante Semantik

Bewertungsregeln legen fest, ab welcher Note ein Prüfungsergebnis als „bestanden" gilt – auf verschiedenen Granularitätsstufen:

| `scope_typ` | Bedeutung |
|---|---|
| `GLOBAL` | Gilt für alle Noten (Default: 4.0 in CH) |
| `KATEGORIE` | Überschreibt für eine bestimmte Prüfungskategorie |
| `FACH` | Überschreibt für ein bestimmtes Schulfach |
| `MODUL` | Überschreibt für ein bestimmtes ÜK-Modul |

**Auflösungsreihenfolge** (spezifischste Regel gewinnt):
`MODUL` > `FACH` > `KATEGORIE` > `GLOBAL`

### Geplante UX-Integration

- Noten unterhalb des Grenzwerts werden rot hervorgehoben (aktuell fest auf 4.0/3.5 kodiert – durch DB-Abfrage ersetzen)
- Durchschnittswerte zeigen visuell an, ob der Lernende im grünen Bereich liegt
- Admin-Seite `/admin/bewertungsregeln` zur Verwaltung der Regeln

### Implementierungshinweise

1. `BewertungsregelService::geltenderGrenzwert(note_id)` – löst die Regel für eine konkrete Note auf (berücksichtigt `gueltig_ab`/`gueltig_bis` und `scope_typ`-Priorität)
2. `Note::scopeUnterGrenzwert()` – Eloquent-Scope für gefährdete Noten
3. Bestehende `NoteService::calcAverages()` kann erweitert werden, um einen „bestanden/nicht bestanden"-Status zurückzugeben
4. Views: Die Farbkodierung (`bg-green-100`, `bg-yellow-100`, `bg-red-100`) ist bereits in allen Notenansichten vorbereitet – die Schwellwerte müssen nur aus der DB kommen statt hardcodiert zu sein

---

## Entwicklungsnotizen

### Theming

CSS-Custom-Properties-basiertes Theming über Tailwind-Tokens:

```
--bg       Hintergrundfarbe (body)
--card     Kartenhintergrund
--text     Primärtext
--muted    Sekundärtext / Placeholder
--border   Rahmenfarbe
--input    Eingabefeldhintergrund
--accent   Primärfarbe (Buttons, Links, Badges)
--ring     Focus-Ring-Farbe
```

Alle Komponenten verwenden ausschliesslich diese Tokens – kein hartes `bg-white dark:bg-gray-800`.

### Migrations

Die Migrations wurden nachträglich erstellt (Tabellen existierten bereits). Sie sind in der `migrations`-Tabelle als ausgeführt markiert. `php artisan migrate:fresh` ist auf produktiven Instanzen **nicht** zu verwenden – die Migrations dienen als Dokumentation und für neue Dev-Umgebungen.

### Noten-Gesehen-Logik

Der „Neu"-Badge für den Berufsbildner erscheint wenn:
- Noch kein `noten_gesehen`-Eintrag für diese Note existiert, **oder**
- `note.erstellt_am > gesehen_am` (Note neuer als letzte Markierung), **oder**
- Ein Kommentar mit `erstellt_am > gesehen_am` existiert (neue Kommentare nach Markierung)

Beim Klick auf „Als gesehen markieren" wird `gesehen_am` via Upsert aktualisiert, sodass neu hinzugekommene Kommentare erneut den Badge auslösen können.
