# Notenportal

Notenverwaltung für Lernende in der Schweizer Berufslehre. Lernende erfassen ihre Noten,
sehen Zeugnisnoten, Promotion und Modulfortschritt; Berufsbildner begleiten ihre Lernenden;
Admins verwalten Betrieb, Stammdaten und Konten.

Ein Server, ein Befehl: `sudo ./install.sh` richtet auf einem frischen Ubuntu alles ein.

---

## Installation

Voraussetzungen: Ubuntu 24.04 mit `sudo`-Rechten, etwa 2 GB Arbeitsspeicher, ein freier Port
(Standard 80) und eine Internetverbindung. Sonst nichts – PHP, Apache, MariaDB, Node und
Composer installiert das Skript selbst.

```bash
sudo apt update && sudo apt install -y git
sudo install -d -o "$USER" -g "$USER" /var/www/notenportal
git clone https://github.com/magic-usb-c/notenportal.git /var/www/notenportal
cd /var/www/notenportal
sudo ./install.sh
```

Das Portal gehört nach `/var/www`, nicht ins Home-Verzeichnis: Ubuntu legt Home-Verzeichnisse so
an, dass der Webserver sie nicht betreten darf – Apache würde jede Seite mit «403 Forbidden»
beantworten. Die erste Zeile legt das Verzeichnis darum vorab auf dich als Eigentümer an; so kann
das Skript später ohne `sudo` bauen. Liegt das Portal trotzdem falsch, bricht `install.sh` gleich am
Anfang ab und nennt den Befehl zum Verschieben.

Am Ende nennt das Skript die Adresse, die E-Mail des ersten Admin-Kontos und ein
Startpasswort. Dieses Passwort erscheint genau einmal – notiere es, bevor du das Fenster
schliesst.

Danach geht es nur noch im Browser weiter:

1. Anmelden mit E-Mail und Startpasswort.
2. Das Portal verlangt sofort ein eigenes Passwort.
3. Die Einrichtung öffnet von selbst und führt durch acht Schritte: Betrieb und Notengrenzen,
   Kategorien, Semester, Lehrberufe und Fächer, Module, Personen, E-Mail, Abschluss.
   Lehrberufe, BMS- und ABU-Fächer sind vorausgewählt, der Semesterplan wird vorgeschlagen.

Der Modulkatalog (Nummern, Titel, Handlungsziele, Leistungsbeurteilungen) liegt nicht im
Repository – er gehört ICT-Berufsbildung Schweiz. Im Schritt «Module» lässt er sich als Datei
einlesen; steht er schon auf einer anderen Instanz, liefert dort
`php artisan notenportal:modulkatalog-export` genau diese Datei. Der ganze Weg: `docs/modulkatalog.md`.

Zum Schluss stehen Berufsbildner und Lernende mit Startpasswörtern zum Ausdrucken bereit.
Ein leeres Portal braucht keinen einzigen SQL-Befehl und keinen Eingriff in die Datenbank.

### Optionen

```
sudo ./install.sh --help                              zeigt diese Liste
sudo ./install.sh --port 8082 --db notenportal_i2     zweite Instanz neben einer bestehenden
sudo ./install.sh --host notenportal.example.ch       Servername statt erkannter IP
sudo ./install.sh --ohne-firewall                     keine ufw-Freigabe
sudo ./install.sh --neues-admin-passwort              neues Startpasswort, wenn der Zugang weg ist
```

### Was das Skript erledigt

Pakete (Apache, MariaDB, PHP der Distribution samt Erweiterungen – mindestens 8.3 –, Node 22,
Composer) · Datenbank mit zwei
Benutzern – einer nur für Daten, einer für Schemaänderungen · `.env` mit Zufallspasswörtern und
Produktionswerten · Anwendungsschlüssel · Migrationen · Grundstammdaten (Rollen, Kategorien) ·
erstes Admin-Konto · Oberfläche bauen · Apache-VirtualHost und Port · Firewall-Freigabe, falls
ufw läuft · Cron für Sicherung, Mailversand und Benachrichtigungen · Dateirechte · Schlusstest
gegen `/login`.

Ein erneuter Lauf im selben Verzeichnis ist ein Update: Pakete, Abhängigkeiten, Build und
Migrationen werden nachgezogen, `.env`, Webserver-Konfiguration und Konten bleiben unverändert.

---

## Was danach von Hand gehört

Das Skript liefert ein lauffähiges Portal über HTTP. Für den echten Betrieb bleibt dies offen:

- **HTTPS**: Zertifikat und `mod_ssl` richtet das Skript nicht ein. Ohne TLS gehen Passwörter
  im Klartext durchs Netz. Vorgehen inklusive eigener CA: `docs/betrieb.md`.
- **E-Mail**: Erstinstallation schreibt Mails nur ins Log. SMTP-Server, Absender und Passwort
  trägst du unter Admin → Betrieb → E-Mail ein, mit Testmail.
- **Sicherungskopie ausser Haus**: Die tägliche Sicherung läuft lokal. Ziel (Ordner oder
  SSH-Server) unter Admin → Betrieb hinterlegen und «Verbindung testen» drücken.
- **Zeitzonen-Tabellen der Datenbank**: `mysql_tzinfo_to_sql /usr/share/zoneinfo | sudo mysql mysql`.
- **Firewall einschränken**: Das Skript öffnet nur den Portalport, es schliesst nichts.

Ob alles sitzt, sagt eine rein lesende Prüfung:

```bash
php artisan notenportal:bereitschaft
```

Sie prüft Umgebung, Sicherung, Mail, Kalenderabgleich und offene Migrationen und endet mit
Exit-Code 1, sobald ein Punkt als Fehler gilt.

---

## Funktionsumfang

Vollständig und laufend nachgeführt in `docs/funktionsumfang.md`. In Stichworten:

- **Lernende**: Noten erfassen mit Live-Auswirkung, Zeugnisnoten und Promotion je Semester,
  Modulfortschritt und Modulwiederholung, Ziele, Notenrechner vorwärts und rückwärts,
  Prüfungen planen, Kalender einlesen (iCal aus Schulnetz, Nextcloud, Outlook, Google),
  Notenimport aus Excel, ODS, CSV und PDF, Dokumente, Zeugnis-Abgleich, Notenblatt zum Drucken.
- **Berufsbildner**: Dashboard mit Ampel und Begründung, Lernenden-Cockpit, Noten korrigieren
  und kommentieren, Prüfungstermine, Lernende anlegen.
- **Admin**: geführte Einrichtung, Stammdaten (Lehrberufe, Fächer, Kategorien, Semester,
  Module), Modulkatalog aus modulbaukasten.ch, Benutzer und Betreuungen, Betrieb mit
  Notengrenzen und Sicherungen, Benachrichtigungen, Notenbericht, Aktivitätsprotokoll.
- **Überall**: Hell und Dunkel, zwölf Farbthemen (WCAG AA geprüft), persönliche Darstellung,
  Befehlspalette mit Ctrl+K, Tastenkürzel, Feedback-Meldungen, Deutsch und Englisch.

---

## Technik

Laravel 13 auf PHP 8.3, MariaDB, Blade mit Tailwind CSS 4, Alpine.js, Vite, Apache mit
mod_php. Die Oberfläche ist deutsch (Schweizer Hochdeutsch), Routen und neuer Code englisch.

Zwei Datenbankbenutzer nach dem Prinzip der geringsten Rechte: der Web-Benutzer darf nur Daten
lesen und schreiben, Schemaänderungen laufen über einen eigenen Benutzer. Deshalb heisst der
Migrationsbefehl hier:

```bash
php artisan notenportal:migrate
```

Wegweiser durch die Dokumentation:

| Datei | Inhalt |
|---|---|
| `docs/funktionsumfang.md` | Was das Portal kann, Rolle für Rolle |
| `docs/notenlogik.md` | Rechenregeln: Zeugnisnote, Rundung, Gewichtung, Promotion |
| `docs/architektur.md` | Datenbankschema und Aufbau der Anwendung |
| `docs/betrieb.md` | Betrieb, HTTPS, Sicherung, Wiederherstellung, Änderungsprotokoll |
| `docs/modulkatalog.md` | Modulkatalog von modulbaukasten.ch ernten und einlesen |
| `docs/audit-backlog.md` | Bewusst offen gelassene Punkte samt Begründung |

---

## Mitarbeit

Entwicklungsumgebung, Tests und Konventionen: `CONTRIBUTING.md`.

## Lizenz

Noch keine festgelegt – bis dahin gilt «alle Rechte vorbehalten». Der Eintrag `"license": "MIT"`
in `composer.json` stammt aus dem Laravel-Grundgerüst und ist keine Rechteerteilung.
Auch der Meldeweg für Sicherheitslücken steht noch nicht fest; beide Punkte stehen in
`docs/endspurt-plan.md` unter «Offene Produktentscheide».
