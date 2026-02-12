<?php
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

if (is_logged_in()) {
    redirect('/dashboard.php');
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');
    $csrf       = (string)($_POST['csrf_token'] ?? '');

    if (login_rate_limit_check()) {
        $error = 'Zu viele Fehlversuche. Bitte später erneut versuchen.';
    } elseif ($csrf === '' || !hash_equals(csrf_token(), $csrf)) {
        $error = 'Ungültige Anfrage (CSRF). Bitte neu versuchen.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Bitte Benutzername/E-Mail und Passwort ausfüllen.';
    } else {
        try {
            $user = null;

            // Versuch 1: Benutzername ODER E-Mail (falls Spalte existiert)
            try {
                $stmt = $pdo->prepare(
                    'SELECT benutzer_id, benutzername, email, passwort_hash, aktiv
                     FROM benutzer
                     WHERE benutzername = :ident OR email = :ident
                     LIMIT 1'
                );
                $stmt->execute([':ident' => $identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            } catch (PDOException $e) {
                // Fallback: falls "email" Spalte nicht existiert oder Query nicht passt
                app_log_exception('Login query (with email) failed, trying fallback', $e, [
                    'identifier' => $identifier,
                ]);

                $stmt = $pdo->prepare(
                    'SELECT benutzer_id, benutzername, passwort_hash, aktiv
                     FROM benutzer
                     WHERE benutzername = :ident
                     LIMIT 1'
                );
                $stmt->execute([':ident' => $identifier]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $genericFail = 'Login fehlgeschlagen. Benutzer oder Passwort stimmt nicht.';

            if (!$user || (int)($user['aktiv'] ?? 0) !== 1) {
                $error = $genericFail;
                login_rate_limit_register_fail();
                app_log('warn', 'Login failed (no user or inactive)', [
                    'identifier' => $identifier,
                    'reason' => $user ? 'inactive' : 'not_found',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                sleep(1);
            } elseif (!password_verify($password, (string)$user['passwort_hash'])) {
                $error = $genericFail;
                login_rate_limit_register_fail();
                app_log('warn', 'Login failed (bad password)', [
                    'identifier' => $identifier,
                    'user_id' => (int)$user['benutzer_id'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);
                sleep(1);
            } else {
                // Optional rehash
                if (password_needs_rehash((string)$user['passwort_hash'], PASSWORD_BCRYPT)) {
                    $newHash = password_hash($password, PASSWORD_BCRYPT);
                    if ($newHash !== false) {
                        $upd = $pdo->prepare(
                            'UPDATE benutzer
                             SET passwort_hash = :h
                             WHERE benutzer_id = :uid
                             LIMIT 1'
                        );
                        $upd->execute([
                            ':h' => $newHash,
                            ':uid' => (int)$user['benutzer_id'],
                        ]);
                    }
                }

                login_user((int)$user['benutzer_id'], (string)$user['benutzername']);

                app_log('info', 'Login success', [
                    'user_id' => (int)$user['benutzer_id'],
                    'benutzername' => (string)$user['benutzername'],
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ]);

                redirect('/dashboard.php');
            }
        } catch (Throwable $e) {
            $error = 'Interner Fehler beim Login. Bitte später erneut versuchen.';
            app_log_exception('Login exception', $e, [
                'identifier' => $identifier,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ]);
        }
    }
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

  <?= flash_render_html() ?>

  <?php if ($error !== ''): ?>
    <p style="color:red;font-weight:bold;"><?= h($error) ?></p>
  <?php endif; ?>

  <form method="post" action="/login.php" autocomplete="off">
    <?= csrf_field() ?>

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
