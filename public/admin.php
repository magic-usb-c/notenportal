<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

require_login();

if (empty($ctx['is_admin'])) {
    http_response_code(403);
    exit('Forbidden');
}

header('Content-Type: text/html; charset=utf-8');

$username = (string)($ctx['username'] ?? 'unknown');
$roles    = (array)($ctx['roles'] ?? []);
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin – Notenportal</title>
</head>
<body>
  <h1>Admin</h1>

  <?= flash_render_html() ?>

  <p>Eingeloggt als: <b><?= h($username) ?></b></p>

  <p>
    <a href="/dashboard.php">Dashboard</a> |
    <a href="/noten.php">Noten</a>
  </p>

  <h2>Deine Rollen</h2>
  <ul>
    <?php foreach ($roles as $r): ?>
      <li><?= h((string)$r) ?></li>
    <?php endforeach; ?>
  </ul>

  <h2>Hinweis</h2>
  <p>Admin-CRUD (User/Module/Fächer/Semester) kommt als nächster Ausbau.</p>
</body>
</html>
