<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_login();

header('Content-Type: text/html; charset=utf-8');

$userId = (int)($ctx['user_id'] ?? 0);
$username = (string)($ctx['username'] ?? 'unknown');
$roles = (array)($ctx['roles'] ?? []);

$user = null;
$error = '';

try {
    // Benutzerdaten aus DB holen (nur zum Test/Proof)
    $stmt = $pdo->prepare(
        'SELECT benutzer_id, benutzername, email, aktiv, erstellt_am, aktualisiert_am
         FROM benutzer
         WHERE benutzer_id = :uid
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = 'Benutzer nicht gefunden (Session ungültig?).';
    }
} catch (Throwable $e) {
    $error = 'Interner Fehler beim Laden der Benutzerdaten.';
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard – Notenportal</title>
</head>
<body>
  <h1>Dashboard</h1>

  <p>
    Eingeloggt als: <b><?= h($username) ?></b><br>
    Rollen: <?= h(implode(', ', $roles)) ?>
  </p>

  <p>
    <a href="/noten.php">Noten</a>

    <?php if (!empty($ctx['is_admin'])): ?>
    |   <a href="/admin.php">Admin</a>
    <?php endif; ?>

  | <form method="post" action="/logout.php" style="display:inline;">
      <input type="hidden" name="csrf_token" value="<?= h((string)$_SESSION['csrf_token']) ?>">
      <button type="submit">Logout</button>
    </form>
  </p>

  </p>

  <?php if ($error !== ''): ?>
    <p style="color:red;font-weight:bold;"><?= h($error) ?></p>
  <?php else: ?>
    <h2>Benutzer aus Datenbank</h2>
    <table border="1" cellpadding="6" cellspacing="0">
      <tr><th>ID</th><td><?= h((string)$user['benutzer_id']) ?></td></tr>
      <tr><th>Benutzername</th><td><?= h((string)$user['benutzername']) ?></td></tr>
      <tr><th>E-Mail</th><td><?= h((string)$user['email']) ?></td></tr>
      <tr><th>Aktiv</th><td><?= ((int)$user['aktiv'] === 1) ? 'ja' : 'nein' ?></td></tr>
      <tr><th>Erstellt</th><td><?= h((string)$user['erstellt_am']) ?></td></tr>
      <tr><th>Aktualisiert</th><td><?= h((string)$user['aktualisiert_am']) ?></td></tr>
    </table>
  <?php endif; ?>
</body>
</html>
