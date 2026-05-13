<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

/** Nombre d’échecs de mot de passe avant blocage du compte. */
const LOGIN_MAX_FAILED_ATTEMPTS = 5;

/** Durée du blocage automatique (minutes) après dépassement du seuil. */
const LOGIN_LOCKOUT_MINUTES = 30;

if (admin_logged_in()) {
    header('Location: ' . url('admin/dashboard.php'), true, 302);
    exit;
}

$error = '';
$registeredOk = isset($_GET['registered']) && $_GET['registered'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = normalize_admin_email((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error = 'Email et mot de passe requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email invalide.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare(
                'SELECT id, email, password_hash, COALESCE(is_super_admin, 0) AS is_super_admin,
                        COALESCE(failed_login_attempts, 0) AS failed_login_attempts, locked_until
                 FROM admins WHERE LOWER(TRIM(email)) = :e LIMIT 1'
            );
            $stmt->execute(['e' => $email]);
            $row = $stmt->fetch();

            if (!$row) {
                $error = 'Identifiants incorrects.';
            } else {
                $adminId = (int) $row['id'];
                $lockedUntilRaw = $row['locked_until'];

                if ($lockedUntilRaw !== null && $lockedUntilRaw !== '') {
                    $lockedTs = strtotime((string) $lockedUntilRaw);
                    if ($lockedTs !== false && $lockedTs <= time()) {
                        $pdo->prepare(
                            'UPDATE admins SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id LIMIT 1'
                        )->execute(['id' => $adminId]);
                        $row['failed_login_attempts'] = 0;
                        $lockedUntilRaw = null;
                    }
                }

                if ($lockedUntilRaw !== null && $lockedUntilRaw !== '') {
                    $lockedTs = strtotime((string) $lockedUntilRaw);
                    if ($lockedTs !== false && $lockedTs > time()) {
                        $error = 'Ce compte est temporairement bloqué après plusieurs tentatives incorrectes. Réessayez plus tard ou contactez un super-administrateur.';
                    }
                }

                if ($error === '' && password_verify($password, $row['password_hash'])) {
                    $pdo->prepare(
                        'UPDATE admins SET failed_login_attempts = 0, locked_until = NULL WHERE id = :id LIMIT 1'
                    )->execute(['id' => $adminId]);
                    login_admin(
                        $adminId,
                        normalize_admin_email((string) $row['email']),
                        ((int) ($row['is_super_admin'] ?? 0)) === 1
                    );
                    header('Location: ' . url('admin/dashboard.php'), true, 302);
                    exit;
                }

                if ($error === '') {
                    $prevAttempts = (int) $row['failed_login_attempts'];
                    $newAttempts = $prevAttempts + 1;
                    $hitLimit = $newAttempts >= LOGIN_MAX_FAILED_ATTEMPTS;

                    $updSql = $hitLimit
                        ? 'UPDATE admins SET failed_login_attempts = :attempts,
                             locked_until = DATE_ADD(NOW(), INTERVAL ' . (int) LOGIN_LOCKOUT_MINUTES . ' MINUTE)
                             WHERE id = :id LIMIT 1'
                        : 'UPDATE admins SET failed_login_attempts = :attempts WHERE id = :id LIMIT 1';
                    $upd = $pdo->prepare($updSql);
                    $upd->execute(['attempts' => $newAttempts, 'id' => $adminId]);

                    $error = $hitLimit
                        ? 'Trop de tentatives avec ce compte. Connexion bloquée pendant ' . (int) LOGIN_LOCKOUT_MINUTES . ' minutes.'
                        : 'Identifiants incorrects.';
                }
            }
        } catch (Throwable $e) {
            $error = database_user_error_message($e);
        }
    }
}

$pageTitle = 'Connexion administrateur — Goo-Bridge';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(url('favicon.svg'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(url('style.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(url('admin/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="admin-body admin-body--auth">
  <div class="admin-auth-card">
    <a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>" class="admin-auth-brand">Goo<span>-Bridge</span></a>

    <?php if ($registeredOk): ?>
      <p class="admin-alert admin-alert--ok" role="status">Compte créé. Vous pouvez vous connecter.</p>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
      <p class="admin-alert admin-alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="" class="admin-form" autocomplete="on">
      <div class="admin-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary admin-submit">Se connecter</button>
    </form>

    <p class="admin-auth-footer"><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">← Retour au site</a></p>
  </div>
</body>
</html>
