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
    $stmt = $pdo->prepare(
        'SELECT benutzer_id, benutzername, email, aktiv
         FROM benutzer
         WHERE benutzer_id = :uid
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $error = 'Benutzer nicht gefunden (Session ungültig?).';
    }
} catch (Throwable $e) {
    $error = 'Interner Fehler beim Laden der Benutzerdaten.';
    app_log_exception('Dashboard load user failed', $e, ['user_id' => $userId]);
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

  <?= flash_render_html() ?>

  <p>
    Eingeloggt als: <b><?= h($username) ?></b>
    (Rollen: <?= h(implode(', ', $roles)) ?>)
  </p>

  <p>
    <a href="/noten.php">Noten</a>
    <?php if (!empty($ctx['is_admin'])): ?>
      | <a href="/admin.php">Admin</a>
    <?php endif; ?>
  </p>

  <form method="post" action="/logout.php" style="display:inline;">
    <?= csrf_field() ?>
    <button type="submit">Logout</button>
  </form>

  <hr>

  <?php if ($error !== ''): ?>
    <p style="color:red;font-weight:bold;"><?= h($error) ?></p>
  <?php elseif ($user): ?>
    <h2>Account</h2>
    <ul>
      <li>ID: <?= h((string)$user['benutzer_id']) ?></li>
      <li>Benutzername: <?= h((string)$user['benutzername']) ?></li>
      <li>E-Mail: <?= h((string)($user['email'] ?? '')) ?></li>
      <li>Aktiv: <?= h((string)$user['aktiv']) ?></li>
    </ul>
  <?php endif; ?>
</body>
</html>
