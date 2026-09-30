# Notenportal – Kontextdossier für die Systemdokumentation

Stand 28.09.2026. Dieses Dossier ist die **einzige Faktenquelle** für die Systemdokumentation
im OneNote-Abschnitt «System – Notenportal». Alles, was hier nicht steht, ist unbekannt und
muss als Lücke markiert werden – nicht ergänzt, nicht geraten, nicht aus Erfahrung aufgefüllt.

---

## 0. Schreibregeln für die Dokumentation

| Regel | Vorgabe |
|---|---|
| Sprache | Deutsch, Schweizer Hochdeutsch |
| ß | Verboten. Durchgehend doppel-s: «Grösse», «ausser», «schliessen» |
| Anführungszeichen | Guillemets « » |
| Anrede | Unpersönlich oder Imperativ («Konto anlegen», «Danach prüfen»). Kein «Sie», kein «man sollte» |
| Form | Kurze Absätze (höchstens drei Zeilen), sonst Tabellen und nummerierte Schritte |
| Befehle, Pfade, Tabellennamen | Immer als Code ausgezeichnet |
| Verboten im Text | Entwicklernotizen, Vermutungen, Floskeln, Einleitungs- und Schlusssätze, Emojis |
| Lücken | Als `‹offen: was genau fehlt›` markieren und weiterschreiben |

---

## 1. Was das Notenportal ist

Webanwendung für die Schulnoten von Lernenden in der Schweizer Berufslehre. Lernende erfassen
ihre Noten und sehen daraus Zeugnisnote, Promotionsstand und Modulfortschritt. Berufsbildner
begleiten ihre Lernenden über eine Ampel mit Begründung. Admins richten Betrieb, Stammdaten
und Konten ein.

Ein einzelner Linux-Server: Apache liefert aus, PHP rechnet, MariaDB speichert. Kein
Cloud-Dienst, keine Client-Software, keine Lizenzkosten. Nach aussen geht nur SMTP für den
Mailversand und – falls Lernende einen Kalender abonnieren – das stündliche Lesen einer
iCal-Adresse.

**Technik:** Laravel 13 auf PHP 8.3, MariaDB 10.11, Blade mit Tailwind CSS 4, Alpine.js, Vite,
Apache 2.4 mit mod_php. Oberfläche deutsch, Routen und Code englisch.

---

## 2. Rollen und Berechtigungen

| Benutzer / Rolle | Bereich | Rechte |
|---|---|---|
| `np_web` | MariaDB | Zugang der Anwendung. Nur Daten: SELECT, INSERT, UPDATE, DELETE. Keine Schemaänderungen |
| `np_migrate` | MariaDB | Darf das Schema ändern. Wird ausschliesslich von `php artisan notenportal:migrate` benutzt |
| `www-data` | Apache, PHP, Cron | Führt das Portal aus. Gruppe aller Portaldateien, Verzeichnisse mit Setgid |
| `ubuntu` | Shell | Administration, Mitglied von `www-data`, hat `sudo`. Bewusst keine eigenen MariaDB-Rechte – darum immer `sudo mysql` |
| Rolle Admin | Portal | Alles: Konten, Stammdaten, Betrieb, Berichte, Noten anlegen und löschen |
| Rolle Berufsbildner | Portal | Nur aktiv betreute Lernende. Noten lesen, korrigieren, kommentieren – nicht anlegen, nicht löschen |
| Rolle Lernender | Portal | Nur eigene Daten |

Rollen hängen an der Tabelle `benutzer_rollen`, eine Person kann mehrere haben. In `benutzer`
gibt es **keine** Rollenspalte. Wer welche Lernenden sieht, entscheidet
`Lernender::sichtbarFuer()` an einer einzigen Stelle im Code.

---

## 3. Infrastruktur

| System | Bezeichnung | Adresse | Funktion | Bemerkung |
|---|---|---|---|---|
| Server | srv-lab-dva-001, VM, Ubuntu 24.04 LTS | `172.26.14.101/24` (ens160) | Trägt alles: Web, Datenbank, Dateien, Cron | ICT-LAB, geschlossenes Netz |
| Webserver | Apache 2.4 | :80 und :443 | Liefert das Portal aus | mpm_prefork + mod_php; `notenportal.conf`, `notenportal-ssl.conf` |
| Laufzeit | PHP 8.3, Laravel 13 | – | Rechenkern für Noten | Distro-Pakete, opcache aktiv |
| Datenbank | MariaDB 10.11 | `127.0.0.1:3306` | Datenbank `notenportal` | Kein Listener nach aussen |
| Dateiablage | `storage/app/private` | – | Dokumente, Modulunterlagen, Sicherungen | Nie direkt über den Webserver, Auslieferung nur über Controller |

Auf derselben VM laufen zusätzlich eine Testinstanz (Port 8082) für Installations- und
Updatetests und eine Demoinstanz mit erfundenen Daten für Schulung und Screenshots. Beide
gehören nicht zum Produktivbetrieb und haben eigene Datenbanken.

**Cloud-Dienste:** keine. Das Portal läuft vollständig on-premise.

**HTTPS:** eigene Lab-Zertifizierungsstelle, kein öffentlicher DNS-Name und darum kein
Let's-Encrypt-Zertifikat. Serverzertifikat läuft am 14.12.2028 ab. Die zu verteilende Datei
heisst `ca.crt` und liegt unter `/etc/ssl/notenportal/`; `ca.key` bleibt auf dem Server.

**Betriebsregel:** `/var/www/notenportal` ist zugleich Arbeitskopie und Auslieferungsstand – ein
Build ist sofort produktiv. Darum gilt die Reihenfolge Dump, Code, Migration, Caches, und
Migrationen laufen zuerst gegen die Kopie `notenportal_probe`.

---

## 4. Installation Server

**Voraussetzungen:** Ubuntu 24.04 LTS oder neuer, erprobt bis 26.04 (frisch genügt), ein Benutzer
mit `sudo`, rund 2 GB Arbeitsspeicher, Ports 80 und 443 frei (sonst `--port` bzw. `--https-port`),
Internetzugang während der Installation.
Apache, MariaDB, PHP, Node und Composer installiert das Skript selbst.

```bash
sudo apt update && sudo apt install -y git
sudo install -d -o "$USER" -g "$USER" /var/www/notenportal
git clone https://github.com/magic-usb-c/notenportal.git /var/www/notenportal
cd /var/www/notenportal
sudo ./install.sh
```

Warum `/var/www`: Ubuntu legt Home-Verzeichnisse so an, dass Apache sie nicht betreten darf –
jede Seite käme als «403 Forbidden». Liegt das Portal falsch, bricht `install.sh` sofort ab und
nennt den Befehl zum Verschieben.

Am Ende nennt das Skript Adresse, E-Mail des ersten Admin-Kontos und ein Startpasswort. Dieses
Passwort erscheint genau einmal.

**Was das Skript erledigt:**

| Schritt | Inhalt |
|---|---|
| Pakete | Apache, MariaDB, PHP ≥ 8.3 samt Erweiterungen, Node 22, Composer |
| Datenbank | Datenbank plus zwei Benutzer: einer nur für Daten, einer für Schemaänderungen |
| Konfiguration | `.env` mit Zufallspasswörtern und Produktionswerten, Anwendungsschlüssel |
| Schema | Migrationen, Grundstammdaten (Rollen, Kategorien) |
| Konto | Erstes Admin-Konto mit Startpasswort |
| Oberfläche | `npm ci` und Build |
| HTTPS | Eigene Zertifizierungsstelle, Serverzertifikat für alle Namen und Adressen der Maschine, `APP_URL` und Secure-Cookie |
| Webserver | VirtualHosts für HTTP und HTTPS, Umleitung von HTTP, ufw-Freigabe falls ufw läuft |
| Automatik | `/etc/cron.d/notenportal` für Sicherung, Mailversand, Benachrichtigungen |
| Sicherung | Bei einer Neuinstallation gleich die erste Sicherung |
| Abschluss | Dateirechte, Schlusstest gegen `/login` über HTTPS samt Prüfung der Zertifikatskette |

**Optionen:**

```bash
sudo ./install.sh --help                        # Liste aller Optionen
sudo ./install.sh --host notenportal.example.ch # Servername im vhost und im Zertifikat
sudo ./install.sh --ohne-https                  # nur HTTP, ohne Zertifikat
sudo ./install.sh --https-port 8443             # HTTPS auf einem anderen Port
sudo ./install.sh --port 8082 --db np_test      # zweite Instanz daneben
sudo ./install.sh --ohne-firewall               # keine ufw-Freigabe
sudo ./install.sh --neues-admin-passwort        # neues Startpasswort
```

Ein erneuter Lauf im selben Verzeichnis ist ein Update: Pakete, Abhängigkeiten, Build und
Migrationen werden nachgezogen. `.env`, Webserver-Konfiguration und Konten bleiben.

**Geführte Einrichtung im Browser, acht Schritte:**

| # | Schritt | Was hier passiert |
|---|---|---|
| 1 | Betrieb | Eigenes Konto, Betriebsname, Notengrenzen, Rundung, Fristen |
| 2 | Kategorien | Schule, ÜK, Betrieb mit Rundung, Gewicht und Promotionsregeln |
| 3 | Semester | Generator mit Vorschau erzeugt den ganzen Semesterplan |
| 4 | Lehrberufe und Fächer | Lehrberufe sowie BMS- und ABU-Fächer sind vorausgewählt |
| 5 | Module | Module je Lernort; hier lässt sich auch der Modulkatalog einlesen |
| 6 | Personen | Berufsbildner, Admins und Lernende zeilenweise, Startpasswörter zum Drucken |
| 7 | E-Mail | SMTP-Server, Absender, Passwort, Testmail |
| 8 | Abschluss | Übersicht, danach ist das Portal betriebsbereit |

Die Einrichtung bleibt danach erreichbar (Betrieb → «Einrichtung») und ist der Weg, später
einen ganzen Jahrgang anzulegen: bis 100 Lernende und 50 Berufsbildner je Vorgang.

**Was von Hand bleibt:** DNS-Eintrag für den Namen auf dem zuständigen DNS-Server (das Skript
nennt am Schluss den nötigen A-Record), Import der Zertifizierungsstelle auf den Clients
(`http://<name>/lab-ca.crt`), SMTP unter Admin → Betrieb → E-Mail eintragen (Erstinstallation
schreibt Mails nur ins Log), Ziel für die Kopie ausser Haus, Zeitzonen der Datenbank
(`mysql_tzinfo_to_sql /usr/share/zoneinfo | sudo mysql mysql`), Firewall auf die berechtigten
Netze einschränken.

**Kontrolle:** `php artisan notenportal:bereitschaft` – rein lesend, prüft Umgebung (`APP_ENV`,
`APP_DEBUG`, `APP_URL`, Secure-Cookie), Mail-Umleitung, Kopie ausser Haus, Alter der letzten
Sicherung, fehlgeschlagene Jobs und Mails, Kalenderabgleich und offene Migrationen. Status je
Punkt ok, warnung oder fehler; Exit-Code 1 bei Fehler; `--json` für Skripte.

---

## 5. Zugang für Benutzer (Client)

Es gibt keine Client-Software.

| Punkt | Wert |
|---|---|
| Software | Keine Installation, kein Agent, kein Plug-in |
| Browser | Chrome, Edge, Firefox oder Safari in aktueller Version, JavaScript aktiv |
| Bildschirm | Ab 390 Pixel Breite bedienbar |
| Netz | Zugang zum Lab-Netz über VPN, Adresse `https://notenportal`; HTTP leitet um |
| Zugang | E-Mail und Startpasswort vom Admin; beim ersten Login ist ein eigenes Passwort Pflicht (mindestens 8 Zeichen) |

**PWA:** Das Portal lässt sich als App auf den Startbildschirm legen. Windows Chrome/Edge:
Adresszeile → «Installieren». Android: Menü → «Zum Startbildschirm hinzufügen». iOS Safari:
Teilen → «Zum Home-Bildschirm». Ohne Verbindung erscheint eine Offline-Seite;
zwischengespeichert werden nur Programmdateien und Symbole, nie Noten oder Personendaten. Beim
Abmelden leert die App ihren Zwischenspeicher.

**Lab-CA importieren:** Herunterladen unter `http://<name>/lab-ca.crt` – bewusst über HTTP, weil
der Abruf über HTTPS vor dem Import genau die Warnung auslösen würde, die er behebt. Windows
Doppelklick → «Lokaler Computer» → «Vertrauenswürdige Stammzertifizierungsstellen». macOS
Schlüsselbundverwaltung → System → «Immer vertrauen». Firefox hat einen eigenen Speicher:
Einstellungen → Zertifikate → Zertifizierungsstellen → Importieren. Ohne Import ist die Verbindung
verschlüsselt, aber ungeprüft – der Browser warnt bei jedem Besuch.

**Kalenderabo:** Link aus Einstellungen → Kalender, enthält ein persönliches Token und lässt
sich dort neu erzeugen. Outlook: Kalender hinzufügen → «Aus dem Internet abonnieren». Google:
Weitere Kalender → «Per URL». Apple: Ablage → «Neues Kalenderabonnement». Der Link ist ein
Schlüssel – wer ihn hat, sieht die Termine.

---

## 6. Datenbank

MariaDB 10.11, Datenbank `notenportal`, **44 Tabellen**, durchgehend deutsch benannt.

| Punkt | Wert |
|---|---|
| Server | MariaDB 10.11, nur `127.0.0.1` |
| Zeichensatz | utf8mb4, Sortierung `utf8mb4_unicode_ci` |
| Benutzer | `np_web` nur Daten, `np_migrate` nur Schema |
| Nebendatenbanken | `notenportal_probe` (Migrationstest), `notenportal_demo` (Schulung), vier `_test`-Datenbanken (PHPUnit, werden je Lauf neu aufgebaut) |
| Zeitstempel | Immer `erstellt_am` und `aktualisiert_am`, Sitzungszeitzone `Europe/Zurich` |
| Löschen | Soft-Delete über `geloescht_am` – Daten verschwinden aus der Anwendung, bleiben nachvollziehbar |
| Integrität | Fremdschlüssel, zusammengesetzte FKs und CHECK-Constraints in der Datenbank selbst, nicht nur im Code |

### Beziehungen

```
  benutzer ──┬── benutzer_rollen ── rollen     (Admin · Berufsbildner · Lernender)
             │
             ├── berufsbildner ── betreuungen ──┐
             │                                  │
             └── lernende ─────────────────────┘
                    │  lehrberuf_id
                    ▼
                lehrberufe ──┬── lehrberuf_faecher ── faecher ── kategorien
                             └── lehrberuf_module  ── module

                lernende ──┬── noten ────────── semester
                           │     │  kategorie_id (abgeleitet)
                           │     └─ fach_id  X O D E R  modul_belegung_id
                           ├── modul_belegungen ── module
                           ├── pruefungen (fach_id XODER modul_id)
                           ├── ziele
                           └── dokumente
```

Kernregel: Eine Note hängt entweder an einem Fach oder an einer Modulbelegung – nie an beidem
und nie an keinem. Ein CHECK in der Datenbank erzwingt das.

### Tabellen nach Gruppen

**Personen und Rechte**

| Tabelle | Inhalt |
|---|---|
| `benutzer` | Konto: E-Mail (= Login), Name, Passwort-Hash, aktiv, Darstellung, Sprache, Kalender-Token |
| `rollen`, `benutzer_rollen` | Admin, Berufsbildner, Lernender. Mehrfachrollen möglich |
| `lernende` | Lehrverhältnis: Lehrberuf, Lehrbeginn, Lehrende, interne Bemerkung (nur BB und Admin) |
| `berufsbildner` | Verweis aufs Konto |
| `betreuungen` | Wer betreut wen, von wann bis wann. Nur eine aktive Betreuung gibt Sicht auf Daten |
| `lernender_tracks` | BMS- oder ABU-Abschnitt mit Start- und Endsemester |

**Stammdaten**

| Tabelle | Inhalt |
|---|---|
| `lehrberufe` | Kürzel, Name, SBFI-Berufsnummer, Bildungsstufe, BiVo-Jahr |
| `kategorien` | Lernorte bzw. Notengruppen mit Rundung, Gewicht im Gesamtschnitt, Promotionsregeln |
| `faecher` | Fach mit Kategorie und optionalem Track (BMS, ABU) |
| `module` | Gemeinsame Stammdaten, ein Datensatz je Modul, von jeder angemeldeten Person anlegbar und ergänzbar. Optional Katalogfelder |
| `lehrberuf_faecher`, `lehrberuf_module` | Welcher Beruf hat welche Fächer und Module – beim Modul zusätzlich Lernort, Pflichtgrad, empfohlenes Semester |
| `modul_handlungsziele`, `modul_lbv_elemente` | Handlungsziele und Leistungsbeurteilungsvorgaben aus dem Katalog, Referenz ohne Rechenweg |
| `modul_dokumente` | Unterlagen am Modul, nicht an einer Person |
| `semester` | Bezeichnung, Start, Ende, Sortierung |
| `einstellungen` | Schlüssel/Wert: Betriebsname, Notengrenzen, Rundung, Fristen, Theme, Mail-Umleitung |

**Noten und Leistung**

| Tabelle | Inhalt |
|---|---|
| `noten` | Zentrale Tabelle: Lernender, Semester, Kategorie (abgeleitet), Fach oder Modulbelegung, Titel, Prüfungsdatum, Note 1–6, Gewichtung in Prozent, erfasst von, geändert von |
| `modul_belegungen` | Ein Lernender belegt ein Modul. Eine Wiederholung ist eine neue Belegung; gerechnet wird nur die jüngste |
| `ziele` | Wunschnote auf Ebene Gesamt, Kategorie, Fach oder Modul |
| `noten_gesehen` | Wer hat welche Note wann zur Kenntnis genommen |
| `noten_kommentare` | Kommentare von Berufsbildnern zu einzelnen Noten |

**Prüfungen, Kalender, Dokumente**

| Tabelle | Inhalt |
|---|---|
| `pruefungen` | Geplante Prüfung oder Abgabe: Datum, Uhrzeit, Dauer, Art, Hilfsmittel, Stoff, Raum, Lehrperson, Gewichtung. Ist die Note erfasst, zeigt `note_id` darauf |
| `calendar_feeds` | Bis zu fünf iCal-Abos je Benutzer, Adresse verschlüsselt |
| `calendar_events` | Lektionen und Termine aus dem Abo |
| `dokumente` | Zeugnisse, Notenlisten, Anhänge einer Prüfung. Datei privat auf der Platte, in der Tabelle Pfad, Prüfsumme, Grösse |

**Mail, Benachrichtigung, System**

| Tabelle | Inhalt |
|---|---|
| `notification_policies` | Was der Admin je Anlass vorgibt: ein oder aus, verpflichtend, Standardfrequenz |
| `notification_preferences` | Was die einzelne Person daraus gewählt hat |
| `notification_digest_items`, `notification_marks` | Zwischenspeicher der Tageszusammenfassung, Merker gegen Doppelversand |
| `mail_log` | Versandprotokoll mit Status, Fehler, «Erneut senden» |
| `feedback`, `feedback_anhaenge`, `feedback_stimmen` | Meldungen aus dem Portal samt Anhängen und Zustimmung |
| `aktivitaeten` | Sicherheitsrelevante Aktionen mit Zeit, Person, Ziel, IP. 365 Tage. Geheimnisse werden nie protokolliert |
| `password_reset_tokens`, `password_invite_tokens` | Einmal-Links für «Passwort vergessen» (60 Minuten) und Kontoeröffnung (7 Tage) |
| `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks`, `migrations` | Laravel-Technik |

### Regeln, die man kennen muss

- Die **Kategorie wird abgeleitet**, nie eingetippt: bei einer Fachnote aus
  `faecher.kategorie_id`, bei einer Modulnote aus `lehrberuf_module.kategorie_id` (dem Lernort).
  Führt jemand ein Modul, das sein Lehrberuf nicht vorsieht, gilt die im Beruf häufigste
  Kategorie.
- **Kein Durchschnitt steht in der Datenbank.** Jeder Schnitt wird bei Bedarf von
  `App\Services\Auswertung` gerechnet, nie in SQL, nie im View. Damit ändert eine geänderte
  Rundungsregel rückwirkend alles Richtige.
- **Die Modulnummer ist fest.** An ihr hängen die Noten aller Personen; sie ändert nur ein Admin.
- **Die Lernenden-ID kommt aus der Sitzung**, nie aus dem Request.
- **Semester:** im Kontext einer Person die persönliche Nummer («3. Semester»), ohne Person der
  neutrale Name («HS 26/27»).

### Schema ändern

```bash
# 1. Dump und Git-Tag
sudo mysqldump --single-transaction notenportal \
  > ~/db-backups/notenportal-$(date +%Y%m%d-%H%M)-vor-x.sql

# 2. Erst gegen die Kopie, mit Rollback-Test
sudo mysql notenportal_probe < ~/db-backups/notenportal-....sql
DB_DATABASE=notenportal_probe php artisan migrate --force

# 3. Dann erst gegen den Betrieb
php artisan notenportal:migrate
```

`php artisan migrate` allein genügt nicht: Der Web-Benutzer darf das Schema gar nicht ändern.
`notenportal:migrate` nimmt dafür `np_migrate` aus der `.env`. Jede Schemaänderung gehört ins
Änderungsprotokoll in `docs/betrieb.md` – mit Dump-Name, Tag und Ergebnis.

---

## 7. Betrieb: Update, Caches, Rechte, Automatik

**Update einspielen** (Reihenfolge zählt – der Build ist sofort live):

```bash
cd /var/www/notenportal
sudo mysqldump --single-transaction notenportal \
  > ~/db-backups/notenportal-$(date +%Y%m%d-%H%M)-vor-update.sql
git pull --ff-only
npm ci && npm run build
php artisan notenportal:migrate
sudo -u www-data php artisan optimize
php artisan notenportal:bereitschaft
```

Alternativ erledigt `sudo ./install.sh` im selben Verzeichnis dasselbe und zieht zusätzlich
Systempakete nach.

**Caches:** `sudo -u www-data php artisan optimize` baut Config-, Routen- und View-Cache (nach
jedem Update). `sudo -u www-data php artisan optimize:clear` leert alles (nach Änderungen an
`.env` oder wenn eine Einstellung nicht greift). Immer als `www-data` – als `ubuntu`
kompilierte Views gehören dem falschen Benutzer, Apache scheitert danach mit
«touch(): Utime failed» und liefert HTTP 500.

**Dateirechte** (Symptom bei kaputten Rechten: HTTP 500 ohne Eintrag im Laravel-Log):

```bash
cd /var/www/notenportal
find . -path './.git' -prune -o -type d -user ubuntu -exec chmod u+rwx,g+rxs {} \;
find . -path './.git' -prune -o -type f -user ubuntu -exec chmod u+rw,g+r {} +
find . -path './.git' -prune -o -user ubuntu ! -group www-data -exec chgrp www-data {} +
```

**Was automatisch läuft.** Ein Cron-Eintrag (`/etc/cron.d/notenportal`) ruft jede Minute den
Laravel-Scheduler auf; Kontrolle mit `php artisan schedule:list`.

| Zeit | Aufgabe | Befehl |
|---|---|---|
| 02:30 | Sicherung, danach Kopie ausser Haus | `notenportal:sicherung` |
| 03:15 | Aktivitätsprotokoll aufräumen (älter als 365 Tage) | `notenportal:aktivitaeten-aufraeumen` |
| 06:30 | Benachrichtigungen prüfen | `notifications:check` |
| 18:00 | Tageszusammenfassungen versenden | `notifications:digest` |
| :17 stündlich | Abonnierte Kalender abgleichen | `calendar:sync` |
| jede Minute | Mailwarteschlange abarbeiten | `queue:work --stop-when-empty --max-time=50 --tries=3` |

**Logs:** Anwendung `storage/logs/laravel-JJJJ-MM-TT.log` (täglich, Level `info`), Webserver
`/var/log/apache2/error.log` und `access.log`, Mailversand im Portal unter Admin →
Versandprotokoll, sicherheitsrelevante Aktionen im Portal unter Admin → Aktivitätsprotokoll
(365 Tage).

**Regelmässig prüfen:** täglich Admin-Dashboard «Handlungsbedarf»; wöchentlich Sicherung
herunterladen und stichprobenweise öffnen sowie `df -h`; monatlich
`sudo apt update && sudo apt upgrade` und danach `notenportal:bereitschaft`; halbjährlich
Restore-Test mit Eintrag ins Restorelog; bis 14.12.2028 Serverzertifikat erneuern.

---

## 8. Monitoring

Zwei Ebenen: PRTG überwacht den Server von aussen, das Portal meldet seine eigenen Störungen
selbst.

| Host / Service | Sensor | Schwelle und Zweck |
|---|---|---|
| srv-lab-dva-001 | Ping | Grundverfügbarkeit der VM |
| srv-lab-dva-001 | CPU, RAM, Disk Free | Warnung bei unter 15 % freiem Platz |
| Notenportal Web | HTTP `/login` | Erwartet 200 und den Text «Anmelden». Bester einzelner Sensor: prüft Apache, PHP und Datenbank in einem Zug |
| Notenportal Web | HTTPS Zertifikat | Warnung 30 Tage vor Ablauf, aktuell 14.12.2028 |
| srv-lab-dva-001 | Dienst `apache2` | SSH- oder SNMP-Sensor |
| srv-lab-dva-001 | Dienst `mariadb` | SSH- oder SNMP-Sensor |
| Notenportal Sicherung | Datei-Alter `storage/app/private/sicherungen` | Fehler, wenn die neuste Sicherung älter als 48 Stunden ist |

Diese Sensoren sind **noch nicht eingerichtet** – die Tabelle ist die Vorgabe. Sensornamen und
Intervalle trägt nach, wer sie aufbaut.

`php artisan notenportal:bereitschaft --json` taugt direkt als PRTG-Sensor «Script v2» über SSH
(Exit-Code 1 bei Fehler).

Das Admin-Dashboard sammelt unter «Handlungsbedarf»: fehlgeschlagene Jobs, fehlgeschlagene
Mails der letzten sieben Tage, Kalenderabgleich-Fehler, fehlende Sicherung, Lernende ohne Track
oder Betreuung, offene Meldungen. Jede Zeile führt mit «Beheben» zur Ursache.

---

## 9. Backup und Restore

Eine Sicherung enthält beides – Datenbank und Dateien der Lernenden. Nur zusammen ergeben sie
ein wiederherstellbares Portal.

| Art | Zeit | Inhalt | Aufbewahrung | Ort |
|---|---|---|---|---|
| Automatisch | täglich 02:30 | ZIP mit `datenbank.sql`, `dateien/lernende/…`, `LIESMICH.txt` | die 14 neusten | `storage/app/private/sicherungen/` |
| Kopie ausser Haus | nach jeder Sicherung | dieselben ZIP-Dateien | Ziel bestimmt | Ordner oder SSH-Server, per rsync |
| Auf Knopfdruck | bei Bedarf | wie automatisch | zählt zu den 14 | Admin → Betrieb → «Jetzt sichern» |
| Manueller Dump | vor jedem Eingriff | nur Datenbank | von Hand | `~/db-backups/` |
| VM-Snapshot | vor grossen Änderungen | ganze Maschine | von Hand | Virtualisierung |

Ist die neuste Sicherung älter als zwei Tage oder ist eine fehlgeschlagen, erscheint im Portal
und im Dashboard eine Warnung.

**Manueller Dump** – Grösse immer kontrollieren. Am 09.09.2026 war ein Dump 875 Byte gross, nur
der Kopf, keine Daten. Ein Dump unter 50 KB ist bei diesem Portal ein Fehlschlag.

**Wiederherstellen:**

```bash
# 1. Sicherung auspacken
unzip notenportal-JJJJMMTT-HHMMSS.zip -d /tmp/wiederherstellung

# 2. Aktuellen Stand wegsichern – auch einen kaputten
sudo mysqldump --single-transaction notenportal \
  > ~/db-backups/notenportal-vor-wiederherstellung.sql

# 3. Datenbank einspielen
sudo mysql notenportal < /tmp/wiederherstellung/datenbank.sql

# 4. Dateien zurückspielen
rsync -a /tmp/wiederherstellung/dateien/lernende/ \
  /var/www/notenportal/storage/app/private/lernende/

# 5. Rechte und Caches
sudo chgrp -R www-data storage/app/private
sudo chmod -R g+rwX storage/app/private
sudo -u www-data php artisan optimize:clear

# 6. Kontrolle
php artisan notenportal:bereitschaft
```

Danach `/login` aufrufen, anmelden und stichprobenweise prüfen, ob Noten, Dokumente und
Prüfungen da sind.

**Nur einzelne Daten zurückholen:** Dump in `notenportal_probe` einspielen und dort abgreifen.

**Restorelog (bisherige Einträge):**

| Art | Datum | Ergebnis | Durchgeführt | Bemerkung |
|---|---|---|---|---|
| Restore-Test (DB + Dateien) | 11.09.2026 | Erfolg | David von Allmen | ZIP `notenportal-20260911-113535.zip` in `notenportal_probe`: 38 Tabellen, Zeilenzahlen identisch mit Prod (ausser `cache_locks`), Dokumente 2 = 2 |
| Wiederherstellung nach Migrationsabbruch | 10.09.2026 | Erfolg | David von Allmen | Halbmigrierten Stand gesichert, `notenportal` aus dem Dump von 13:53 neu aufgebaut, Migration korrigiert und erneut ausgeführt |

---

## 10. Störungsbehebung

| Symptom | Zuerst prüfen |
|---|---|
| HTTP 500, nichts im Laravel-Log | Dateirechte, dann `/var/log/apache2/error.log` |
| HTTP 500 mit Log-Eintrag | `tail -50 storage/logs/laravel-$(date +%Y-%m-%d).log` |
| «touch(): Utime failed» | Caches als falscher Benutzer gebaut: `sudo -u www-data php artisan optimize:clear`, danach `optimize` |
| Einstellung greift nicht | `sudo -u www-data php artisan optimize:clear` |
| Seite weiss oder alte Version | `npm ci && npm run build` nachziehen – der Build ist sofort live |
| Keine Mails | Admin → Betrieb → E-Mail: Umleitung noch gesetzt? Dann Versandprotokoll, dann `php artisan queue:failed` |
| Kalender bringt nichts | Einstellungen → Kalender, Spalte Status und Fehler. Antwortet die Quelle mit 304, ist alles in Ordnung |
| Sicherung fehlt oder ist alt | Admin → Betrieb → Sicherungen, dann `php artisan schedule:list` und `/var/log/syslog` |
| Tests brechen mit `UnableToCreateDirectory` ab | `sudo chmod -R 2775 storage/framework/testing` |
| Migration abgebrochen | Nicht erneut ausführen. Stand sichern, Datenbank aus dem Dump vor dem Eingriff neu aufbauen, Migration korrigieren |
| Zertifikatswarnung im Browser | Lab-CA auf dem Client fehlt |

Eskalation: Portal nicht erreichbar oder Datenverlust vermutet – nichts überschreiben, sofort
einen Dump des Ist-Zustands ziehen, Systemtechnik beiziehen. Der kaputte Stand ist oft die
einzige Quelle für die Ursache.

---

## 11. Funktionsumfang je Rolle (Stoff für die Howto-Seiten)

### Navigation

- **Lernender:** Übersicht · Noten · Agenda · Rechner · Module · Dokumente
- **Berufsbildner:** Übersicht · Lernende · Prüfungstermine · Module
- **Admin:** Übersicht · Lernende · Prüfungstermine · Personen · Stammdaten · Berichte · Feedback

Für alle: **Ctrl + K** oder **/** Befehlspalette, **n** neue Note, **?** Übersicht aller
Tastenkürzel, **g + Buchstabe**
Sprung auf eine Seite. Oben rechts: Feedback melden (Sprechblase), Hell/Dunkel, Benutzermenü.

### Lernende

- **Erster Start:** anmelden mit E-Mail und Startpasswort, sofort eigenes Passwort setzen
  (mindestens 8 Zeichen).
- **Übersicht:** Gesamt-, Semester- und Kategorienoten, daneben «Als Nächstes» (überfällige
  Prüfungen ohne Note, neue Kommentare des Berufsbildners, fehlende Module), unten Verlauf und
  letzte Noten.
- **Note erfassen** (Noten → «Note erfassen» oder Taste `n`): Note (Viertelnoten erlaubt), Fach
  oder Modul, Prüfungsdatum (daraus bestimmt das Portal das Semester), Gewichtung (100 % normal,
  25 % und 50 % als Knopf), Titel freiwillig. Die Kategorie wird nicht gefragt.
- **Noten ansehen:** je Semester Kategorie → Fach oder Modul mit Zeugnisnote, Schnitt vor der
  Rundung, Promotionsstand. Klick auf eine Zeile öffnet die einzelnen Noten. Unten die
  Zeugnisübersicht über die ganze Lehrzeit als Farbraster.
- **Import:** Excel, ODS, CSV oder PDF, auch das Schulnetz-PDF «Aktuelle Noten». Das Portal
  erkennt Spalten, ordnet Fach oder Modul zu, markiert Dubletten; in der Vorschau korrigierbar.
  CSV-Vorlage auf derselben Seite.
- **Rechner:** vorwärts (angenommene Noten eintragen, Wirkung sehen) und rückwärts (Ziel
  eintragen, nötige Note ablesen). Nichts wird gespeichert.
- **Prüfungen:** Agenda → «Prüfung planen» mit Fach oder Modul, Datum, Gewichtung, optional
  Stoff und Hilfsmittel. Nach dem Termin erscheint sie unter «Als Nächstes»; «Note erfassen»
  übernimmt Fach, Datum und Gewichtung. Die Note hängt danach an der Prüfung.
- **Schulkalender:** Einstellungen → Kalender → «Kalender hinzufügen», iCal-Adresse aus
  Schulnetz, Nextcloud, Outlook oder Google. Stündlicher Abgleich. Wer ein übernommenes Feld
  selbst ändert, sperrt genau dieses Feld; «Wieder vom Kalender übernehmen» hebt die Sperre auf.
  Absagen der Schule kommen immer durch. Geschrieben wird nie etwas zurück.
- **Module:** gemeinsame Liste, ein Datensatz je Modul. Fehlendes Modul selbst anlegen
  (Nummer, Titel, Beschreibung, Handlungsziele). Unterlagen bis 20 MB hängen am Modul, nicht an
  einer Person. «Zu meinen Modulen hinzufügen» stellt ein Modul in die eigene Notenerfassung.
  Modul wiederholen: laufende Belegung schliessen, ab der ersten neuen Note zählt nur der neue
  Versuch.
- **Dokumente:** Zeugnisse, Notenlisten und Sonstiges bis 10 MB, nach Art und Semester. Ein
  Zeugnis lässt sich gegen die berechneten Zeugnisnoten prüfen («Abgleichen» zeigt je Fach
  stimmt, abweichend oder fehlt).
- **Einstellungen:** Profil (Name, E-Mail, Passwort; Thema, Akzentfarbe, Schriftgrösse, Dichte,
  Diagrammfarben auch farbenblindsicher, Bewegungen reduzieren), Benachrichtigungen (je Anlass
  sofort, Tageszusammenfassung um 18:00 oder nie), Kalender, Daten (alles als ZIP, Art. 25 DSG).
- **Notenblatt:** Zeugnisnoten je Semester, Prüfungen, Promotion, Unterschriftenzeilen,
  druckfertig. Daneben CSV-Export der Rohnoten.

### Berufsbildner

- **Ampel auf der Übersicht:** *kritisch* = Promotion gefährdet, Semesterschnitt zu tief oder
  mehrere ungenügende Zeugnisnoten. *beobachten* = Einbruch, Rückgang, fehlende Noten oder
  längere Inaktivität. Ohne Markierung = im Rahmen. Die Gründe stehen als Text daneben, etwa
  «Semesterschnitt 3.9 · 2 ungenügende Noten · Französisch 4.0 → 3.5 · 3 Noten fehlen». Die
  Spalte «Neue Noten» zählt, was seit dem letzten Blick dazukam.
- **Cockpit einer Person:** Stand, Verlauf, Zeugnisraster, Ziele, Prüfungen auf einer Seite.
- **Noten korrigieren und kommentieren:** Bearbeiten lässt «geändert von» sichtbar. Der
  Kommentar erscheint beim Lernenden unter «Als Nächstes». «Als gesehen markieren» nimmt die
  Note aus «Neue Noten». Berufsbildner können Noten **nicht anlegen und nicht löschen**.
- **Prüfungstermine:** kommende 7, 30 oder 90 Tage und vergangene ohne Note, nach Woche und
  Monat gruppiert, filterbar nach Person.
- **Lernende verwalten:** «+ Lernende» (Name, E-Mail, Lehrberuf, Lehrbeginn), Startpasswort oder
  Einladungslink (7 Tage). Betreuung mit Gültigkeitszeitraum – endet sie, endet die Sicht auf
  die Daten. Track (BMS oder ABU) mit Start- und Endsemester, sonst fehlen die Fächer. Bemerkung
  im Cockpit ist intern.
- **Empfohlene Benachrichtigungen:** Tageszusammenfassung für neue Noten und Korrekturen, sofort
  für kritische Fälle und fehlende Noten nach einer Prüfung.

### Admin

- **Dashboard:** Kennzahlen, «Handlungsbedarf» mit direktem «Beheben», Last je Berufsbildner,
  erfasste Noten pro Woche.
- **Personen anlegen:** einzeln über Personen → Benutzer → «Benutzer anlegen»; einzelner
  Lernender über Lernende → «+ Lernende»; ganzer Jahrgang über Betrieb → Einrichtung → Personen
  (bis 100 Lernende und 50 Berufsbildner je Vorgang, Startpasswörter zum Ausdrucken). Zugang weg:
  «Passwort zurücksetzen» schickt einen Link, 7 Tage gültig. Person verlässt den Betrieb: Konto
  auf inaktiv setzen, Anmeldung sofort gesperrt, Daten bleiben für Auswertungen erhalten.
  Startpasswörter erscheinen genau einmal und erzwingen beim ersten Login einen Wechsel.
- **Stammdaten:** Lehrberufe (Beruf mit Fächern und Modulen, Lernort, Pflicht, empfohlenes
  Semester – hier entscheidet sich, was Lernende zur Auswahl haben), Fächer (Kategorie und
  optionaler Track), Kategorien (Rundung, Gewicht, Promotionsregeln – Änderungen wirken
  rückwirkend, das ist gewollt), Semester (nur leere löschbar), Module (Nummer ändert nur ein
  Admin).
- **Modulkatalog einlesen:** Datei besorgen (eine laufende Instanz liefert den Stand per Knopf
  «Katalog herunterladen» oder mit `php artisan notenportal:modulkatalog-export`), Stammdaten →
  Module → «Katalog einlesen», Vorschau prüfen (Zahlen, Konflikte, neue Lehrberufe; gilt nur
  einmal), übernehmen. Eigene Titel und der Lernort bleiben erhalten. CLI:
  `notenportal:modulkatalog ernte.json` für die Vorschau, `--anwenden` zum Schreiben.
- **Betrieb:** Name, Logo (PNG, JPG, WebP, max. 1 MB), Thema, Notengrenzen, Rundung, Fristen;
  Sicherungen mit Zustand, «Jetzt sichern», Herunterladen, Löschen und Kopie ausser Haus mit
  «Verbindung testen»; Systemhinweis als Banner mit Text, Stufe, Zielgruppe und Zeitfenster;
  Sitzungs-Timeout 15 bis 480 Minuten mit Warnung zwei Minuten vorher; Einrichtung erneut öffnen.
- **E-Mail:** Server, Port, Absender, Benutzername, Passwort (verschlüsselt in der Datenbank,
  überschreibt die `.env`), Testmail senden, Versandprotokoll prüfen. Vor dem Produktivgang die
  Umleitung leeren – solange dort eine Adresse steht, gehen alle Mails dorthin.
- **Benachrichtigungen:** Anlässe global ein- oder ausschalten, verpflichtend machen,
  Standardfrequenz setzen. Verpflichtendes kann niemand abwählen, es erscheint gesperrt sichtbar.
- **Berichte:** Notenbericht mit Zeitraum, Lehrberuf, Berufsbildner; Status, Kategorien, tiefste
  Fächer, Verteilung der Zeugnisnoten. Sortierbar, CSV-Export, druckbar.
- **Feedback:** Meldungen aus dem Portal mit Seite, Rolle, Browser, Zeit, letzten
  JavaScript-Fehlern und optional Screenshot. Antwort benachrichtigt die meldende Person.
- **Aktivitätsprotokoll:** An- und Abmeldungen, Kontoänderungen, Betrieb, Sicherungen, Noten,
  Betreuungen – mit Zeit, Person, Ziel und IP. 365 Tage, filterbar.
- **Datenauskunft:** jede Person selbst über Einstellungen → Daten, der Admin für jedes Konto
  über Personen → Benutzer → Datenauskunft. Nie Daten anderer Personen. Grundlage Art. 25 DSG.

---

## 12. Offene Punkte vor dem Produktivgang

| Punkt | Stand | Zuständig | Was genau |
|---|---|---|---|
| Gültiges HTTPS-Zertifikat | offen | Systemtechnik | Danach HTTP→HTTPS-Redirect, HSTS und `SESSION_SECURE_COOKIE=true` aktivieren |
| Produktive SMTP-Zugangsdaten | offen | Product Owner | Admin → Betrieb → E-Mail, Testmail, danach Mail-Umleitung leeren |
| Sicherungskopie ausser Haus | offen | Systemtechnik | Ziel hinterlegen und «Verbindung testen». Bis dahin liegen alle Sicherungen auf demselben Server wie die Daten |
| Firewall einschränken | offen | Systemtechnik | ufw auf die berechtigten Netze begrenzen, Testinstanz-Port von aussen sperren |
| Monitoring in PRTG | offen | Systemtechnik | Sensoren aufbauen, Sensornamen und Intervalle nachtragen |
| Netz- und DNS-Entscheid | offen | Systemtechnik | Fester Name statt IP, damit Zertifikat und Lesezeichen stabil bleiben |
| Einwilligung Katalogdaten | offen | Product Owner | Schriftliche Zustimmung von ICT-Berufsbildung Schweiz |
| Lizenz und Meldeweg für Sicherheitslücken | offen | Product Owner | Lizenztext festlegen, Kontaktadresse publizieren |
| Restore-Test dokumentiert | erledigt | Product Owner | 11.09.2026, Ergebnis im Restorelog. Wiederholung halbjährlich |
| Automatische Sicherung aktiv | erledigt | Betrieb | Täglich 02:30, 14 Stände, Kontrolle im Admin-Dashboard |

Reihenfolge am Tag des Produktivgangs: Zertifikat und Redirect setzen →
`notenportal:bereitschaft` ohne Fehler → SMTP eintragen und Testmail → Mail-Umleitung leeren →
Sicherung von Hand auslösen und herunterladen → erst dann Konten verteilen.

---

## 13. Support und Zuständigkeiten

| Anliegen | Zuständig |
|---|---|
| Passwort vergessen | Selbst: «Passwort vergessen» auf der Anmeldeseite, Link 60 Minuten gültig. Ohne Mailzugang: Admin |
| Konto anlegen, Rolle ändern, Betreuung zuweisen | Admin |
| Falsche Note, falsches Fach | Lernender selbst; Berufsbildner kann korrigieren |
| Fach oder Modul fehlt in der Auswahl | Admin (Stammdaten); ein fehlendes Modul kann jede Person selbst anlegen |
| Fehler, Idee oder Frage im Portal | Meldeknopf im Portal – Seite, Rolle und Technik gehen automatisch mit |
| Portal nicht erreichbar | Systemtechnik, siehe Störungsbehebung |
| Zertifikatswarnung im Browser | Lab-CA importieren, siehe Zugang für Benutzer |
| Modulinhalte, Handlungsziele, Katalogrechte | ICT-Berufsbildung Schweiz |

Namen, Mailadressen und Telefonnummern der Zuständigen sind hier **nicht** hinterlegt:
`‹offen: Kontakttabelle mit Name, Funktion, Mail, Telefon für Product Owner, Stellvertretung,
Systemtechnik, Keyuser Berufsbildung, Keyuser Lernende›`.

---

## 14. Weiterführende Dokumente

| Datei im Repository | Inhalt |
|---|---|
| `README.md` | Installation in zehn Zeilen, Funktionsumfang in Stichworten, Technik |
| `docs/funktionsumfang.md` | Was das Portal kann, Rolle für Rolle |
| `docs/notenlogik.md` | Rechenregeln: Zeugnisnote, Rundung, Gewichtung, Promotion, Rechner |
| `docs/architektur.md` | Aufbau der Anwendung und vollständiges Datenbankschema |
| `docs/betrieb.md` | Serverkonfiguration, HTTPS, Go-Live-Checkliste, Änderungsprotokoll |
| `docs/modulkatalog.md` | Katalog ernten, einlesen, weitergeben |
| `docs/gui-konzept.md` | Gestaltungsregeln der Oberfläche |
| `docs/audit-backlog.md` | Bewusst offen gelassene Punkte, mit Begründung |
| `CONTRIBUTING.md` | Entwicklungsumgebung, Tests, Konventionen |

Quelle: `https://github.com/magic-usb-c/notenportal`

Externe Quellen: modulbaukasten.ch (Modulkatalog der ICT-Berufe), ict-berufsbildung.ch
(Bildungsverordnungen, Bildungspläne, ÜK-Programme), laravel.com/docs, mariadb.com/kb.

---

## 15. Was nicht in die Dokumentation gehört

- **Keine Passwörter, Schlüssel oder Token** – weder Beispiele noch Platzhalter, die echt
  aussehen. Auch keine Inhalte aus `.env`.
- **Keine Inhalte des Modulkatalogs** (Modulnummern, Titel, Handlungsziele). Die Daten gehören
  ICT-Berufsbildung Schweiz und werden nur eingelesen, nie wiedergegeben.
- **Keine echten Personendaten und keine echten Noten.** Screenshots stammen ausschliesslich aus
  der Demoinstanz mit erfundenen Personen.
- **Keine Vermutungen über nicht dokumentierte Teile.** Stattdessen `‹offen: …›`.
