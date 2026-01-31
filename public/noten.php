<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require_login();

header('Content-Type: text/html; charset=utf-8');

$notes = [];
$error = '';

$canManage = (!empty($ctx['is_admin']) || !empty($ctx['lernender_id'])); // admin oder lernender
$canSeeLearnerColumn = (!empty($ctx['is_admin']) || !empty($ctx['berufsbildner_id']));

try {
    if (!empty($ctx['is_admin'])) {
        $stmt = $pdo->query(
            'SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                    k.name AS kategorie,
                    COALESCE(f.name, m.titel) AS objekt_name,
                    s.bezeichnung AS semester,
                    b.benutzername AS lernender_username
             FROM noten n
             JOIN kategorien k ON k.kategorie_id = n.kategorie_id
             JOIN semester s ON s.semester_id = n.semester_id
             JOIN lernende l ON l.lernender_id = n.lernender_id
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             LEFT JOIN faecher f ON f.fach_id = n.fach_id
             LEFT JOIN module m ON m.modul_id = n.modul_id
             WHERE n.geloescht_am IS NULL
             ORDER BY n.pruefungsdatum DESC
             LIMIT 200'
        );
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } elseif (!empty($ctx['lernender_id'])) {
        $stmt = $pdo->prepare(
            'SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                    k.name AS kategorie,
                    COALESCE(f.name, m.titel) AS objekt_name,
                    s.bezeichnung AS semester
             FROM noten n
             JOIN kategorien k ON k.kategorie_id = n.kategorie_id
             JOIN semester s ON s.semester_id = n.semester_id
             LEFT JOIN faecher f ON f.fach_id = n.fach_id
             LEFT JOIN module m ON m.modul_id = n.modul_id
             WHERE n.geloescht_am IS NULL
               AND n.lernender_id = :lid
             ORDER BY n.pruefungsdatum DESC'
        );
        $stmt->execute([':lid' => (int)$ctx['lernender_id']]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    } elseif (!empty($ctx['berufsbildner_id'])) {
        $stmt = $pdo->prepare(
            'SELECT n.note_id, n.lernender_id, n.pruefungsdatum, n.note_wert, n.gewichtung_prozent,
                    k.name AS kategorie,
                    COALESCE(f.name, m.titel) AS objekt_name,
                    s.bezeichnung AS semester,
                    b.benutzername AS lernender_username
             FROM noten n
             JOIN kategorien k ON k.kategorie_id = n.kategorie_id
             JOIN semester s ON s.semester_id = n.semester_id
             JOIN lernende l ON l.lernender_id = n.lernender_id
             JOIN benutzer b ON b.benutzer_id = l.benutzer_id
             JOIN betreuungen bt ON bt.lernender_id = n.lernender_id
             LEFT JOIN faecher f ON f.fach_id = n.fach_id
             LEFT JOIN module m ON m.modul_id = n.modul_id
             WHERE n.geloescht_am IS NULL
               AND bt.berufsbildner_id = :bbid
               AND bt.gueltig_von <= CURDATE()
               AND (bt.gueltig_bis IS NULL OR bt.gueltig_bis >= CURDATE())
             ORDER BY n.pruefungsdatum DESC
             LIMIT 200'
        );
        $stmt->execute([':bbid' => (int)$ctx['berufsbildner_id']]);
        $notes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } else {
        $error = 'Kein gültiges Profil (weder Lernender noch Berufsbildner noch Admin).';
    }
} catch (Throwable $e) {
    $error = 'Interner Fehler beim Laden der Noten.';
    app_log_exception('Load notes failed', $e, [
        'user_id' => $ctx['user_id'] ?? null,
    ]);
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Noten – Notenportal</title>
</head>
<body>
  <h1>Noten</h1>

  <?= flash_render_html() ?>

  <p>
    Eingeloggt als: <b><?= h((string)$ctx['username']) ?></b>
    (Rollen: <?= h(implode(', ', (array)$ctx['roles'])) ?>)
  </p>

  <p>
    <a href="/dashboard.php">Dashboard</a>
    <?php if ($canManage): ?>
      | <a href="/noten_create.php">+ Neue Note</a>
    <?php endif; ?>
    | <form method="post" action="/logout.php" style="display:inline;">
        <?= csrf_field() ?>
        <button type="submit">Logout</button>
      </form>
  </p>

  <?php if ($error !== ''): ?>
    <p style="color:red;font-weight:bold;"><?= h($error) ?></p>
  <?php elseif (!$notes): ?>
    <p>Keine Noten gefunden.</p>
  <?php else: ?>
    <table border="1" cellpadding="6" cellspacing="0">
      <tr>
        <th>Datum</th>
        <th>Kategorie</th>
        <th>Fach/Modul</th>
        <th>Semester</th>
        <th>Note</th>
        <th>Gewichtung %</th>
        <?php if ($canSeeLearnerColumn): ?>
          <th>Lernender</th>
        <?php endif; ?>
        <?php if ($canManage): ?>
          <th>Aktionen</th>
        <?php endif; ?>
      </tr>
      <?php foreach ($notes as $n): ?>
        <tr>
          <td><?= h((string)$n['pruefungsdatum']) ?></td>
          <td><?= h((string)$n['kategorie']) ?></td>
          <td><?= h((string)$n['objekt_name']) ?></td>
          <td><?= h((string)$n['semester']) ?></td>
          <td><?= h((string)$n['note_wert']) ?></td>
          <td><?= h((string)($n['gewichtung_prozent'] ?? '')) ?></td>

          <?php if ($canSeeLearnerColumn): ?>
            <td><?= h((string)($n['lernender_username'] ?? '')) ?></td>
          <?php endif; ?>
          
          <td><a href="/noten_detail.php?note_id=<?= h((string)$n['note_id']) ?>">Öffnen</a></td>

          <?php if ($canManage): ?>
            <td>
              <a href="/noten_edit.php?note_id=<?= h((string)$n['note_id']) ?>">Bearbeiten</a>
              <form method="post" action="/noten_delete.php" style="display:inline;" onsubmit="return confirm('Wirklich löschen?');">
                <?= csrf_field() ?>
                <input type="hidden" name="note_id" value="<?= h((string)$n['note_id']) ?>">
                <button type="submit">Löschen</button>
              </form>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</body>
</html>
