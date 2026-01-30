<?php
declare(strict_types=1);

/*
  app/user_context.php
  Zweck:
  - Ein zentraler "Kontext" für den eingeloggten User.
  - Liefert: user_id, username, rollen, lernender_id, berufsbildner_id
  - Damit kannst du in jeder Seite saubere Filter machen:
      Lernender: WHERE lernende.benutzer_id = :uid
      Berufsbildner: via betreuungen/berufsbildner_id
*/

require_once __DIR__ . '/auth.php';

/**
 * Liefert Kontextdaten zum eingeloggten User.
 *
 * Rückgabe-Beispiel:
 * [
 *   'user_id' => 1,
 *   'username' => 'lerni1',
 *   'roles' => ['Lernender'],
 *   'is_admin' => false,
 *   'lernender_id' => 3,
 *   'berufsbildner_id' => null
 * ]
 */
function current_user_context(PDO $pdo): array
{
    start_secure_session();

    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $username = (string)($_SESSION['username'] ?? '');

    $roles = current_user_roles($pdo);
    $isAdmin = false;
    foreach ($roles as $r) {
        if (strcasecmp($r, 'Admin') === 0) {
            $isAdmin = true;
            break;
        }
    }

    // Lernender-Profil-ID holen (falls vorhanden)
    $lernenderId = null;
    $stmt = $pdo->prepare(
        'SELECT lernender_id
         FROM lernende
         WHERE benutzer_id = :uid AND geloescht_am IS NULL
         LIMIT 1'
    );
    $stmt->execute([':uid' => $uid]);
    $row = $stmt->fetch();
    if ($row && isset($row['lernender_id'])) {
        $lernenderId = (int)$row['lernender_id'];
    }

    // Berufsbildner-Profil-ID holen (falls vorhanden)
    $berufsbildnerId = null;
    $stmt = $pdo->prepare(
        'SELECT berufsbildner_id
         FROM berufsbildner
         WHERE benutzer_id = :uid AND geloescht_am IS NULL
         LIMIT 1'
    );
    $stmt->execute([':uid' => $uid]);
    $row = $stmt->fetch();
    if ($row && isset($row['berufsbildner_id'])) {
        $berufsbildnerId = (int)$row['berufsbildner_id'];
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
