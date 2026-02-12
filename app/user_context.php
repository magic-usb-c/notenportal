<?php
declare(strict_types=1);

/*
  app/user_context.php
  Zweck:
  - zentraler Kontext für eingeloggten User
  - liefert: user_id, username, roles, is_admin, lernender_id, berufsbildner_id
*/

require_once __DIR__ . '/auth.php';

function current_user_context(PDO $pdo): array
{
    start_secure_session();

    $uidRaw = $_SESSION['user_id'] ?? 0;
    $uid = (is_int($uidRaw) || (is_string($uidRaw) && ctype_digit($uidRaw))) ? (int)$uidRaw : 0;

    $username = (string)($_SESSION['username'] ?? '');

    // nicht eingeloggt -> Kontext minimal, keine weiteren Queries
    if ($uid <= 0) {
        return [
            'user_id' => 0,
            'username' => '',
            'roles' => [],
            'is_admin' => false,
            'lernender_id' => null,
            'berufsbildner_id' => null,
        ];
    }

    $roles = current_user_roles($pdo);

    $isAdmin = false;
    $isLernender = false;
    $isBerufsbildner = false;

    foreach ($roles as $r) {
        if (strcasecmp($r, 'Admin') === 0) $isAdmin = true;
        if (strcasecmp($r, 'Lernender') === 0) $isLernender = true;
        if (strcasecmp($r, 'Berufsbildner') === 0) $isBerufsbildner = true;
    }

    $lernenderId = null;
    if ($isLernender || $isAdmin) {
        $stmt = $pdo->prepare(
            'SELECT lernender_id
             FROM lernende
             WHERE benutzer_id = :uid AND geloescht_am IS NULL
             LIMIT 1'
        );
        $stmt->execute([':uid' => $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['lernender_id'])) {
            $lernenderId = (int)$row['lernender_id'];
        }
    }

    $berufsbildnerId = null;
    if ($isBerufsbildner || $isAdmin) {
        $stmt = $pdo->prepare(
            'SELECT berufsbildner_id
             FROM berufsbildner
             WHERE benutzer_id = :uid AND geloescht_am IS NULL
             LIMIT 1'
        );
        $stmt->execute([':uid' => $uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['berufsbildner_id'])) {
            $berufsbildnerId = (int)$row['berufsbildner_id'];
        }
    }

    return [
        'user_id' => $uid,
        'username' => $username,
        'roles' => $roles,
        'is_admin' => $isAdmin,
        'lernender_id' => $lernenderId,
        'berufsbildner_id' => $berufsbildnerId,
    ];
}
