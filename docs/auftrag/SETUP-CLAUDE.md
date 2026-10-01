# Einrichtung der Claude-Code-Sitzungen für das Notenportal

Stand 01.10.2026. Jede Aussage über Claude Code ist an der offiziellen Dokumentation vom selben Tag
geprüft (`settings-reference`, `commands`, `model-config`) oder im laufenden Cloud-Container gemessen
(Claude Code 2.1.286, PHP 8.4.19, Node 22.22.2, MariaDB 10.11.14). Was sich nicht belegen liess, steht
in Abschnitt 12 als offene Frage, nicht als Tatsache.

Ziel: Eine Sitzung mit **Fable 5.1 auf Ultracode** leitet die Arbeit, wählt für jede Teilaufgabe
selbst Modell und Effort, startet und beaufsichtigt Workflows, Subagenten und Hintergrundaufgaben,
legt neue Skills, Agents und Workflows an und arbeitet ohne Rückfragen bis zum Stopp. Massstab der
Oberfläche: Desktop-Browser 1920×1080 bis 2560×1440 im Dunkelmodus nach Apple Human Interface
Guidelines (`docs/auftrag/GUI-APPLE.md`).

---

## 0. In drei Schritten

1. **Cloud-Sitzung** auf `claude.ai/code` für `magic-usb-c/notenportal`, Branch `main`, starten.
   Der Hook `.claude/hooks/session-start.sh` richtet den Container ein (Abschnitt 4); ein
   Setup-Skript in der Umgebung ist nicht nötig.
2. **Berechtigungen** im Menü der Sitzung auf **Auto** stellen. Die Projektdatei kann nur
   `acceptEdits` wirksam setzen; `auto` zählt laut Dokumentation nur aus `~/.claude/settings.json`
   (Abschnitt 6).
3. **Prompt:** `/weiter` – oder `/weiter <Schwerpunkt>`, zum Beispiel
   `/weiter R4 Admin-Bereich im Dunkelmodus`. Alles Weitere steht in `.claude/commands/weiter.md`.
   Bietet die Oberfläche `/weiter` nicht an, den Text dieser Datei als Prompt einfügen (Schwerpunkt
   statt `$ARGUMENTS`).
   Stoppen jederzeit mit der Stopp-Taste; der Stand ist in `docs/auftrag/UEBERGABE.md` und auf
   `main`, weil nach jedem lauffähigen Stand committet und gepusht wird.

Lokal im Terminal (VM): Abschnitt 5, einmalig `~/.claude/settings.json` anlegen, dann `claude` im
Repo starten und `/weiter` eingeben.

---

## 1. Abbild: was wo liegt

### 1.1 Im Repository (gilt in jeder Sitzung, Cloud und lokal)

| Datei | Zweck |
|---|---|
| `CLAUDE.md` | Projektanweisungen, harte Regeln, Massstab Desktop/Dunkelmodus, Befehle. Wird jeder Sitzung und jedem Subagenten vorangestellt. |
| `.claude/settings.json` | Projekt-Einstellungen, committet: Modell `fable`, Effort `xhigh`, `modelSettings` (fable/opus xhigh, sonnet high), `alwaysThinkingEnabled`, `advisorModel: fable`, `ultracode: true`, `workflowSizeGuideline: unrestricted`, `subagentPromptCacheTtl: 1h`, `language: deutsch`, `enabledPlugins`, `permissions` (Allow-/Deny-Listen, `defaultMode: acceptEdits`), Hooks. Vollständig in Abschnitt 3. |
| `.claude/settings.local.json` | Nicht committet, nur dieser Container: erlaubt `mcp__Claude_Code_Remote__read_documentation`. |
| `.claude/commands/weiter.md` | Startprompt für autonome Sitzungen (`/weiter [Schwerpunkt]`). |
| `.claude/skills/notenportal-orchestrierung/SKILL.md` | Modell und Effort je Aufgabe; selbst, Subagent oder Workflow; wie neue Agents/Skills/Workflows angelegt werden. |
| `.claude/skills/notenportal-dunkelmodus/SKILL.md` | HIG-Regeln für Farbe, Tiefe, Typografie und Layout im Dunkelmodus, mit Quelle je Regel, Prüfliste §5. |
| `.claude/skills/notenportal-ui/SKILL.md` | Tokens, Klassen, Muster, Verbote, Abschlussprüfung (§10). |
| `.claude/skills/notenportal-pruefwerkzeuge/SKILL.md` | Werkzeuge in `tools/pruefung/` und Demo-Server. |
| `.claude/skills/notenportal-agentauftrag/SKILL.md` | Vorlage und Pflichtblock für jeden Auftrag an einen Subagenten. |
| `.claude/skills/notenportal-blockabschluss/SKILL.md` | Was vor «Block fertig» zu messen ist. |
| `.claude/skills/notenportal-sessionende/SKILL.md` | Was vor dem Ende einer Sitzung zu tun ist. |
| `.claude/skills/notenportal-migration/SKILL.md` | Migrationen auf VM-Datenbanken (Dump, Tag, `notenportal:migrate`). |
| `.claude/agents/*.md` | Acht Subagenten mit festem Modell und Effort (Tabelle in 2.3). |
| `.claude/rules/oberflaeche.md` | Regel mit `paths:` – lädt automatisch, sobald eine Datei unter `resources/views`, `resources/css` oder `resources/js` bearbeitet wird. |
| `.claude/workflows/notenportal-audit.js` | Workflow: sechs Audit-Dimensionen parallel, jeder Befund zweifach gegnerisch geprüft. `args: { dimensionen?, max? }`. |
| `.claude/workflows/notenportal-dunkel-rundgang.js` | Workflow: Screenshots je Rolle bei 1920 und 2560 px im Dunkelmodus, Sichturteil je Bild, zweifache Verifikation je Befund. `args: { rollen?, breiten?, ordner?, maxBilder?, buendel?, maxSeiten? }`. |
| `.claude/hooks/session-start.sh` | SessionStart, nur bei `CLAUDE_CODE_REMOTE=true`: MariaDB, Datenbanken `notenportal_test` und `notenportal_demo`, `composer install`, `npm install`, `npm run build`, Umgebungsvariablen in `$CLAUDE_ENV_FILE`, Demo-Server auf 8099, Plugins aus dem offiziellen Marktplatz. Protokoll: `~/.notenportal/session-start.log`. |
| `.claude/hooks/view-pruefung.sh` | PostToolUse nach Edit/Write: meldet ß und Farb-Hardcodes in Views (Mail-Vorlagen und Notenblatt ausgenommen). |
| `tools/pruefung/{browser,shot,klick,rundgang}.mjs`, `demo-server.sh` | Browser-Werkzeuge (Playwright, Chromium aus `/opt/pw-browsers`), Passwort nur über `NP_TEST_PW`. |
| `docs/auftrag/LAGE.md` · `UEBERGABE.md` · `GUI-APPLE.md` · `NACHTLAUF.md` · `NOTENBAUM.md` | Lage, Übergabebrett, Auftrag Oberfläche, Grundsätze, Auftrag Notenlogik. |
| `docs/audit-backlog.md` | Bewusst Liegengelassenes mit Begründung. |

### 1.2 Ausserhalb des Repositories (Container oder Benutzerverzeichnis)

Gemessen am 01.10.2026 im Cloud-Container. **Alles hier geht mit dem Container verloren**; die Cloud
behält nur Gespräch, Branch und Aufgabenliste.

| Ort | Inhalt |
|---|---|
| `~/.claude/settings.json` | `{"autoCompactWindow": 300000, "advisorModel": "fable"}` – durch `/advisor fable` in dieser Sitzung geschrieben. |
| `~/.claude/plugins/known_marketplaces.json` | Nur `claude-plugins-official` (GitHub `anthropics/claude-plugins-official`). |
| `~/.claude/plugins/installed_plugins.json` | War leer; `php-lsp@claude-plugins-official` wurde testweise installiert (Hook macht das künftig, Abschnitt 8). |
| `~/.claude/skills/synced/<org>/…` | 20 von der Organisation synchronisierte Skills: canvas-design, convert-to-markdown, doc-coauthoring, docs, docx, google-workspace, import-memory, internal-comms, kb-snow-anleitung, kb-snow-information, learn, morning, pdf, powershell-expert, pptx, skill-creator, theme-factory, web-artifacts-builder, word-doku, xlsx. Keiner betrifft das Notenportal. |
| `~/.claude/projects/-home-user-notenportal/memory` | Nicht vorhanden. Auto-Memory ist maschinenlokal und wird laut Dokumentation nicht in Cloud-Sitzungen synchronisiert – Wissen gehört deshalb ins Repo (`docs/auftrag/`), nicht ins Gedächtnis. |
| Umgebungsvariablen der Sitzung | `CLAUDE_CODE_REMOTE=true`, `CLAUDE_EFFORT=xhigh`, `CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH=1`, `CLAUDE_AUTOCOMPACT_PCT_OVERRIDE=80`, `CLAUDE_CODE_VERSION=2.1.42` (die CLI selbst meldet 2.1.286), `PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers`, `HTTPS_PROXY` (Agent-Proxy, CA `/root/.ccr/ca-bundle.crt`). |
| Connectors (MCP) | GitHub (`mcp__github__*`, nur `magic-usb-c/notenportal`), Gmail (verbunden, vom Portal nicht gebraucht), Claude Docs, Claude Code Remote (Sitzungen, Routinen, Dokumentation). |
| Werkzeuge der Sitzung | `advisor` (Fable), `Workflow` (Ultracode an), `Agent`, `Artifact`, Tasks, `WebSearch`/`WebFetch`. |

### 1.3 Was eine Sitzung nicht sieht

- `.env` (Deny-Regel), `tmp-testdaten/` (Deny-Regel, echte Noten), Modulkatalog-Inhalte, Tokens.
- Das Demo-Passwort steht nur als Konstante `DEMO_PASSWORT` in `database/seeders/DemoSeeder.php`
  und wird nur in eine Umgebungsvariable gelesen, nie ausgegeben.

---

## 2. Modell und Effort

### 2.1 Stufen (Dokumentation `model-config`, 01.10.2026)

| Stufe | Bedeutung |
|---|---|
| `low` | Schnell, wenig Nachdenken: mechanische Arbeit, Inventar, Suche. |
| `medium` | Standard für Opus 5.5 und Sonnet 5.5. |
| `high` | Standard der meisten übrigen Modelle. |
| `xhigh` | Standard von Opus 4.7; unsere Hauptsitzung und alle Prüfer. |
| `max` | Nur für die Sitzung (`/effort max`), neigt zum Überdenken – für hartnäckige Fehler und letzte Verifikation. |
| `ultracode` | Keine Stufe, sondern eine Einstellung: Claude plant für jede substanzielle Aufgabe einen Workflow. |

### 2.2 Wo Modell und Effort gesetzt werden

| Ort | Schlüssel | Gilt für |
|---|---|---|
| `.claude/settings.json` | `model`, `effortLevel`, `modelSettings.<id>.effortLevel`, `maxEffortLevel` | Hauptsitzung. `effortLevel` aus Projekt-, Local- oder Managed-Settings gilt für jedes Modell; aus User-Settings ignorieren Opus 5.5 und neuer den Wert, dort braucht es `modelSettings`. |
| `.claude/agents/<name>.md` | Frontmatter `model: sonnet\|opus\|haiku\|fable\|<id>\|inherit`, `effort: low\|medium\|high\|xhigh\|max` | Dieser Subagent. |
| Workflow-Skript | `agent(prompt, { model, effort })` | Diese Stufe des Workflows. Ohne Angabe erbt der Agent Modell und Effort der Hauptsitzung (Fable xhigh) – für mechanische Arbeit Verschwendung, deshalb immer setzen. |
| `SKILL.md` | Frontmatter `model`, `effort` | Ein Skill, der als eigener Agent läuft (`context: fork`). |
| Sitzung | `/model <alias>`, `/effort <stufe>`, `/effort ultracode on\|off`, `/advisor <alias>` | Nur diese Sitzung (`/model` ohne `s` speichert als Standard). |
| Umgebung | `ANTHROPIC_MODEL`, `ANTHROPIC_DEFAULT_MODEL`, `CLAUDE_CODE_EFFORT_LEVEL`, `CLAUDE_CODE_SUBAGENT_MODEL`, `MAX_THINKING_TOKENS` | Eine Sitzung, überschreibt Dateien. |

Aliase laut Dokumentation: `fable`, `opus`, `sonnet`, `haiku`, `best` (Fable, sonst Opus), `default`,
`opusplan`. Volle IDs: `claude-fable-5-1`, `claude-opus-5-5`, `claude-sonnet-5-5`,
`claude-haiku-4-5-20251001`. Der Advisor muss mindestens so stark sein wie das Hauptmodell; `fable`
verlangt einmal `/model fable` zur Einwilligung in den Kreditverbrauch.

### 2.3 Entscheidungstabelle (Skill `notenportal-orchestrierung`)

| Aufgabe | Modell | Effort | Form |
|---|---|---|---|
| Dateien finden, Inventar, Vorkommen zählen, Routen auflisten | haiku (`Explore`) · sonnet (`explorer`) | low | Agent |
| Schema und Datenbestand nachsehen | sonnet | medium | `db-inspector` |
| Umsetzen (Controller, Views, Tests), Dokumentation | sonnet | high | Subagent oder Workflow-Stage |
| Code-Review eines Diffs | sonnet | high | `reviewer` |
| Texte für Menschen | opus | high | `texter` |
| Fakten Schweizer Berufsbildung | sonnet | high | `recherche-schweiz` |
| UI-Regelprüfung je Rollenbereich | opus | xhigh | `ui-checker` |
| Sichtprüfung von Screenshots | opus | xhigh | `bildpruefer` |
| Behauptung «fertig/behoben/grün» widerlegen | opus | xhigh | `pruefer` |
| Architektur, Datenmodell, riskante Migration | opus | xhigh | Subagent, danach `pruefer` |
| Hartnäckiger Fehler nach zwei erfolglosen Runden | opus → fable | xhigh → max | Instrument bauen, messen |
| Letzte Verifikation bei widersprüchlichen Prüfern | fable | max | Workflow-Stage mit Mehrheitsvotum |
| Leiten, zerlegen, committen, berichten | fable | xhigh | Hauptsitzung selbst |

Die Agents im Repo tragen genau diese Werte (`model`/`effort` im Frontmatter): bildpruefer opus xhigh ·
db-inspector sonnet medium · explorer sonnet low · pruefer opus xhigh · recherche-schweiz sonnet high ·
reviewer sonnet high · texter opus high · ui-checker opus xhigh.

---

## 3. Was diese Umstellung hinzugefügt oder geändert hat

Commit `13f0edb` (Hook, Werkzeuge, Einstellungen) und der Folgecommit (alles Übrige).

| Datei | Änderung |
|---|---|
| `CLAUDE.md` | Neu geschrieben: eine Sitzung auf `main`, Massstab Desktop/Dunkelmodus, Orchestrierung, Werkzeuge im Repo, Cloud-Betrieb. |
| `.claude/settings.json` | Modell/Effort/Advisor/Ultracode/Workflow-Grösse/Cache/Sprache; Allow-Liste für git, php, composer (ohne update/require), npm, node, mysql über Socket, Werkzeuge; Deny-Liste für Force-Push, `reset --hard`, `clean -fdx`, `rm -rf`, sudo, `DROP DATABASE`, `composer update/require`, `install.sh`, `.env`, `tmp-testdaten`; Hooks; Plugin `php-lsp`. |
| `.claude/commands/weiter.md` | Startprompt, liest Orchestrierungs-Skill, arbeitet mit Workflows, hört nicht auf. |
| `.claude/skills/notenportal-orchestrierung/SKILL.md` | Neu. |
| `.claude/skills/notenportal-dunkelmodus/SKILL.md` | Neu, aus den HIG-Seiten Dark Mode, Color, Materials, Typography, Layout (DocC-JSON gelesen, nicht aus dem Gedächtnis). |
| `.claude/skills/notenportal-ui/SKILL.md` | §10 Abschluss: Werkzeuge aus `tools/pruefung`, dunkel 1920 und 2560, hell nur Stichprobe. |
| `.claude/skills/notenportal-pruefwerkzeuge`, `-agentauftrag`, `-blockabschluss`, `-sessionende` | Auf Repo-Werkzeuge, Desktop/Dunkel und Cloud umgestellt. |
| `.claude/agents/bildpruefer.md` | Neu (opus xhigh). |
| `.claude/agents/{db-inspector,explorer,pruefer,recherche-schweiz,texter,ui-checker}.md` | `model`/`effort` gesetzt; `ui-checker` war von David schon auf opus gestellt (9f65858). |
| `.claude/rules/oberflaeche.md` | Neu. |
| `.claude/workflows/notenportal-audit.js` | Neu (ersetzt `notenportal-audit-v2.js`). |
| `.claude/workflows/notenportal-dunkel-rundgang.js` | Neu. |
| `.claude/hooks/session-start.sh` | Neu; Abschnitt 7 installiert Plugins. |
| `.claude/hooks/view-pruefung.sh` | Pfadpräfix über `CLAUDE_PROJECT_DIR`. |
| `tools/pruefung/*` | Neu im Repo (vorher `~/tools/visual` auf der VM). |
| `docs/auftrag/GUI-APPLE.md` | Massstab Desktop/Dunkel, Quelle, Beweis mit Workflow. |
| `docs/auftrag/LAGE.md` | §1 Cloud-Betrieb, §7 Stand R1–R5, §8 Migrationen Cloud/VM und Orchestrierung. |
| `docs/auftrag/NACHTLAUF.md` | Banner: Blöcke 1–5 erledigt, Grundsätze gelten. |
| `docs/auftrag/UEBERGABE.md` | Eintrag `[Q] 01.10. 08:32`, «Offen für David» ergänzt. |
| `docs/auftrag/SETUP-CLAUDE.md` | Diese Datei. |

---

## 4. Die Cloud-Umgebung (claude.ai/code)

Dokumentation: `https://code.claude.com/docs/en/claude-code-on-the-web` und
`…/cloud-environments`.

1. **Repository** `magic-usb-c/notenportal`, Standardbranch `main`. Sitzungen arbeiten direkt auf
   `main` (NACHTLAUF-Grundsatz: eine Sitzung, keine Feature-Branches). Für Experimente mit
   Aussenwirkung einen `claude/*`-Branch nehmen.
2. **Umgebung:** Netzwerkzugriff so wählen, dass `registry.npmjs.org`, `repo.packagist.org`,
   `github.com`, `developer.apple.com` (HIG-DocC-JSON) und `fedlex.admin.ch` erreichbar sind; in
   dieser Sitzung lief alles über den vorkonfigurierten Agent-Proxy. Setup-Skript: leer lassen,
   der Hook übernimmt. Umgebungsvariablen und Secrets: keine nötig – Datenbank ohne Passwort über
   Socket, Demo-Passwort aus dem Seeder, kein API-Schlüssel.
3. **Hook-Ablauf** (`session-start.sh`, synchron, Timeout 900 s): MariaDB über `mysqld_safe` auf
   `/run/mysqld/mysqld.sock` starten · Datenbanken `notenportal_test` und `notenportal_demo` anlegen ·
   `composer install` · `npm install` + `npm run build` · `APP_KEY` erzeugen, Variablen in
   `$CLAUDE_ENV_FILE` (bewusst **ohne** `DB_DATABASE`: `phpunit.xml` setzt `notenportal_test`, der
   Demo-Server `notenportal_demo`) · Demo-DB migrieren und mit `DemoSeeder` befüllen, wenn die
   Tabelle `benutzer` leer ist · `tools/pruefung/demo-server.sh start` auf `http://127.0.0.1:8099` ·
   Plugins `php-lsp` und `frontend-design` installieren. Der Container wird nach dem Hook
   zwischengespeichert; ein zweiter Lauf überspringt, was da ist.
4. **Berechtigungen:** Menü der Sitzung → **Auto**. Deny-Regeln aus `.claude/settings.json` gelten in
   jedem Modus. Die Projektdatei trägt `acceptEdits`, weil `auto` dort laut Dokumentation nicht
   wirkt (Abschnitt 6).
5. **Starten:** `/weiter` oder `/weiter <Schwerpunkt>`. Eine Fortsetzung nach Kontextverdichtung
   braucht nichts Besonderes: der Hook läuft auch bei `compact` und `resume`.
6. **Was bleibt:** Gespräch, Branch, Aufgabenliste. **Was nicht bleibt:** laufende Hintergrundarbeit,
   `~/.claude`, Screenshots im Container – deshalb alles, was zählt, committen oder in `docs/` schreiben.

---

## 5. Lokal im Terminal (VM oder eigener Rechner)

Einmalig `~/.claude/settings.json` (Benutzer-Ebene; die Projektdatei im Repo bleibt wie sie ist):

```json
{
  "permissions": { "defaultMode": "auto" },
  "advisorModel": "fable",
  "autoCompactWindow": 500000,
  "promptCacheTtl": "1h",
  "autoMemoryEnabled": true,
  "skipAutoPermissionPrompt": true,
  "modelSettings": {
    "claude-fable-5-1": { "effortLevel": "xhigh" },
    "claude-opus-5-5": { "effortLevel": "xhigh" },
    "claude-sonnet-5-5": { "effortLevel": "high" }
  }
}
```

Warum so: `permissions.defaultMode: auto` und `bypassPermissions` wirken nur aus der Benutzer- oder
Managed-Datei; `autoCompactWindow` nimmt laut `settings-reference` eine Tokenzahl von 100000 bis
1000000 (`/autocompact 500k` schreibt denselben Wert); `promptCacheTtl: 1h` hält den Cache über
Pausen warm (teurerer Schreibvorgang, dafür weniger Neuaufbau); `modelSettings` ist nötig, weil
Opus 5.5 und neuer `effortLevel` aus User-Settings ignorieren.

Dann im Repo `claude` starten und einmalig:

```
/model fable            # Einwilligung in den Fable-Kreditverbrauch, speichert als Standard
/advisor fable
/effort ultracode on    # nur nötig, falls die Projektdatei nicht greift; sonst schon an
/plugin install php-lsp@claude-plugins-official
/plugin install frontend-design@claude-plugins-official
/reload-skills
/weiter
```

Auf der VM gilt zusätzlich `CLAUDE.md`: autonome Sitzungen nie in `/var/www/notenportal`, sondern in
einem Worktree auf einem `claude/*`-Branch (`git worktree add ../np-claude -b claude/<thema>`),
Migrationen über den Skill `notenportal-migration`.

---

## 6. Berechtigungen

`permissions.defaultMode` kennt laut `settings-reference` sieben Werte: `default`, `acceptEdits`,
`plan`, `auto`, `dontAsk`, `bypassPermissions`, `manual` (Alias von `default`).

- «`auto` and `bypassPermissions` don't take effect from project or local settings, so set them in
  `~/.claude/settings.json`.»
- «In cloud sessions, Claude Code honors only `acceptEdits`, `plan`, `default`, and `auto` from this
  key.» In der Cloud ist der Modus deshalb über das Menü der Sitzung zu wählen.
- `permissions.deny` gilt in jedem Modus, auch in `auto` und `bypassPermissions`. Die Deny-Liste
  im Repo bleibt trotz «Claude darf alles» bestehen: sie verhindert nur das, was nie gewollt ist
  (Force-Push auf `main`, Datenbank löschen, `.env` lesen, echte Noten lesen, Framework-Upgrade ohne
  Dump).
- `permissions.ask` wirkt nur im Frage-Modus (`default`), nicht in `auto`.
- Abgelehnte Aufrufe im Auto-Modus zeigt `/permissions` («recent auto mode denials»); eigene Regeln
  für den Klassifikator kommen in `autoMode.allow|soft_deny|hard_deny` (User- oder Managed-Datei).
- Subagenten laufen mit den Berechtigungen der Sitzung; das Agent-Frontmatter kennt
  `permissionMode` mit denselben Werten. Laut Referenz zu `permissions.disableBypassPermissionsMode`
  kann ein Agent-Frontmatter sogar `bypassPermissions` setzen (dieser Schlüssel ignoriert es dann) –
  ein Agent darf also grundsätzlich mehr als die Sitzung. Kein Agent im Repo setzt `permissionMode`;
  die Deny-Liste gilt für alle.

---

## 7. Wie Fable die Arbeit leitet

Ablauf eines `/weiter`-Laufs:

1. Lesen: `LAGE.md`, `UEBERGABE.md`, `CLAUDE.md`, Skill `notenportal-orchestrierung`, Auftrag zum
   Punkt (`GUI-APPLE.md` oder `NOTENBAUM.md`).
2. Advisor vor dem ersten substanziellen Schritt.
3. Zerlegen. Substanzielle Aufgaben als **Workflow** (`Workflow`-Werkzeug; Ultracode ist in der
   Projektdatei an, das Stichwort `ultracode` im Prompt löst zusätzlich einen Workflow aus):
   Befunde parallel sammeln (`pipeline`), jeden Befund gegnerisch verifizieren (zwei bis drei
   Prüfer mit verschiedenen Blickwinkeln, Mehrheit zählt), erst dann umsetzen. Vorlagen:
   `notenportal-audit`, `notenportal-dunkel-rundgang` (beide als `/`-Befehle sichtbar, mit `args`).
4. Abgegrenzte Teile als **Subagent** (`Agent`-Werkzeug, mehrere unabhängige in einer Nachricht);
   Modell und Effort nach 2.3. In der Cloud gilt `CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH=1`:
   Subagenten starten keine Subagenten, Workflows laufen deshalb aus der Hauptsitzung.
5. Jede Behauptung eines Agents nachmessen: Suite selbst laufen lassen, Screenshot selbst mit
   `Read` ansehen, `pruefer` ansetzen.
6. Nach jedem lauffähigen Stand committen und auf `main` pushen (Prefix `Feat:` `Fix:` `GUI:`
   `Refactor:` `Test:` `Docs:` `Chore:`, Deutsch, Imperativ, eine Zeile). Subagenten schreiben
   nie Git, Composer oder npm.
7. Block fertig: Skill `notenportal-blockabschluss`, `pruefer`, Eintrag ins Übergabebrett, Advisor.
   Dann ohne Rückfrage den nächsten Punkt.

Fable legt selbst an, was wiederkehrt (Skill `notenportal-orchestrierung` §4): Agent in
`.claude/agents/<name>.md`, Skill in `.claude/skills/<name>/SKILL.md` (danach `/reload-skills`),
Pfadregel in `.claude/rules/`, Workflow in `.claude/workflows/<name>.js` (bewährter Lauf: `/workflows`,
Taste `s`, Ziel «Projekt»), Befehl in `.claude/commands/<name>.md`. Alles wird committet.

Hintergrund und Zeit: `/background` (Sitzung freigeben), `/fork <prompt>` (Kopie arbeitet
parallel), `/subtask <aufgabe>` (Subagent mit vollem Gespräch), `/loop` (wiederholt), `/schedule`
(Routinen in der Cloud), `send_later` (Selbst-Erinnerung in der Cloud-Sitzung), `/goal <bedingung>`
(arbeitet über Runden bis die Bedingung erfüllt ist).

---

## 8. Plugins, Connectors, Skills

**Plugins.** `enabledPlugins` in der Projektdatei schaltet ein, installiert aber nichts: «Enabling a
plugin … in a project's `.claude/settings.json` doesn't install it for other people … until each
user installs it themselves.» Deshalb installiert der Hook in der Cloud (geprüft mit
`claude plugin install`, CLI 2.1.286):

- `php-lsp@claude-plugins-official` – Sprachserver für PHP, Ersatz für `laravel-lsp@laravel`, das
  im Container keinen Marktplatz hat.
- `frontend-design@claude-plugins-official` – Gestaltung von Oberflächen.

Beim regulären Start läuft der Hook, bevor die erste Runde beginnt. Ob eine **laufende** Sitzung ein
Plugin sieht, das der Hook eben erst installiert hat, ist nicht gemessen; nach einem Lauf von Hand
hilft `/reload-plugins`. Lokal sind `php-lsp` und `laravel-lsp@laravel` beide eingeschaltet, also
zwei PHP-Sprachserver – einen davon in `.claude/settings.local.json` auf `false` setzen.

Die übrigen Einträge (`caveman@caveman`, `laravel@laravel`, `laravel-lsp@laravel`,
`tailwind-v4-shadcn@claude-skills`, `ui-ux-pro-max@ui-ux-pro-max-skill`) stammen aus Davids lokaler
Einrichtung (Commit 22e5c23). Ihre Marktplätze sind im Container nicht bekannt; sie wirken nur
lokal. Sollen sie auch in der Cloud laufen, kommt je Marktplatz ein Eintrag mit Quelle in die
Projektdatei – die Quellen kennt nur David:

```json
"extraKnownMarketplaces": {
  "laravel": { "source": { "source": "github", "repo": "<owner>/<repo>" } }
}
```

und der Hook erhält die Plugins in seiner Schleife (Abschnitt 7 des Skripts).

Im offiziellen Marktplatz liegen ausserdem, für das Projekt brauchbar: `code-review`,
`security-guidance`, `commit-commands`, `pr-review-toolkit`, `claude-md-management`, `hookify`,
`session-report`, `ralph-loop`. Nicht eingeschaltet – `/code-review`, `/security-review` und
`/simplify` sind ohnehin eingebaut.

**Connectors.** GitHub ist über MCP angebunden (Pull Requests, Issues, Actions – im Projekt nicht
nötig, weil direkt auf `main` gearbeitet wird). Gmail ist verbunden, wird vom Portal nicht
gebraucht. Weitere Connectors braucht die Arbeit nicht. Verwaltung: `/mcp`,
`https://claude.ai/settings/connectors`.

**Skills.** Projekt-Skills (Abschnitt 1.1) laden automatisch nach Beschreibung oder über
`/<name>`; `/skills` zeigt sie mit Kontextkosten, `/skill-doctor` die Nutzung. Die 20 synchronisierten
Organisations-Skills sind für Büroarbeit (docx, pptx, xlsx, pdf …) und stören nicht.

---

## 9. Alle `/`-Befehle (Claude Code, Dokumentation `commands` vom 01.10.2026)

Fett: im Notenportal regelmässig gebraucht. «Skill» = eingebauter Skill, «Workflow» = eingebauter
Workflow. Versionshinweise nur, wo sie für 2.1.286 noch relevant sind.

| Befehl | Was er tut |
|---|---|
| `/add-dir <pfad>` | Weiteres Arbeitsverzeichnis für Dateizugriff freigeben (Konfiguration daraus wird nicht geladen). |
| **`/advisor [fable\|opus\|sonnet\|<id>\|off]`** | Advisor-Werkzeug ein/aus und Modell wählen; ohne Argument Auswahl, in Sitzungen ohne Terminal druckt es den Stand. |
| `/agents` | Hinweis, Subagenten über Claude oder direkt in `.claude/agents/` zu pflegen (seit 2.1.198 keine Oberfläche mehr). |
| `/artifact-capabilities` | Skill: Referenz der Laufzeitfähigkeiten veröffentlichter Artefakte. |
| `/artifact-diagramming` | Skill: Diagramm-Leitfaden für Artefakte (Inline-SVG, hell und dunkel). |
| `/artifacts` | Eigene und geteilte Artefakte auflisten, anhängen, öffnen, Link kopieren. |
| `/auto-mode-setup` | `autoMode.environment`-Einträge aus Projekt und Sitzungen entwerfen und in User-Settings speichern. |
| **`/autocompact [auto\|<tokens>]`** | Auto-Compact-Fenster setzen (`500k` oder `auto`), speichert in User-Settings. |
| `/autofix-pr [prompt]` | Cloud-Sitzung starten, die den PR des Branches bei CI-Fehlern und Reviews repariert (braucht `gh`). |
| **`/background [prompt]`** (`/bg`) | Sitzung als Hintergrund-Agent lösen und das Terminal freigeben. |
| **`/batch <anweisung>`** | Skill: grosse Änderung in 5–30 unabhängige Einheiten zerlegen, je ein Hintergrund-Subagent im eigenen Worktree. |
| `/branch [name]` | Gespräch an dieser Stelle verzweigen; Original mit `/resume` erreichbar. |
| `/btw [frage]` | Nebenfrage ohne Eintrag ins Gespräch. |
| `/bug [bericht]` (`/share`) | Fehler melden oder Gespräch teilen, mit Einwilligungsschritt. |
| `/cd <pfad>` | Sitzung in anderes Arbeitsverzeichnis verschieben, Gespräch bleibt. |
| `/chrome` | Einstellungen für Claude in Chrome. |
| `/claude-api [migrate\|upgrade\|managed-agents-onboard\|prompt-audit\|cost-optimize\|build-eval\|hillclimb\|preserved-thinking-migration]` | Skill: Claude-API- und Managed-Agents-Referenz laden. |
| `/claude-in-chrome [aufgabe]` | Skill: Aufgabe im eigenen Browser über die Chrome-Erweiterung erledigen. |
| `/clear [name]` (`/reset`, `/new`) | Neues Gespräch mit leerem Kontext; Name beschriftet das alte im `/resume`-Wähler. |
| **`/code-review [low\|medium\|high\|xhigh\|max\|ultra] [--fix] [--comment] [pr#\|branch\|pfad]`** (`/review`) | Skill: Diff, PR, Branch oder Pfad auf Korrektheitsfehler prüfen; `--fix` wendet an, `--comment` postet, `ultra` läuft als Cloud-Review. |
| `/color [farbe\|default]` | Farbe der Eingabezeile. |
| **`/compact [anweisungen]`** | Kontext durch Zusammenfassung freigeben, optional mit Schwerpunkt. |
| **`/config [schlüssel=wert …]`** (`/settings`) | Einstellungsoberfläche; `key=value` setzt direkt (`/config model=sonnet`), `/config --help` listet Schlüssel. |
| `/context [all]` | Kontextnutzung als Raster mit Hinweisen. |
| `/copy [N]` | Letzte (oder N-letzte) Antwort in die Zwischenablage; `w` schreibt in Datei. |
| `/cost` | Alias von `/usage`. |
| `/dataviz [anfrage]` | Skill: Gestaltung von Diagrammen und Dashboards mit Farbprüfung. |
| `/debug [beschreibung]` | Skill: Debug-Protokoll der Sitzung einschalten und auswerten. |
| `/deep-research <frage>` | Workflow: Websuchen fächern, Quellen lesen und gegenprüfen, Bericht mit Belegen. |
| `/design [brief]` | Skill: UI-Mockups als Design-Artefakt (Canvas) entwerfen. |
| `/design-login` | Zugriff auf Design-Systeme für `/design-sync` autorisieren. |
| `/design-sync [hinweis]` | Skill: React-Design-System des Repos nach Claude Design laden (nicht für Blade). |
| `/desktop` (`/app`) | Sitzung in der Desktop-App fortsetzen. |
| `/diff` | Änderungen im Arbeitsbaum ansehen. |
| **`/doctor [prompt-audit [pfad]]`** (`/checkup`) | Skill: Installation prüfen, ungenutzte Skills/MCP/Plugins, langsame Hooks, `CLAUDE.md` kürzen; `prompt-audit` prüft Anweisungsdateien auf Veraltetes und Widersprüche. |
| **`/effort [low\|medium\|high\|xhigh\|max\|auto\|status\|ultracode [on\|off]]`** | Effort setzen oder anzeigen; `ultracode on/off` für die Sitzung (`ultracode`-Schlüssel bleibt gespeichert); `max` nur für die Sitzung. |
| `/exit` (`/quit`) | CLI verlassen; in einer Hintergrundsitzung nur ablösen. |
| `/export [datei]` | Gespräch als Text exportieren. |
| `/fast [on\|off]` | Fast Mode (Opus mit schnellerer Ausgabe) umschalten. |
| `/feedback [bericht]` | Produkt-Feedback senden. |
| `/fewer-permission-prompts` | Skill: Transkripte nach häufigen Nur-Lese-Befehlen durchsuchen und Allow-Liste in `.claude/settings.json` ergänzen. |
| `/focus` | Fokusansicht (nur letzter Prompt, Werkzeugzeile, Antwort) – nur Vollbild-Renderer. |
| **`/fork [prompt]`** | Gespräch in neue Hintergrundsitzung kopieren und hier weiterarbeiten; Kopie legt eigenen Worktree an. |
| **`/goal [bedingung\|clear]`** | Ziel setzen: Claude arbeitet über Runden, bis die Bedingung erfüllt ist; `clear`/`stop`/`off` beendet. |
| `/heapdump` | Heap-Snapshot für Speicherdiagnose (verborgen, enthält das ganze Gespräch). |
| `/help` | Hilfe und Befehle. |
| `/hooks` | Hook-Konfiguration ansehen. |
| `/ide` | IDE-Integrationen verwalten. |
| `/import [codex\|gemini\|cursor] [--dry-run] [--yes]` | Konfiguration aus Codex, Gemini CLI oder Cursor übernehmen. |
| `/init` | `CLAUDE.md` anlegen (`CLAUDE_CODE_NEW_INIT=1` für den interaktiven Ablauf). |
| `/insights` | HTML-Bericht über die eigene Nutzung – nicht in Cloud-Sitzungen. |
| `/install-github-app` | Claude-GitHub-App für ein Repo einrichten (nur github.com). |
| `/install-slack-app` | Claude-Slack-App installieren. |
| `/keybindings` | Datei der Tastenkürzel öffnen. |
| `/list-agents` (`/peers`) | Subagenten, Teammitglieder und andere Sitzungen, die Nachrichten empfangen können. |
| `/login` · `/logout` | An- und abmelden. |
| **`/loop [intervall] [prompt]`** (`/proactive`) | Skill: Prompt wiederholt ausführen; ohne Intervall wählt Claude den Takt, ohne Prompt läuft die Wartung oder `loop.md`. |
| **`/mcp [reconnect <server>\|enable\|disable [<server>\|all]]`** | MCP-Server und OAuth verwalten. |
| **`/memory`** | `CLAUDE.md`-Dateien bearbeiten, Auto-Memory ein/aus, Einträge ansehen. |
| `/mobile` (`/ios`, `/android`) | QR-Code für die mobile App. |
| **`/model [modell]`** | Modell wechseln und als Standard speichern; Pfeiltasten für Effort; `s` nur für diese Sitzung. |
| `/output-style [stil]` | Ausgabestil wählen (`concise` …). |
| `/passes` | Freiwoche teilen (nur wenn berechtigt). |
| **`/permissions`** (`/allowed-tools`) | Allow-/Ask-/Deny-Regeln, Arbeitsverzeichnisse, Auto-Mode-Ablehnungen und -Regeln. |
| `/plan [beschreibung]` | Planmodus betreten, optional direkt mit Aufgabe. |
| **`/plugin [list\|install\|enable\|disable\|…]`** | Plugins verwalten; `install <name>@<marktplatz>`. |
| `/powerup` | Interaktive Lektionen zu Funktionen. |
| `/pr-comments` | Entfernt (2.1.91); Claude direkt fragen. |
| `/privacy-settings` | Datenschutzeinstellungen (Pro/Max). |
| `/radio` | Claude-FM-Radio im Browser. |
| `/rate-limit-options` | Optionen bei erreichtem Nutzungslimit (warten, Credits, Upgrade). |
| `/recap` | Einzeilige Zusammenfassung der Sitzung. |
| `/release-notes` | Änderungsprotokoll nach Version. |
| `/reload-plugins [--force]` | Plugins neu laden ohne Neustart. |
| **`/reload-skills`** | Skill- und Befehlsverzeichnisse neu einlesen. |
| `/remote-control` (`/rc`) | Sitzung für Remote Control von claude.ai freigeben. |
| `/remote-env` | Standard-Cloud-Umgebung für Sitzungen aus dem CLI. |
| `/rename [name]` | Sitzung umbenennen. |
| **`/resume [sitzung]`** (`/continue`) | Gespräch nach ID oder Name fortsetzen oder Wähler öffnen. |
| `/rewind` (`/checkpoint`, `/undo`) | Gespräch und/oder Code auf einen früheren Punkt zurücksetzen. |
| **`/run`** | Skill: die App starten und eine Änderung im Betrieb ansehen. |
| `/run-skill-generator` | Skill: projektspezifischen Skill für `/run` und `/verify` schreiben. |
| `/sandbox` | Sandbox-Modus umschalten (nur unterstützte Plattformen). |
| **`/schedule [beschreibung]`** (`/routines`) | Routinen in der Cloud anlegen, ändern, auflisten, starten. |
| `/scroll-speed` | Scrollgeschwindigkeit (Vollbild). |
| **`/security-review`** | Branch-Diff gegen den Standardbranch auf Sicherheitslücken prüfen (braucht `origin`). |
| `/setup-bedrock` · `/setup-vertex` | Assistenten für Amazon Bedrock bzw. Google Cloud (verborgen, nur mit Umgebungsvariable). |
| **`/simplify [ziel]`** | Skill: vier parallele Agents für Wiederverwendung, Vereinfachung, Effizienz, Abstraktionsebene; wendet Fixes an (keine Fehlersuche – dafür `/code-review`). |
| `/skill-doctor` | Kontextkosten und Nutzung je Skill. |
| **`/skills`** | Skills auflisten, filtern, Sichtbarkeit umschalten. |
| `/slides [brief]` | Skill: Präsentation als Slides-Artefakt. |
| `/stats` | Alias von `/usage` (Stats-Reiter). |
| `/status` | Status-Reiter: Version, Modell, Konto, Verbindung. |
| `/statusline` | Statuszeile konfigurieren. |
| `/stickers` | Sticker bestellen. |
| `/stop` | Laufende Hintergrundsitzung anhalten (nur angehängt). |
| **`/subtask <aufgabe>`** | Geforkter Hintergrund-Subagent mit vollem Gespräch; Ergebnis kommt zurück. |
| **`/tasks`** (`/bashes`) | Hintergrundarbeit der Sitzung ansehen und verwalten. |
| `/team-onboarding` | Onboarding-Leitfaden aus der eigenen Nutzung erzeugen. |
| `/teleport` (`/tp`) | Cloud-Sitzung ins Terminal holen. |
| `/terminal-setup` | Shift+Enter für Zeilenumbruch einrichten. |
| `/theme` | Farbthema (`auto`, hell, dunkel, daltonisiert, ANSI, eigene). |
| `/tui [default\|fullscreen]` | Terminal-Renderer wählen. |
| `/ultraplan` | Entfernt; Planmodus verwenden. |
| `/ultrareview [PR\|branch]` | Tiefe Mehr-Agenten-Review in der Cloud; bevorzugt `/code-review ultra`. |
| **`/update-config [wunsch]`** | Skill: Einstellungsänderung beschreiben, Claude bearbeitet die passende `settings.json`. |
| `/upgrade` | Upgrade-Seite öffnen. |
| **`/usage`** | Kosten, Limits, Aktivität. |
| `/usage-credits` | Nutzungs-Credits konfigurieren oder beim Admin anfragen. |
| **`/verify`** | Skill: Änderung durch Bauen, Starten und Beobachten bestätigen. |
| `/vim` | Entfernt; Editor-Modus über `/config`. |
| `/voice [hold\|tap\|off]` | Sprachdiktat. |
| `/web-setup` | GitHub-Konto für Cloud-Sitzungen über lokale `gh`-Anmeldung verbinden. |
| **`/workflow-authoring`** | Skill: Referenz für dynamische Workflow-Skripte (API, Resume, Muster). |
| **`/workflows`** | Fortschrittsansicht laufender und beendeter Workflows: ansehen, pausieren, fortsetzen, speichern (`s`). |
| **`/notenportal-audit`**, **`/notenportal-dunkel-rundgang`**, **`/weiter`** | Eigene Befehle aus `.claude/workflows/` und `.claude/commands/`. |

---

## 10. Umgebungsvariablen, die hier zählen

| Variable | Wirkung |
|---|---|
| `CLAUDE_CODE_REMOTE=true` | Von der Cloud gesetzt; der Hook tut nur dann etwas. |
| `CLAUDE_CODE_EFFORT_LEVEL` | Effort der Sitzung (`low`…`max`, `auto`), überschreibt Dateien. |
| `ANTHROPIC_MODEL` · `ANTHROPIC_DEFAULT_MODEL` · `ANTHROPIC_DEFAULT_OPUS_MODEL` | Modell der Sitzung bzw. Standard und Opus-Zuordnung. |
| `CLAUDE_CODE_SUBAGENT_MODEL` | Modell aller Subagenten ohne eigenes `model`. |
| `MAX_THINKING_TOKENS` | Denkbudget, `0` schaltet ab. `alwaysThinkingEnabled: false` hat auf Fable, Opus 5.5 und Sonnet 5.5 keine Wirkung. |
| `CLAUDE_CODE_MAX_SUBAGENT_SPAWN_DEPTH=1` | Cloud: Subagenten starten keine Subagenten. |
| `CLAUDE_AUTOCOMPACT_PCT_OVERRIDE=80` · `CLAUDE_CODE_AUTO_COMPACT_WINDOW` | Cloud-Vorgabe für die Verdichtung; die Tokenzahl überschreibt `autoCompactWindow`. |
| `CLAUDE_CODE_DISABLE_WORKFLOWS=1` | Workflows für eine Sitzung aus (Datei: `disableWorkflows`, `enableWorkflows`). |
| `CLAUDE_CODE_DISABLE_ADVISOR_TOOL` | Advisor aus. |
| `CLAUDE_CODE_DISABLE_AUTO_MEMORY` | Auto-Memory aus (Datei: `autoMemoryEnabled`). |
| `FORCE_PROMPT_CACHING_5M` · `CLAUDE_CODE_PROMPT_CACHE_TTL` · `CLAUDE_CODE_SUBAGENT_PROMPT_CACHE_TTL` | Cache-Lebensdauer, Vorrang vor `promptCacheTtl`/`subagentPromptCacheTtl`. |
| `CLAUDE_CODE_EXPERIMENTAL_AGENT_TEAMS=1` | Agent-Teams (experimentell, hier nicht genutzt). |
| `NP_TEST_PW` | Einziger Weg, den Prüfwerkzeugen ein Passwort zu geben. |
| `CLAUDE_ENV_FILE` · `CLAUDE_PROJECT_DIR` | Vom Hook-System gesetzt: Variablendatei der Sitzung, Repo-Wurzel. |

---

## 11. Hooks

Ereignisse laut Dokumentation: `SessionStart`, `Setup`, `SessionEnd`, `UserPromptSubmit`,
`UserPromptExpansion`, `Stop`, `StopFailure`, `PreToolUse`, `PermissionRequest`, `PermissionDenied`,
`PostToolUse`, `PostToolUseFailure`, `PostToolBatch` (weitere wie `PreCompact`, `TaskCreated`,
`TeammateIdle`, `DirectoryAdded` existieren, sind hier nicht geprüft). Typen `command`, `http`,
`mcp_tool`, `prompt`, `agent`. Exit 0 = ok, 2 = blockierend, anderes = Meldung ohne Block.

Im Repo: `SessionStart` → `session-start.sh` (Timeout 900 s, synchron – damit nichts läuft, bevor
Datenbank und Demo-Server da sind); `PostToolUse` mit `matcher: Edit|Write|MultiEdit` →
`view-pruefung.sh` (Timeout 10 s). Beide über `"$CLAUDE_PROJECT_DIR"/.claude/hooks/…`.

---

## 12. Widersprüche und Lücken in der Dokumentation

Hier steht, was sich nicht sauber belegen liess. Nichts davon ist als Tatsache in Dateien gelandet.

1. **`autoCompactWindow`:** `settings-reference` vom 01.10. sagt «number of tokens, from 100000 to
   1000000»; ein Prüfer aus dem Recherche-Workflow behauptete einen Bruch 0–1. Übernommen ist die
   Primärquelle (Tokenzahl). `~/.claude/settings.json` im Container trägt 300000.
2. **`permissions.defaultMode`:** sieben Werte in der Referenz, vier in der Cloud, `auto` nicht aus
   Projektdateien. Ob das Menü der Cloud-Sitzung dasselbe `auto` ist, steht nicht explizit da.
3. **`effortLevel`:** Projektdatei gilt für jedes Modell, User-Datei nicht für Opus 5.5+. Ob
   `modelSettings` in der Projektdatei zusätzlich nötig ist, sagt die Referenz nicht; es ist
   deshalb beides gesetzt.
4. **`effort: max` im Skill-Frontmatter:** Agents dokumentieren `low`…`max`, Skills `low`…`xhigh`;
   `max` für Skills ungeklärt.
5. **`CLAUDE_CODE_VERSION`** in der Sitzung meldet 2.1.42, die CLI 2.1.286. Massgebend ist die CLI.
6. **Plugins:** `enabledPlugins` nennt Marktplätze, die nur David lokal hat; Quellen unbekannt.
7. **Hook-Ereignisse:** vollständige Liste und die Managed-Settings-Schlüssel nicht geprüft.
8. **Workflow-Agenten und `agentType`:** dass ein Projekt-Agent (`bildpruefer`) als `agentType` in
   `agent()` funktioniert, steht in der Workflow-Referenz. Im Rauchtest vom 01.10. schlug der Aufruf
   fehl, weil `bildpruefer.md` erst während derselben Sitzung angelegt worden war und die Sitzung
   ihre Agent-Liste nur beim Start liest; der Ersatz (`opus xhigh` mit denselben Anweisungen) griff.
   In einer frischen Sitzung ist der Agent registriert.
9. **`permissionMode` im Agent-Frontmatter:** die Referenz belegt nur indirekt (über
   `disableBypassPermissionsMode`), dass ein Agent `bypassPermissions` setzen kann; die
   Subagent-Seite selbst wurde nicht gelesen. Im Repo setzt kein Agent den Schlüssel.
10. **Plugin-Sichtbarkeit:** ob eine laufende Sitzung ein vom Hook installiertes Plugin ohne
    `/reload-plugins` lädt, ist nicht gemessen.
11. **Nutzungslimit in Workflows (gemessen 01.10., 09:20 UTC):** erreicht die Sitzung ihr Limit,
    bricht jeder weitere `agent()`-Aufruf mit «You've hit your session limit» ab, das Journal
    führt ihn als `failed` ohne `agentId`, und `agent()` liefert `null`. Im Rauchtest von
    `notenportal-dunkel-rundgang` fielen so 22 von 29 Agenten aus – fast alle Prüfer, weil die
    parallelen `opus xhigh`-Verifikationen das Limit in Minuten aufbrauchen. Folgen für Skripte:
    `null` eines Prüfers heisst «ungeprüft», nie «verworfen»; Prüfer laufen in Bündeln statt alle
    gleichzeitig; nach dem Reset mit `resumeFromRunId` fortsetzen, die fertigen Befunde kommen aus
    dem Cache. Wie viel Kontingent eine Fable-Sitzung je Tag hat, zeigt keine Dokumentation; nur
    die Meldung mit der Reset-Zeit.

---

## 13. Was nicht automatisiert ist

- Berechtigungsmodus der Cloud-Sitzung (Menü) und `~/.claude/settings.json` lokal – einmal von Hand.
- `/model fable` zur Einwilligung in den Kreditverbrauch – einmal von Hand.
- Marktplatz-Quellen für Davids lokale Plugins (Abschnitt 8).
- Rotation des Prod-Testpassworts aus Commit `171ed72`.
- Entscheide mit Aussenwirkung (Produkt, Recht, Lizenz) – «Offen für David» in `UEBERGABE.md`.
