<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

/** @var array{allow_admin_registration?: bool} $appCfg */
$appCfg = require __DIR__ . '/config/app.php';

if (!($appCfg['allow_admin_registration'] ?? true)) {
    header('Location: ' . url('login.php'), true, 302);
    exit;
}

if (admin_logged_in()) {
    header('Location: ' . url('admin/dashboard.php'), true, 302);
    exit;
}

$error = '';
$minLen = 8;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailRaw = trim((string) ($_POST['email'] ?? ''));
    $email = normalize_admin_email($emailRaw);
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if ($email === '' || $password === '' || $confirm === '') {
        $error = 'Tous les champs sont requis.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } elseif (strlen($password) < $minLen) {
        $error = 'Le mot de passe doit contenir au moins ' . $minLen . ' caractères.';
    } elseif (!hash_equals($password, $confirm)) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $pdo = db();
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE LOWER(TRIM(email)) = :e LIMIT 1');
            $stmt->execute(['e' => $email]);
            if ($stmt->fetch()) {
                $error = 'Un compte existe déjà avec cet email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare(
                        'INSERT INTO admins (email, first_name, last_name, password_hash, is_super_admin) VALUES (:email, NULL, NULL, :hash, 0)'
                    );
                $ins->execute(['email' => $email, 'hash' => $hash]);
                header('Location: ' . url('login.php?registered=1'), true, 303);
                exit;
            }
        } catch (Throwable $e) {
            $error = database_user_error_message($e);
        }
    }
}

$pageTitle = 'Inscription administrateur — Goo-Bridge';
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
    <h1>Nouveau compte administrateur</h1>
    <p class="admin-auth-hint">Créez un accès au tableau de bord. Mot de passe : au moins <?= (int) $minLen ?> caractères.</p>

    <?php if ($error !== ''): ?>
      <p class="admin-alert admin-alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="" class="admin-form" autocomplete="off">
      <div class="admin-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required maxlength="255"
          value="<?= htmlspecialchars(trim((string) ($_POST['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required minlength="<?= (int) $minLen ?>" autocomplete="new-password">
      </div>
      <div class="admin-field">
        <label for="password_confirm">Confirmer le mot de passe</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="<?= (int) $minLen ?>" autocomplete="new-password">
      </div>
      <button type="submit" class="btn-primary admin-submit">Créer mon compte</button>
    </form>

    <p class="admin-auth-switch">
      <a href="<?= htmlspecialchars(url('login.php'), ENT_QUOTES, 'UTF-8') ?>">Déjà un compte ? Se connecter</a>
    </p>
    <p class="admin-auth-footer"><a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">← Retour au site</a></p>
  </div>
</body>
</html>
