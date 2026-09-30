# Notenportal

Notenverwaltung für Lernende in der Schweizer Berufslehre. Lernende erfassen ihre Noten,
sehen Zeugnisnoten, Promotion und Modulfortschritt; Berufsbildner begleiten ihre Lernenden;
Admins verwalten Betrieb, Stammdaten und Konten.

Ein Server, ein Befehl: `sudo ./install.sh` richtet auf einem frischen Ubuntu alles ein –
einschliesslich HTTPS mit eigener Zertifizierungsstelle.

---

## Installation

Voraussetzungen: Ubuntu 24.04 LTS oder neuer (erprobt bis 26.04) mit `sudo`-Rechten, etwa 2 GB
Arbeitsspeicher, freie Ports 80 und 443 und eine Internetverbindung. Sonst nichts – PHP, Apache, MariaDB, Node und
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

Die Adresse ist standardmässig `https://notenportal`. Damit andere Geräte diesen Namen auflösen,
braucht es einen A-Record auf dem zuständigen DNS-Server; das Skript nennt am Schluss den nötigen
Eintrag. Die Zertifizierungsstelle liegt unter `/etc/ssl/notenportal/ca.crt` und lässt sich als
`http://<name>/lab-ca.crt` herunterladen – ohne diesen einmaligen Import warnt der Browser,
verschlüsselt aber trotzdem.

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
sudo ./install.sh --host notenportal.example.ch       Servername im vhost und im Zertifikat
sudo ./install.sh --ohne-https                        nur HTTP, ohne Zertifikat
sudo ./install.sh --https-port 8443                   HTTPS auf einem anderen Port
sudo ./install.sh --ohne-firewall                     keine ufw-Freigabe
sudo ./install.sh --neues-admin-passwort              neues Startpasswort, wenn der Zugang weg ist
```

### Was das Skript erledigt

Pakete (Apache, MariaDB, PHP der Distribution samt Erweiterungen – mindestens 8.3 –, Node 22,
Composer) · Datenbank mit zwei
Benutzern – einer nur für Daten, einer für Schemaänderungen · `.env` mit Zufallspasswörtern und
Produktionswerten · Anwendungsschlüssel · Migrationen · Grundstammdaten (Rollen, Kategorien) ·
erstes Admin-Konto · Oberfläche bauen · eigene Zertifizierungsstelle und Serverzertifikat ·
Apache-VirtualHosts für HTTP und HTTPS samt Umleitung · Firewall-Freigabe, falls ufw läuft ·
Cron für Sicherung, Mailversand und Benachrichtigungen · erste Sicherung · Dateirechte ·
Schlusstest gegen `/login` über HTTPS, mit Prüfung der Zertifikatskette.

Ein erneuter Lauf im selben Verzeichnis ist ein Update: Pakete, Abhängigkeiten, Build und
Migrationen werden nachgezogen, `.env`, Zertifikat, Webserver-Konfiguration und Konten bleiben
unverändert. Läuft das Serverzertifikat in weniger als 30 Tagen ab, stellt der Lauf ein neues aus –
mit derselben Zertifizierungsstelle, die Geräte müssen nichts neu importieren.

---

## Was danach von Hand gehört

Das Skript liefert ein lauffähiges Portal über HTTPS. Für den echten Betrieb bleibt dies offen:

- **DNS-Eintrag**: Den Namen aus `--host` als A-Record auf dem DNS-Server eintragen, den die
  Clients benutzen. Das ist der einzige Schritt ausserhalb der Maschine.
- **Zertifikat verteilen**: `http://<name>/lab-ca.crt` auf jedem Gerät einmal importieren.
  Ein Zertifikat einer offiziellen Stelle ersetzt es, indem nur `SSLCertificateFile` und
  `SSLCertificateKeyFile` im vhost getauscht werden.
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
| `docs/systemdoku/` | Systemdokumentation als OneNote-Abschnitt (HTML zum Einfügen, mit Screenshots) |

---

## Mitarbeit

Entwicklungsumgebung, Tests und Konventionen: `CONTRIBUTING.md`.

## Lizenz

Noch keine festgelegt – bis dahin gilt «alle Rechte vorbehalten». Der Eintrag `"license": "MIT"`
in `composer.json` stammt aus dem Laravel-Grundgerüst und ist keine Rechteerteilung.
Auch der Meldeweg für Sicherheitslücken steht noch nicht fest; beide Punkte stehen in
`docs/endspurt-plan.md` unter «Offene Produktentscheide».
