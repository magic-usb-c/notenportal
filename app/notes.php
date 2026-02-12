<?php
declare(strict_types=1);

/*
  app/notes.php
  Zweck:
  - DB-Zugriffe + Permission-Checks rund um Noten
  - inkl. Kommentare + "Gesehen"-Markierung
  - angepasst an neues Schema:
    - noten.modul_belegung_id (statt modul_id)
    - optional noten.gruppe_id, noten.titel
    - erfasst_von_benutzer_id / aktualisiert_von_benutzer_id
*/

function can_manage_notes(array $ctx): bool
{
    // "manage" = Noten erfassen/bearbeiten/löschen (nicht nur ansehen)
    return !empty($ctx['is_admin']) || !empty($ctx['lernender_id']);
}

function can_manage_note(array $ctx, int $noteLernenderId): bool
{
    if (!empty($ctx['is_admin'])) return true;
    if (!empty($ctx['lernender_id']) && (int)$ctx['lernender_id'] === $noteLernenderId) return true;
    return false;
}

/**
 * Optionen fürs Note-Formular.
 * Wichtig: Für Modul-Noten braucht man modul_belegung_id.
 *
 * Rückgabe-Keys:
 * - kategorien, semester, faecher, modul_belegungen, gruppen (alle Gruppen), lernende (nur Admin)
 */
function load_note_form_options(PDO $pdo, bool $isAdmin, ?int $lernenderId = null): array
{
    $opt = [
        'kategorien' => [],
        'semester' => [],
        'faecher' => [],
        'modul_belegungen' => [],
        'gruppen' => [],
        'lernende' => [],
    ];

    $opt['kategorien'] = $pdo->query('SELECT kategorie_id, name FROM kategorien ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $opt['semester']   = $pdo->query('SELECT semester_id, bezeichnung FROM semester ORDER BY semester_id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $opt['faecher']    = $pdo->query('SELECT fach_id, name FROM faecher ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Modul-Belegungen (für Dropdown bei Modul-Noten)
    // Admin: alle Belegungen (inkl. Lernendenname)
    // Lernender: nur eigene
    if ($isAdmin) {
        $sql = '
            SELECT mb.modul_belegung_id,
                   mb.lernender_id,
                   m.modul_nummer,
                   m.titel,
                   CONCAT(m.modul_nummer, " ", m.titel, " (", b.benutzername, ")") AS label
            FROM modul_belegungen mb
            JOIN module m ON m.modul_id = mb.modul_id
            JOIN lernende l ON l.lernender_id = mb.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            WHERE l.geloescht_am IS NULL
            ORDER BY m.modul_nummer, m.titel, b.benutzername
        ';
        $opt['modul_belegungen'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->query(
            'SELECT l.lernender_id, b.benutzername
             FROM lernende l
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             WHERE l.geloescht_am IS NULL
             ORDER BY b.benutzername'
        );
        $opt['lernende'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        if ($lernenderId) {
            $stmt = $pdo->prepare(
                'SELECT mb.modul_belegung_id,
                        mb.lernender_id,
                        m.modul_nummer,
                        m.titel,
                        CONCAT(m.modul_nummer, " ", m.titel) AS label
                 FROM modul_belegungen mb
                 JOIN module m ON m.modul_id = mb.modul_id
                 WHERE mb.lernender_id = :lid
                 ORDER BY m.modul_nummer, m.titel'
            );
            $stmt->execute([':lid' => (int)$lernenderId]);
            $opt['modul_belegungen'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    // Gruppen (optional) – alle Gruppen laden, später kannst du UI-seitig nach Belegung filtern
    $sqlGroups = '
        SELECT g.gruppe_id, g.modul_belegung_id, g.bezeichnung
        FROM modul_note_gruppen g
        ORDER BY g.modul_belegung_id, g.bezeichnung
    ';
    $opt['gruppen'] = $pdo->query($sqlGroups)->fetchAll(PDO::FETCH_ASSOC) ?: [];

    return $opt;
}

function fetch_note(PDO $pdo, int $noteId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT note_id, lernender_id, kategorie_id, semester_id,
                fach_id, modul_belegung_id, gruppe_id, titel,
                pruefungsdatum, note_wert, gewichtung_prozent
         FROM noten
         WHERE note_id = :id AND geloescht_am IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $noteId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function insert_note(PDO $pdo, array $data, int $createdByUserId): int
{
    // Backward-Compat falls irgendwo noch "modul_id" kommt:
    $modulBelegungId = $data['modul_belegung_id'] ?? ($data['modul_id'] ?? null);

    $stmt = $pdo->prepare(
        'INSERT INTO noten (
            lernender_id, kategorie_id, semester_id,
            fach_id, modul_belegung_id, gruppe_id,
            titel, pruefungsdatum, note_wert, gewichtung_prozent,
            erfasst_von_benutzer_id
         ) VALUES (
            :lernender_id, :kategorie_id, :semester_id,
            :fach_id, :modul_belegung_id, :gruppe_id,
            :titel, :pruefungsdatum, :note_wert, :gewichtung_prozent,
            :erfasst_von_benutzer_id
         )'
    );

    $stmt->execute([
        ':lernender_id' => (int)$data['lernender_id'],
        ':kategorie_id' => (int)$data['kategorie_id'],
        ':semester_id' => (int)$data['semester_id'],
        ':fach_id' => !empty($data['fach_id']) ? (int)$data['fach_id'] : null,
        ':modul_belegung_id' => !empty($modulBelegungId) ? (int)$modulBelegungId : null,
        ':gruppe_id' => !empty($data['gruppe_id']) ? (int)$data['gruppe_id'] : null,
        ':titel' => (isset($data['titel']) && $data['titel'] !== '') ? (string)$data['titel'] : null,
        ':pruefungsdatum' => (string)$data['pruefungsdatum'],
        ':note_wert' => (string)$data['note_wert'],
        ':gewichtung_prozent' => ($data['gewichtung_prozent'] === null ? null : (string)$data['gewichtung_prozent']),
        ':erfasst_von_benutzer_id' => $createdByUserId,
    ]);

    return (int)$pdo->lastInsertId();
}

function update_note(PDO $pdo, int $noteId, array $data, ?int $updatedByUserId = null): void
{
    $modulBelegungId = $data['modul_belegung_id'] ?? ($data['modul_id'] ?? null);

    $stmt = $pdo->prepare(
        'UPDATE noten
         SET lernender_id = :lernender_id,
             kategorie_id = :kategorie_id,
             semester_id = :semester_id,
             fach_id = :fach_id,
             modul_belegung_id = :modul_belegung_id,
             gruppe_id = :gruppe_id,
             titel = :titel,
             pruefungsdatum = :pruefungsdatum,
             note_wert = :note_wert,
             gewichtung_prozent = :gewichtung_prozent,
             aktualisiert_von_benutzer_id = :aktualisiert_von_benutzer_id
         WHERE note_id = :id AND geloescht_am IS NULL
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $noteId,
        ':lernender_id' => (int)$data['lernender_id'],
        ':kategorie_id' => (int)$data['kategorie_id'],
        ':semester_id' => (int)$data['semester_id'],
        ':fach_id' => !empty($data['fach_id']) ? (int)$data['fach_id'] : null,
        ':modul_belegung_id' => !empty($modulBelegungId) ? (int)$modulBelegungId : null,
        ':gruppe_id' => !empty($data['gruppe_id']) ? (int)$data['gruppe_id'] : null,
        ':titel' => (isset($data['titel']) && $data['titel'] !== '') ? (string)$data['titel'] : null,
        ':pruefungsdatum' => (string)$data['pruefungsdatum'],
        ':note_wert' => (string)$data['note_wert'],
        ':gewichtung_prozent' => ($data['gewichtung_prozent'] === null ? null : (string)$data['gewichtung_prozent']),
        ':aktualisiert_von_benutzer_id' => $updatedByUserId,
    ]);
}

function soft_delete_note(PDO $pdo, int $noteId): void
{
    $stmt = $pdo->prepare(
        'UPDATE noten
         SET geloescht_am = NOW()
         WHERE note_id = :id AND geloescht_am IS NULL
         LIMIT 1'
    );
    $stmt->execute([':id' => $noteId]);
}

/**
 * Holt eine Note “mit Kontext” und macht den Permission-Check direkt über SQL.
 * Gibt null zurück, wenn Note nicht existiert ODER der User keinen Zugriff hat.
 *
 * Für Berufsbildner: liefert zusätzlich "gelesen_am" (NULL wenn noch nicht gesehen).
 */
function fetch_note_detail_for_ctx(PDO $pdo, array $ctx, int $noteId): ?array
{
    $baseSelect = '
        SELECT n.note_id, n.lernender_id, n.kategorie_id, n.semester_id,
               n.fach_id, n.modul_belegung_id, n.gruppe_id, n.titel,
               n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
               k.name AS kategorie,
               s.bezeichnung AS semester,
               f.name AS fach_name,
               m.modul_nummer AS modul_nummer,
               m.titel AS modul_titel,
               g.bezeichnung AS gruppen_name,
               %s
               %s AS gelesen_am
        FROM noten n
        JOIN kategorien k ON k.kategorie_id = n.kategorie_id
        JOIN semester s ON s.semester_id = n.semester_id
        LEFT JOIN faecher f ON f.fach_id = n.fach_id
        LEFT JOIN modul_belegungen mb ON mb.modul_belegung_id = n.modul_belegung_id
        LEFT JOIN module m ON m.modul_id = mb.modul_id
        LEFT JOIN modul_note_gruppen g ON g.gruppe_id = n.gruppe_id AND g.modul_belegung_id = n.modul_belegung_id
    ';

    if (!empty($ctx['is_admin'])) {
        $sql = sprintf($baseSelect, 'b.benutzername AS lernender_username,', 'NULL');
        $sql .= '
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            WHERE n.note_id = :id AND n.geloescht_am IS NULL
            LIMIT 1
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $noteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    if (!empty($ctx['lernender_id'])) {
        $sql = sprintf($baseSelect, 'NULL AS lernender_username,', 'NULL');
        $sql .= '
            WHERE n.note_id = :id
              AND n.geloescht_am IS NULL
              AND n.lernender_id = :lid
            LIMIT 1
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $noteId,
            ':lid' => (int)$ctx['lernender_id'],
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    if (!empty($ctx['berufsbildner_id'])) {
        $sql = sprintf($baseSelect, 'b.benutzername AS lernender_username,', 'ng.gesehen_am');
        $sql .= '
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            JOIN betreuungen bt ON bt.lernender_id = n.lernender_id
            LEFT JOIN (
                SELECT note_id, berufsbildner_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                GROUP BY note_id, berufsbildner_id
            ) ng
              ON ng.note_id = n.note_id
             AND ng.berufsbildner_id = :bbid_join
            WHERE n.note_id = :id
              AND n.geloescht_am IS NULL
              AND bt.berufsbildner_id = :bbid_where
              AND bt.gueltig_von <= CURDATE()
              AND (bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())
            LIMIT 1
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id' => $noteId,
            ':bbid_join' => (int)$ctx['berufsbildner_id'],
            ':bbid_where' => (int)$ctx['berufsbildner_id'],
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    return null;
}

function fetch_note_comments(PDO $pdo, int $noteId): array
{
    $stmt = $pdo->prepare(
        'SELECT nk.kommentar_id, nk.kommentar_text, nk.erstellt_am,
                b.benutzername AS autor_username
         FROM noten_kommentare nk
         JOIN benutzer b ON b.benutzer_id = nk.autor_benutzer_id
         WHERE nk.note_id = :nid
         ORDER BY nk.erstellt_am ASC, nk.kommentar_id ASC'
    );
    $stmt->execute([':nid' => $noteId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function add_note_comment(PDO $pdo, int $noteId, int $authorUserId, string $text): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO noten_kommentare (note_id, autor_benutzer_id, kommentar_text)
         VALUES (:nid, :uid, :txt)'
    );
    $stmt->execute([
        ':nid' => $noteId,
        ':uid' => $authorUserId,
        ':txt' => $text,
    ]);
}

function mark_note_seen(PDO $pdo, int $noteId, int $berufsbildnerId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO noten_gesehen (note_id, berufsbildner_id, gesehen_am)
         SELECT :nid1, :bbid1, NOW()
         WHERE NOT EXISTS (
            SELECT 1 FROM noten_gesehen
            WHERE note_id = :nid2 AND berufsbildner_id = :bbid2
         )'
    );
    $stmt->execute([
        ':nid1' => $noteId,
        ':bbid1' => $berufsbildnerId,
        ':nid2' => $noteId,
        ':bbid2' => $berufsbildnerId,
    ]);
}

function load_note_filter_options(PDO $pdo, array $ctx): array
{
    $opt = [
        'kategorien' => $pdo->query('SELECT kategorie_id, name FROM kategorien ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'semester'   => $pdo->query('SELECT semester_id, bezeichnung FROM semester ORDER BY semester_id')->fetchAll(PDO::FETCH_ASSOC) ?: [],
        'faecher'    => $pdo->query('SELECT fach_id, name FROM faecher ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [],
        // Für Filter: wir liefern Modul-Belegungen (weil Noten darauf referenzieren)
        'modul_belegungen' => [],
        'lernende'   => [],
    ];

    if (!empty($ctx['is_admin'])) {
        $sql = '
            SELECT mb.modul_belegung_id, mb.lernender_id,
                   m.modul_nummer, m.titel,
                   CONCAT(m.modul_nummer, " ", m.titel, " (", b.benutzername, ")") AS label
            FROM modul_belegungen mb
            JOIN module m ON m.modul_id = mb.modul_id
            JOIN lernende l ON l.lernender_id = mb.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            WHERE l.geloescht_am IS NULL
            ORDER BY m.modul_nummer, m.titel, b.benutzername
        ';
        $opt['modul_belegungen'] = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $stmt = $pdo->query(
            'SELECT l.lernender_id, b.benutzername
             FROM lernende l
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             WHERE l.geloescht_am IS NULL
             ORDER BY b.benutzername'
        );
        $opt['lernende'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $opt;
    }

    if (!empty($ctx['berufsbildner_id'])) {
        $stmt = $pdo->prepare(
            'SELECT DISTINCT mb.modul_belegung_id, mb.lernender_id,
                    m.modul_nummer, m.titel,
                    CONCAT(m.modul_nummer, " ", m.titel, " (", b.benutzername, ")") AS label
             FROM betreuungen bt
             JOIN lernende l ON l.lernender_id = bt.lernender_id
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             LEFT JOIN modul_belegungen mb ON mb.lernender_id = l.lernender_id
             LEFT JOIN module m ON m.modul_id = mb.modul_id
             WHERE l.geloescht_am IS NULL
               AND bt.berufsbildner_id = :bbid
               AND bt.gueltig_von <= CURDATE()
               AND (bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())
             ORDER BY b.benutzername, m.modul_nummer, m.titel'
        );
        $stmt->execute([':bbid' => (int)$ctx['berufsbildner_id']]);
        $opt['modul_belegungen'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Lernende-Liste wie vorher (für Berufsbildner Filter)
        $stmt2 = $pdo->prepare(
            'SELECT DISTINCT l.lernender_id, b.benutzername
             FROM betreuungen bt
             JOIN lernende l ON l.lernender_id = bt.lernender_id
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             WHERE l.geloescht_am IS NULL
               AND bt.berufsbildner_id = :bbid
               AND bt.gueltig_von <= CURDATE()
               AND (bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())
             ORDER BY b.benutzername'
        );
        $stmt2->execute([':bbid' => (int)$ctx['berufsbildner_id']]);
        $opt['lernende'] = $stmt2->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // Lernender: eigene Belegungen (nice-to-have fürs Filter-UI)
    if (!empty($ctx['lernender_id'])) {
        $stmt = $pdo->prepare(
            'SELECT mb.modul_belegung_id, mb.lernender_id,
                    m.modul_nummer, m.titel,
                    CONCAT(m.modul_nummer, " ", m.titel) AS label
             FROM modul_belegungen mb
             JOIN module m ON m.modul_id = mb.modul_id
             WHERE mb.lernender_id = :lid
             ORDER BY m.modul_nummer, m.titel'
        );
        $stmt->execute([':lid' => (int)$ctx['lernender_id']]);
        $opt['modul_belegungen'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $opt;
}

function fetch_notes_list_for_ctx_filtered(PDO $pdo, array $ctx, array $filters, string $sort): array
{
    $isAdmin = !empty($ctx['is_admin']);
    $isLearner = !empty($ctx['lernender_id']);
    $isBb = !empty($ctx['berufsbildner_id']);

    $sortMap = [
        'date_desc' => 'n.pruefungsdatum DESC, n.note_id DESC',
        'date_asc'  => 'n.pruefungsdatum ASC, n.note_id ASC',
        'note_desc' => 'n.note_wert DESC, n.pruefungsdatum DESC',
        'note_asc'  => 'n.note_wert ASC, n.pruefungsdatum DESC',
        'obj_asc'   => 'objekt_name ASC, n.pruefungsdatum DESC',
        'obj_desc'  => 'objekt_name DESC, n.pruefungsdatum DESC',
    ];
    $orderBy = $sortMap[$sort] ?? $sortMap['date_desc'];

    $where = ['n.geloescht_am IS NULL'];
    $params = [];

    // Filter
    if (!empty($filters['kategorie_id'])) {
        $where[] = 'n.kategorie_id = :kategorie_id';
        $params[':kategorie_id'] = (int)$filters['kategorie_id'];
    }
    if (!empty($filters['semester_id'])) {
        $where[] = 'n.semester_id = :semester_id';
        $params[':semester_id'] = (int)$filters['semester_id'];
    }
    if (!empty($filters['fach_id'])) {
        $where[] = 'n.fach_id = :fach_id';
        $params[':fach_id'] = (int)$filters['fach_id'];
    }

    // neues Feld: modul_belegung_id
    $filterBelegung = $filters['modul_belegung_id'] ?? null;

    // Backward-Compat: falls UI noch "modul_id" sendet -> filtert über JOIN module
    $filterModulId = $filters['modul_id'] ?? null;

    if (!empty($filterBelegung)) {
        $where[] = 'n.modul_belegung_id = :mbid';
        $params[':mbid'] = (int)$filterBelegung;
    }

    $seenFilter = (string)($filters['seen'] ?? 'all');
    if (!in_array($seenFilter, ['all', 'yes', 'no'], true)) $seenFilter = 'all';

    // letzter Kommentar pro Note
    $lastCommentJoin = '
        LEFT JOIN (
            SELECT note_id, MAX(kommentar_id) AS last_kommentar_id
            FROM noten_kommentare
            GROUP BY note_id
        ) lk ON lk.note_id = n.note_id
        LEFT JOIN noten_kommentare nk ON nk.kommentar_id = lk.last_kommentar_id
        LEFT JOIN benutzer cb ON cb.benutzer_id = nk.autor_benutzer_id
    ';

    // Objektname: Fachname oder Modulnummer+Titel (aus Belegung)
    $objektExpr = '
        CASE
          WHEN n.fach_id IS NOT NULL THEN f.name
          ELSE CONCAT(m.modul_nummer, " ", m.titel)
        END
    ';

    $baseFrom = '
        FROM noten n
        JOIN kategorien k ON k.kategorie_id = n.kategorie_id
        JOIN semester s ON s.semester_id = n.semester_id
        LEFT JOIN faecher f ON f.fach_id = n.fach_id
        LEFT JOIN modul_belegungen mb ON mb.modul_belegung_id = n.modul_belegung_id
        LEFT JOIN module m ON m.modul_id = mb.modul_id
    ';

    // Falls alter Filter modul_id genutzt wird
    if (!empty($filterModulId)) {
        $where[] = 'm.modul_id = :modul_id';
        $params[':modul_id'] = (int)$filterModulId;
    }

    // Seen-Joins
    if ($isAdmin) {
        $seenJoin = '
            LEFT JOIN (
                SELECT note_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                GROUP BY note_id
            ) ng ON ng.note_id = n.note_id
        ';

        if (!empty($filters['lernender_id'])) {
            $where[] = 'n.lernender_id = :lid_filter';
            $params[':lid_filter'] = (int)$filters['lernender_id'];
        }

        if ($seenFilter === 'yes') $where[] = 'ng.gesehen_am IS NOT NULL';
        if ($seenFilter === 'no')  $where[] = 'ng.gesehen_am IS NULL';

        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   ' . $objektExpr . ' AS objekt_name,
                   s.bezeichnung AS semester,
                   b.benutzername AS lernender_username,
                   ng.gesehen_am AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            ' . $baseFrom . '
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            ' . $seenJoin . $lastCommentJoin . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy . '
            LIMIT 200
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($isLearner) {
        $where[] = 'n.lernender_id = :lid_role';
        $params[':lid_role'] = (int)$ctx['lernender_id'];

        $seenJoin = '
            LEFT JOIN (
                SELECT note_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                GROUP BY note_id
            ) ng ON ng.note_id = n.note_id
        ';

        if ($seenFilter === 'yes') $where[] = 'ng.gesehen_am IS NOT NULL';
        if ($seenFilter === 'no')  $where[] = 'ng.gesehen_am IS NULL';

        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   ' . $objektExpr . ' AS objekt_name,
                   s.bezeichnung AS semester,
                   NULL AS lernender_username,
                   ng.gesehen_am AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            ' . $baseFrom . '
            ' . $seenJoin . $lastCommentJoin . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy . '
            LIMIT 200
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if ($isBb) {
        $seenJoin = '
            LEFT JOIN (
                SELECT note_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                WHERE berufsbildner_id = :bbid_seen
                GROUP BY note_id
            ) ng ON ng.note_id = n.note_id
        ';
        $params[':bbid_seen'] = (int)$ctx['berufsbildner_id'];

        $where[] = 'bt.berufsbildner_id = :bbid_where';
        $params[':bbid_where'] = (int)$ctx['berufsbildner_id'];
        $where[] = 'bt.gueltig_von <= CURDATE()';
        $where[] = '(bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())';

        if (!empty($filters['lernender_id'])) {
            $where[] = 'n.lernender_id = :lid_filter';
            $params[':lid_filter'] = (int)$filters['lernender_id'];
        }

        if ($seenFilter === 'yes') $where[] = 'ng.gesehen_am IS NOT NULL';
        if ($seenFilter === 'no')  $where[] = 'ng.gesehen_am IS NULL';

        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   ' . $objektExpr . ' AS objekt_name,
                   s.bezeichnung AS semester,
                   b.benutzername AS lernender_username,
                   ng.gesehen_am AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            ' . $baseFrom . '
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            JOIN betreuungen bt ON bt.lernender_id = n.lernender_id
            ' . $seenJoin . $lastCommentJoin . '
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY ' . $orderBy . '
            LIMIT 200
        ';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return [];
}

/**
 * Simple default list (ohne Filter). Beibehalten für Kompatibilität.
 */
function fetch_notes_list_for_ctx(PDO $pdo, array $ctx): array
{
    return fetch_notes_list_for_ctx_filtered($pdo, $ctx, [], 'date_desc');
}
