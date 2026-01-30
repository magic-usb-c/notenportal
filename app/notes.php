<?php
declare(strict_types=1);

/*
  app/notes.php
  Zweck:
  - DB-Zugriffe + Permission-Checks rund um Noten
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
        ':gewichtung_prozent' => (int)$data['gewichtung_prozent'],
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
        ':gewichtung_prozent' => (int)$data['gewichtung_prozent'],
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
