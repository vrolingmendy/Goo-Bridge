<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_super_admin();

$pageTitle = 'Nouvel administrateur — Goo-Bridge Admin';
$activeNav = 'admins_create';

$pdo = db();
$error = '';
$minLen = 8;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Session invalide — rechargez la page.';
    } else {
        $emailRaw = trim((string) ($_POST['email'] ?? ''));
        $email = normalize_admin_email($emailRaw);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $role = (string) ($_POST['role'] ?? 'admin');

        if ($email === '' || $password === '' || $confirm === '') {
            $error = 'Tous les champs sont requis.';
        } elseif ($firstName === '' || $lastName === '') {
            $error = 'Le prénom et le nom sont requis.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Adresse email invalide.';
        } elseif (strlen($password) < $minLen) {
            $error = 'Le mot de passe doit contenir au moins ' . $minLen . ' caractères.';
        } elseif (!hash_equals($password, $confirm)) {
            $error = 'Les mots de passe ne correspondent pas.';
        } elseif (!in_array($role, ['admin', 'super_admin'], true)) {
            $error = 'Rôle invalide.';
        } else {
            try {
                $stmt = $pdo->prepare('SELECT id FROM admins WHERE LOWER(TRIM(email)) = :e LIMIT 1');
                $stmt->execute(['e' => $email]);
                if ($stmt->fetch()) {
                    $error = 'Un compte existe déjà avec cet email.';
                } else {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare(
                        'INSERT INTO admins (email, first_name, last_name, password_hash, is_super_admin)
                         VALUES (:email, :first_name, :last_name, :hash, :is_super_admin)'
                    );
                    $ins->execute([
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'hash' => $hash,
                        'is_super_admin' => $role === 'super_admin' ? 1 : 0,
                    ]);
                    header('Location: ' . url('admin/admins.php?flash=created'), true, 303);
                    exit;
                }
            } catch (Throwable $e) {
                $error = database_user_error_message($e);
            }
        }
    }
}

require __DIR__ . '/inc/header.php';
?>

<h1>Nouvel administrateur</h1>
<p class="admin-lead"><a href="<?= htmlspecialchars(url('admin/admins.php'), ENT_QUOTES, 'UTF-8') ?>">← Utilisateurs</a></p>

<?php if ($error !== ''): ?>
  <p class="admin-alert admin-alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<div class="admin-panel">
  <form method="post" class="admin-form admin-form--admins-create" autocomplete="off">
    <?= csrf_field() ?>
    <div class="admin-grid-2">
      <div class="admin-field">
        <label for="first_name">Prénom</label>
        <input type="text" id="first_name" name="first_name" required maxlength="128" autocomplete="given-name"
          value="<?= htmlspecialchars(trim((string) ($_POST['first_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="last_name">Nom</label>
        <input type="text" id="last_name" name="last_name" required maxlength="128" autocomplete="family-name"
          value="<?= htmlspecialchars(trim((string) ($_POST['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required maxlength="255"
          value="<?= htmlspecialchars(trim((string) ($_POST['email'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="role">Rôle</label>
        <?php $selectedRole = (string) ($_POST['role'] ?? 'admin'); ?>
        <select id="role" name="role" required>
          <option value="admin" <?= $selectedRole === 'admin' ? 'selected' : '' ?>>Administrateur</option>
          <option value="super_admin" <?= $selectedRole === 'super_admin' ? 'selected' : '' ?>>Super-administrateur</option>
        </select>
      </div>
      <div class="admin-field">
        <label for="password">Mot de passe</label>
        <input type="password" id="password" name="password" required minlength="<?= (int) $minLen ?>" autocomplete="new-password">
      </div>
      <div class="admin-field">
        <label for="password_confirm">Confirmer le mot de passe</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="<?= (int) $minLen ?>" autocomplete="new-password">
      </div>
    </div>
    <button type="submit" class="btn-primary admin-submit admin-form--admins-create__submit">Créer le compte</button>
  </form>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
