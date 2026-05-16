<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_super_admin();

$pdo = db();
$minLen = 8;

/** @return array{super:int, total:int} */
function admin_counts(PDO $pdoConn): array
{
    $super = (int) $pdoConn->query(
        'SELECT COUNT(*) FROM admins WHERE COALESCE(is_super_admin, 0) = 1'
    )->fetchColumn();
    $total = (int) $pdoConn->query('SELECT COUNT(*) FROM admins')->fetchColumn();

    return ['super' => $super, 'total' => $total];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        header('Location: ' . url('admin/admins.php?flash=csrf'), true, 302);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    $postId = (int) ($_POST['id'] ?? 0);

    if ($action === 'delete' && $postId > 0) {
        $counts = admin_counts($pdo);
        $stmt = $pdo->prepare(
            'SELECT id, COALESCE(is_super_admin, 0) AS is_super_admin FROM admins WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $postId]);
        $delRow = $stmt->fetch();
        if ($delRow === false) {
            header('Location: ' . url('admin/admins.php?flash=notfound'), true, 302);
            exit;
        }
        $me = current_admin_id();
        if ($me !== null && $postId === $me) {
            header('Location: ' . url('admin/admins.php?flash=selfdelete'), true, 302);
            exit;
        }
        $targetSuper = ((int) ($delRow['is_super_admin'] ?? 0)) === 1;
        if ($targetSuper && $counts['super'] <= 1) {
            header('Location: ' . url('admin/admins.php?flash=lastsuper'), true, 302);
            exit;
        }
        try {
            $pdo->prepare('DELETE FROM admins WHERE id = :id LIMIT 1')->execute(['id' => $postId]);
            header('Location: ' . url('admin/admins.php?flash=deleted'), true, 302);
            exit;
        } catch (Throwable $e) {
            header('Location: ' . url('admin/admins.php?flash=error'), true, 302);
            exit;
        }
    }

    if ($action === 'update' && $postId > 0) {
        $emailRaw = trim((string) ($_POST['email'] ?? ''));
        $email = normalize_admin_email($emailRaw);
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $role = (string) ($_POST['role'] ?? 'admin');

        $stmt = $pdo->prepare(
            'SELECT id, email, COALESCE(is_super_admin, 0) AS is_super_admin FROM admins WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $postId]);
        $row = $stmt->fetch();
        if ($row === false) {
            header('Location: ' . url('admin/admins.php?flash=notfound'), true, 302);
            exit;
        }

        $wasSuper = ((int) ($row['is_super_admin'] ?? 0)) === 1;
        $wantSuper = $role === 'super_admin';
        if (!in_array($role, ['admin', 'super_admin'], true)) {
            header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=role'), true, 302);
            exit;
        }

        if ($email === '' || $firstName === '' || $lastName === '') {
            header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=required'), true, 302);
            exit;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=email'), true, 302);
            exit;
        }

        $dup = $pdo->prepare(
            'SELECT id FROM admins WHERE LOWER(TRIM(email)) = :e AND id != :id LIMIT 1'
        );
        $dup->execute(['e' => $email, 'id' => $postId]);
        if ($dup->fetch()) {
            header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=duplicate'), true, 302);
            exit;
        }

        $counts = admin_counts($pdo);
        if ($wasSuper && !$wantSuper && $counts['super'] <= 1) {
            header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=lastsuper'), true, 302);
            exit;
        }

        if ($password !== '' || $confirm !== '') {
            if (strlen($password) < $minLen) {
                header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=pwdshort'), true, 302);
                exit;
            }
            if (!hash_equals($password, $confirm)) {
                header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=pwdmatch'), true, 302);
                exit;
            }
        }

        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $up = $pdo->prepare(
                    'UPDATE admins SET email = :email, first_name = :fn, last_name = :ln,
                     password_hash = :hash, is_super_admin = :super WHERE id = :id LIMIT 1'
                );
                $up->execute([
                    'email' => $email,
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'hash' => $hash,
                    'super' => $wantSuper ? 1 : 0,
                    'id' => $postId,
                ]);
            } else {
                $up = $pdo->prepare(
                    'UPDATE admins SET email = :email, first_name = :fn, last_name = :ln,
                     is_super_admin = :super WHERE id = :id LIMIT 1'
                );
                $up->execute([
                    'email' => $email,
                    'fn' => $firstName,
                    'ln' => $lastName,
                    'super' => $wantSuper ? 1 : 0,
                    'id' => $postId,
                ]);
            }

            $me = current_admin_id();
            if ($me !== null && $postId === $me) {
                $_SESSION['admin_email'] = $email;
                $_SESSION['admin_is_super'] = $wantSuper;
                $_SESSION['admin_role_synced'] = true;
            }

            header('Location: ' . url('admin/admins.php?flash=updated'), true, 302);
            exit;
        } catch (Throwable $e) {
            header('Location: ' . url('admin/admins_edit.php?id=' . $postId . '&flash=error'), true, 302);
            exit;
        }
    }

    header('Location: ' . url('admin/admins.php'), true, 302);
    exit;
}

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . url('admin/admins.php'), true, 302);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT id, email, first_name, last_name, COALESCE(is_super_admin, 0) AS is_super_admin, created_at
     FROM admins WHERE id = :id LIMIT 1'
);
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if ($row === false) {
    header('Location: ' . url('admin/admins.php?flash=notfound'), true, 302);
    exit;
}

$pageTitle = 'Modifier — ' . (string) $row['email'];
$activeNav = 'admins';

$flashGet = isset($_GET['flash']) ? (string) $_GET['flash'] : '';
$flashMsg = '';
if ($flashGet === 'required') {
    $flashMsg = 'Prénom, nom et email sont obligatoires.';
} elseif ($flashGet === 'email') {
    $flashMsg = 'Adresse email invalide.';
} elseif ($flashGet === 'duplicate') {
    $flashMsg = 'Un autre compte utilise déjà cet email.';
} elseif ($flashGet === 'lastsuper') {
    $flashMsg = 'Impossible de retirer le statut super-admin au dernier super-administrateur.';
} elseif ($flashGet === 'pwdshort') {
    $flashMsg = 'Le mot de passe doit contenir au moins ' . $minLen . ' caractères.';
} elseif ($flashGet === 'pwdmatch') {
    $flashMsg = 'Les mots de passe ne correspondent pas.';
} elseif ($flashGet === 'role') {
    $flashMsg = 'Rôle invalide.';
} elseif ($flashGet === 'error') {
    $flashMsg = 'Enregistrement impossible.';
}

$hrefList = url('admin/admins.php');
$isSuperRow = ((int) ($row['is_super_admin'] ?? 0)) === 1;
$roleVal = $isSuperRow ? 'super_admin' : 'admin';
$currentUid = current_admin_id();
$isSelf = $currentUid !== null && $currentUid === $id;
$counts = admin_counts($pdo);
$canDelete = !$isSelf && !($isSuperRow && $counts['super'] <= 1);

require __DIR__ . '/inc/header.php';
?>

<div class="admin-admins-edit-page">
  <nav class="admin-detail-breadcrumb admin-detail-breadcrumb--bar" aria-label="Navigation">
    <a href="<?= htmlspecialchars($hrefList, ENT_QUOTES, 'UTF-8') ?>">
      <span aria-hidden="true">←</span> Utilisateurs
    </a>
    <span class="admin-detail-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="admin-detail-breadcrumb__current" aria-current="page"><?= htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8') ?></span>
  </nav>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert admin-alert--error"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <section class="admin-panel admin-admins-edit-panel">
    <header class="admin-admins-edit-panel__head">
      <span class="admin-admins-edit-panel__eyebrow">Super-administrateur</span>
      <h1 class="admin-admins-edit-panel__title">Modifier le compte</h1>
      <p class="admin-admins-edit-panel__hint">Seuls les super-administrateurs peuvent modifier ou supprimer des comptes. La suppression est définitive.</p>
    </header>

    <form method="post" class="admin-form admin-admins-edit-form" autocomplete="off">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int) $id ?>">

      <div class="admin-grid-2">
        <div class="admin-field">
          <label for="first_name">Prénom</label>
          <input type="text" id="first_name" name="first_name" required maxlength="128" autocomplete="given-name"
            value="<?= htmlspecialchars(trim((string) ($row['first_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="admin-field">
          <label for="last_name">Nom</label>
          <input type="text" id="last_name" name="last_name" required maxlength="128" autocomplete="family-name"
            value="<?= htmlspecialchars(trim((string) ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="admin-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required maxlength="255"
            value="<?= htmlspecialchars((string) $row['email'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="admin-field">
          <label for="role">Rôle</label>
          <?php if ($isSuperRow && $counts['super'] <= 1): ?>
            <input type="hidden" name="role" value="super_admin">
            <select id="role" class="admin-create-form__status" disabled aria-disabled="true">
              <option value="super_admin" selected>Super-administrateur</option>
            </select>
            <p class="admin-create-form__help">Seul super-administrateur : impossible de rétrograder ce compte ici. Promouvez d’abord un autre utilisateur.</p>
          <?php else: ?>
            <select id="role" name="role" class="admin-create-form__status" required>
              <option value="admin" <?= $roleVal === 'admin' ? 'selected' : '' ?>>Administrateur</option>
              <option value="super_admin" <?= $roleVal === 'super_admin' ? 'selected' : '' ?>>Super-administrateur</option>
            </select>
          <?php endif; ?>
        </div>
        <div class="admin-field">
          <label for="password">Nouveau mot de passe</label>
          <input type="password" id="password" name="password" autocomplete="new-password" placeholder="Laisser vide pour ne pas changer">
        </div>
        <div class="admin-field">
          <label for="password_confirm">Confirmer le mot de passe</label>
          <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" placeholder="Si vous changez le mot de passe">
        </div>
      </div>

      <div class="admin-admins-edit-form__actions">
        <button type="submit" class="btn-primary">Enregistrer les modifications</button>
        <a class="admin-btn admin-btn--ghost" href="<?= htmlspecialchars($hrefList, ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
      </div>
    </form>

    <?php if ($canDelete): ?>
      <div class="admin-admins-edit-danger">
        <h2 class="admin-admins-edit-danger__title">Zone sensible</h2>
        <p class="admin-admins-edit-danger__text">Supprimer définitivement ce compte et ses données de connexion.</p>
        <form method="post" class="admin-inline-form" onsubmit="return confirm('Supprimer définitivement ce compte administrateur ?');">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int) $id ?>">
          <button type="submit" class="admin-btn admin-btn--danger admin-admins-edit-danger__btn">Supprimer ce compte</button>
        </form>
      </div>
    <?php elseif (!$isSelf): ?>
      <p class="admin-create-form__help admin-admins-edit-footnote">Ce super-administrateur ne peut pas être supprimé tant qu’il est le seul dans l’équipe.</p>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
