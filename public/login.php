<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';


header('Content-Type: text/html; charset=utf-8');

$error = '';
$identifier = '';


/*
  POST: Login prüfen
  - Identifier kann benutzername ODER email sein
  - Query ist prepared -> schützt vor SQL Injection
  - Passwortprüfung via password_verify() -> bcrypt korrekt
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');
    $csrf       = (string)($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Ungültige Anfrage (CSRF). Bitte neu versuchen.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Bitte Benutzername/E-Mail und Passwort ausfüllen.';
    } else {
        try {
            $pdo = get_pdo();

            // LIMIT 1: wir wollen genau einen User
            $stmt = $pdo->prepare(
                'SELECT benutzer_id, benutzername, passwort_hash, aktiv
                 FROM benutzer
                 WHERE benutzername = :u OR email = :e
                 LIMIT 1'
            );
            $stmt->execute([
                ':u' => $identifier,
                ':e' => $identifier,
            ]);
            $user = $stmt->fetch();

            // Generische Fehlermeldung (kein Username-Enumeration)
            $genericFail = 'Login fehlgeschlagen. Benutzer oder Passwort stimmt nicht.';

            if (!$user || (int)$user['aktiv'] !== 1) {
                $error = $genericFail;
                app_log('warn', 'Login failed (no user or inactive)', [
                    'identifier' => $identifier,
                    'reason' => $user ? 'inactive' : 'not_found',
                ]);
            } else {
                // WICHTIG: nicht neu hashen! => verify gegen gespeicherten bcrypt-hash
                if (!password_verify($password, (string)$user['passwort_hash'])) {
                    $error = $genericFail;
                    app_log('warn', 'Login failed (bad password)', [
                        'identifier' => $identifier,
                        'user_id' => (int)$user['benutzer_id'],
                    ]);
                } else {
                    // Optional: Hash “upgraden”, wenn PHP künftig stärkere Parameter nutzt
                    if (password_needs_rehash((string)$user['passwort_hash'], PASSWORD_BCRYPT)) {
                        $newHash = password_hash($password, PASSWORD_BCRYPT);
                        $upd = $pdo->prepare('UPDATE benutzer SET passwort_hash = :h WHERE benutzer_id = :uid');
                        $upd->execute([':h' => $newHash, ':uid' => (int)$user['benutzer_id']]);
                    }

                    login_user((int)$user['benutzer_id'], (string)$user['benutzername']);
                    app_log('info', 'Login success', [
                        'user_id' => (int)$user['benutzer_id'],
                        'benutzername' => (string)$user['benutzername'],
                    ]);

                    header('Location: /dashboard.php');
                    exit;
                }
            }
        } catch (Throwable $e) {
            // Im Browser keine Details, aber ins Log schon
            $error = 'Interner Fehler beim Login. Bitte später erneut versuchen.';
            app_log('error', 'Login exception', [
                'identifier' => $identifier,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }
}

// Wenn bereits eingeloggt, direkt weiter
if (is_logged_in()) {
    header('Location: /dashboard.php');
    exit;
}
?>
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login – Notenportal</title>
</head>
<body>
  <h1>Login</h1>

  <?php if ($error !== ''): ?>
    <p style="color:red;font-weight:bold;"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" action="/login.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">

    <label>
      Benutzername oder E-Mail<br>
      <input type="text" name="identifier" value="<?= h($identifier) ?>" required>
    </label>
    <br><br>

    <label>
      Passwort<br>
      <input type="password" name="password" required>
    </label>
    <br><br>

    <button type="submit">Anmelden</button>
  </form>
</body>
</html>
