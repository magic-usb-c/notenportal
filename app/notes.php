<?php
declare(strict_types=1);

/*
  app/notes.php
  Zweck:
  - DB-Zugriffe + Permission-Checks rund um Noten
  - inkl. Kommentare + "Gesehen"-Markierung
*/

function can_manage_notes(array $ctx): bool
{
    return !empty($ctx['is_admin']) || !empty($ctx['lernender_id']);
}

function can_manage_note(array $ctx, int $noteLernenderId): bool
{
    if (!empty($ctx['is_admin'])) return true;
    if (!empty($ctx['lernender_id']) && (int)$ctx['lernender_id'] === $noteLernenderId) return true;
    return false;
}

function load_note_form_options(PDO $pdo, bool $isAdmin): array
{
    $opt = [
        'kategorien' => [],
        'semester' => [],
        'faecher' => [],
        'module' => [],
        'lernende' => [],
    ];

    $opt['kategorien'] = $pdo->query('SELECT kategorie_id, name FROM kategorien ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $opt['semester']   = $pdo->query('SELECT semester_id, bezeichnung FROM semester ORDER BY semester_id')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $opt['faecher']    = $pdo->query('SELECT fach_id, name FROM faecher ORDER BY name')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $opt['module']     = $pdo->query('SELECT modul_id, titel FROM module ORDER BY titel')->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($isAdmin) {
        $stmt = $pdo->query(
            'SELECT l.lernender_id, b.benutzername
             FROM lernende l
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             WHERE l.geloescht_am IS NULL
             ORDER BY b.benutzername'
        );
        $opt['lernende'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return $opt;
}

function fetch_note(PDO $pdo, int $noteId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT note_id, lernender_id, kategorie_id, semester_id, fach_id, modul_id,
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
    $stmt = $pdo->prepare(
        'INSERT INTO noten (
            lernender_id, kategorie_id, semester_id, fach_id, modul_id,
            pruefungsdatum, note_wert, gewichtung_prozent,
            erstellt_von_benutzer_id
         ) VALUES (
            :lernender_id, :kategorie_id, :semester_id, :fach_id, :modul_id,
            :pruefungsdatum, :note_wert, :gewichtung_prozent,
            :erstellt_von_benutzer_id
         )'
    );

    $stmt->execute([
        ':lernender_id' => (int)$data['lernender_id'],
        ':kategorie_id' => (int)$data['kategorie_id'],
        ':semester_id' => (int)$data['semester_id'],
        ':fach_id' => $data['fach_id'] ? (int)$data['fach_id'] : null,
        ':modul_id' => $data['modul_id'] ? (int)$data['modul_id'] : null,
        ':pruefungsdatum' => (string)$data['pruefungsdatum'],
        ':note_wert' => (string)$data['note_wert'],
        ':gewichtung_prozent' => ($data['gewichtung_prozent'] === null ? null : (string)$data['gewichtung_prozent']),
        ':erstellt_von_benutzer_id' => $createdByUserId,
    ]);

    return (int)$pdo->lastInsertId();
}

function update_note(PDO $pdo, int $noteId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE noten
         SET lernender_id = :lernender_id,
             kategorie_id = :kategorie_id,
             semester_id = :semester_id,
             fach_id = :fach_id,
             modul_id = :modul_id,
             pruefungsdatum = :pruefungsdatum,
             note_wert = :note_wert,
             gewichtung_prozent = :gewichtung_prozent
         WHERE note_id = :id AND geloescht_am IS NULL
         LIMIT 1'
    );

    $stmt->execute([
        ':id' => $noteId,
        ':lernender_id' => (int)$data['lernender_id'],
        ':kategorie_id' => (int)$data['kategorie_id'],
        ':semester_id' => (int)$data['semester_id'],
        ':fach_id' => $data['fach_id'] ? (int)$data['fach_id'] : null,
        ':modul_id' => $data['modul_id'] ? (int)$data['modul_id'] : null,
        ':pruefungsdatum' => (string)$data['pruefungsdatum'],
        ':note_wert' => (string)$data['note_wert'],
        ':gewichtung_prozent' => ($data['gewichtung_prozent'] === null ? null : (string)$data['gewichtung_prozent']),
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
    if (!empty($ctx['is_admin'])) {
        $stmt = $pdo->prepare(
            'SELECT n.note_id, n.lernender_id, n.kategorie_id, n.semester_id, n.fach_id, n.modul_id,
                    n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                    k.name AS kategorie,
                    s.bezeichnung AS semester,
                    f.name AS fach_name,
                    m.titel AS modul_titel,
                    b.benutzername AS lernender_username,
                    NULL AS gelesen_am
             FROM noten n
             JOIN kategorien k ON k.kategorie_id = n.kategorie_id
             JOIN semester s ON s.semester_id = n.semester_id
             JOIN lernende l ON l.lernender_id = n.lernender_id
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             LEFT JOIN faecher f ON f.fach_id = n.fach_id
             LEFT JOIN module m ON m.modul_id = n.modul_id
             WHERE n.note_id = :id AND n.geloescht_am IS NULL
             LIMIT 1'
        );
        $stmt->execute([':id' => $noteId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    if (!empty($ctx['lernender_id'])) {
        $stmt = $pdo->prepare(
            'SELECT n.note_id, n.lernender_id, n.kategorie_id, n.semester_id, n.fach_id, n.modul_id,
                    n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                    k.name AS kategorie,
                    s.bezeichnung AS semester,
                    f.name AS fach_name,
                    m.titel AS modul_titel,
                    NULL AS lernender_username,
                    NULL AS gelesen_am
             FROM noten n
             JOIN kategorien k ON k.kategorie_id = n.kategorie_id
             JOIN semester s ON s.semester_id = n.semester_id
             LEFT JOIN faecher f ON f.fach_id = n.fach_id
             LEFT JOIN module m ON m.modul_id = n.modul_id
             WHERE n.note_id = :id
               AND n.geloescht_am IS NULL
               AND n.lernender_id = :lid
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $noteId,
            ':lid' => (int)$ctx['lernender_id'],
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    if (!empty($ctx['berufsbildner_id'])) {
        $stmt = $pdo->prepare(
            'SELECT n.note_id, n.lernender_id, n.kategorie_id, n.semester_id, n.fach_id, n.modul_id,
                    n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                    k.name AS kategorie,
                    s.bezeichnung AS semester,
                    f.name AS fach_name,
                    m.titel AS modul_titel,
                    b.benutzername AS lernender_username,
                    ng.gesehen_am AS gelesen_am
             FROM noten n
             JOIN kategorien k ON k.kategorie_id = n.kategorie_id
             JOIN semester s ON s.semester_id = n.semester_id
             JOIN lernende l ON l.lernender_id = n.lernender_id
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             JOIN betreuungen bt ON bt.lernender_id = n.lernender_id
             LEFT JOIN faecher f ON f.fach_id = n.fach_id
             LEFT JOIN module m ON m.modul_id = n.modul_id
             LEFT JOIN (
                SELECT note_id, berufsbildner_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                GROUP BY note_id, berufsbildner_id
             ) ng
             ON ng.note_id = n.note_id
             AND ng.berufsbildner_id = :bbid

             WHERE n.note_id = :id
               AND n.geloescht_am IS NULL
               AND bt.berufsbildner_id = :bbid
               AND bt.gueltig_von <= CURDATE()
               AND (bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $noteId,
            ':bbid' => (int)$ctx['berufsbildner_id'],
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

function fetch_notes_list_for_ctx(PDO $pdo, array $ctx): array
{
    // Letzter Kommentar pro Note (über kommentar_id, stabil)
    $lastCommentJoin = '
        LEFT JOIN (
            SELECT note_id, MAX(kommentar_id) AS last_kommentar_id
            FROM noten_kommentare
            GROUP BY note_id
        ) lk ON lk.note_id = n.note_id
        LEFT JOIN noten_kommentare nk ON nk.kommentar_id = lk.last_kommentar_id
        LEFT JOIN benutzer cb ON cb.benutzer_id = nk.autor_benutzer_id
    ';

    if (!empty($ctx['is_admin'])) {
        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   s.bezeichnung AS semester,
                   COALESCE(f.name, m.titel) AS objekt_name,
                   b.benutzername AS lernender_username,
                   NULL AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            FROM noten n
            JOIN kategorien k ON k.kategorie_id = n.kategorie_id
            JOIN semester s ON s.semester_id = n.semester_id
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            LEFT JOIN faecher f ON f.fach_id = n.fach_id
            LEFT JOIN module m ON m.modul_id = n.modul_id
            ' . $lastCommentJoin . '
            WHERE n.geloescht_am IS NULL
            ORDER BY n.pruefungsdatum DESC
            LIMIT 200
        ';
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (!empty($ctx['lernender_id'])) {
        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   s.bezeichnung AS semester,
                   COALESCE(f.name, m.titel) AS objekt_name,
                   NULL AS lernender_username,
                   NULL AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            FROM noten n
            JOIN kategorien k ON k.kategorie_id = n.kategorie_id
            JOIN semester s ON s.semester_id = n.semester_id
            LEFT JOIN faecher f ON f.fach_id = n.fach_id
            LEFT JOIN module m ON m.modul_id = n.modul_id
            ' . $lastCommentJoin . '
            WHERE n.geloescht_am IS NULL
              AND n.lernender_id = :lid
            ORDER BY n.pruefungsdatum DESC
            LIMIT 200
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':lid' => (int)$ctx['lernender_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    if (!empty($ctx['berufsbildner_id'])) {
        // Seen-Status pro Note für diesen Berufsbildner (stabil via MAX)
        $seenJoin = '
            LEFT JOIN (
                SELECT note_id, berufsbildner_id, MAX(gesehen_am) AS gesehen_am
                FROM noten_gesehen
                GROUP BY note_id, berufsbildner_id
            ) ng ON ng.note_id = n.note_id AND ng.berufsbildner_id = :bbid
        ';

        $sql = '
            SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                   k.name AS kategorie,
                   s.bezeichnung AS semester,
                   COALESCE(f.name, m.titel) AS objekt_name,
                   b.benutzername AS lernender_username,
                   ng.gesehen_am AS gesehen_am,
                   nk.kommentar_text AS last_comment_text,
                   nk.erstellt_am AS last_comment_at,
                   cb.benutzername AS last_comment_author
            FROM noten n
            JOIN kategorien k ON k.kategorie_id = n.kategorie_id
            JOIN semester s ON s.semester_id = n.semester_id
            JOIN lernende l ON l.lernender_id = n.lernender_id
            JOIN benutzer b ON b.benutzer_id = l.benutzer_id
            JOIN betreuungen bt ON bt.lernender_id = n.lernender_id
            LEFT JOIN faecher f ON f.fach_id = n.fach_id
            LEFT JOIN module m ON m.modul_id = n.modul_id
            ' . $seenJoin . '
            ' . $lastCommentJoin . '
            WHERE n.geloescht_am IS NULL
              AND bt.berufsbildner_id = :bbid
              AND bt.gueltig_von <= CURDATE()
              AND (bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())
            ORDER BY n.pruefungsdatum DESC
            LIMIT 200
        ';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':bbid' => (int)$ctx['berufsbildner_id']]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    return [];
}

