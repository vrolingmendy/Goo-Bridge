<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_super_admin();

$pageTitle = 'Utilisateurs — Goo-Bridge Admin';
$activeNav = 'admins';

$pdo = db();
$admins = $pdo->query(
    'SELECT id, email, first_name, last_name, COALESCE(is_super_admin, 0) AS is_super_admin, created_at,
            locked_until, COALESCE(failed_login_attempts, 0) AS failed_login_attempts
     FROM admins ORDER BY id ASC'
)->fetchAll();

$createdFlash = isset($_GET['created']) && $_GET['created'] === '1';
$currentId = current_admin_id();

require __DIR__ . '/inc/header.php';
?>

<h1>Utilisateurs</h1>

<?php if ($createdFlash): ?>
  <p class="admin-alert admin-alert--ok" role="status">Compte créé avec succès.</p>
<?php endif; ?>

<div class="admin-panel admin-panel--admins">
  <div class="admin-users-toolbar">
    <div class="admin-users-toolbar__intro">
      <h2 class="admin-users-toolbar__title">Comptes administrateurs <span class="admin-heading-count"><?= count($admins) ?></span></h2>
      <p class="admin-users-toolbar__hint">Rôles et accès au tableau de bord.</p>
    </div>
    <a href="<?= htmlspecialchars(url('admin/admins_create.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-primary admin-users-toolbar__cta">Créer un administrateur</a>
  </div>

  <?php if ($admins === []): ?>
    <p class="admin-lead" style="margin:0;">Aucun compte. Utilisez le bouton ci-dessus pour en ajouter un.</p>
  <?php else: ?>
    <div class="admin-table-wrap">
      <table class="admin-table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Créé le</th>
            <th>Sécurité connexion</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($admins as $a): ?>
            <?php
              $aid = (int) $a['id'];
              $isSuper = ((int) ($a['is_super_admin'] ?? 0)) === 1;
              $lockedRaw = $a['locked_until'];
              $lockedTs = ($lockedRaw !== null && $lockedRaw !== '') ? strtotime((string) $lockedRaw) : false;
              $isLocked = $lockedTs !== false && $lockedTs > time();
              $createdFmt = '';
              if (!empty($a['created_at'])) {
                  $ct = strtotime((string) $a['created_at']);
                  $createdFmt = $ct !== false ? date('d/m/Y à H:i', $ct) : (string) $a['created_at'];
              }
            ?>
            <tr>
              <td>
                <?php
                  $parts = array_filter([
                      trim((string) ($a['first_name'] ?? '')),
                      trim((string) ($a['last_name'] ?? '')),
                  ]);
                  $displayName = implode(' ', $parts);
                ?>
                <?= $displayName !== ''
                    ? htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8')
                    : '<span class="admin-users-noname">—</span>' ?>
                <?php if ($currentId !== null && $aid === $currentId): ?>
                  <span class="admin-users-you">(vous)</span>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8') ?></td>
              <td><?= $isSuper ? 'Super-administrateur' : 'Administrateur' ?></td>
              <td><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></td>
              <td>
                <?php if ($isLocked): ?>
                  <span class="admin-badge admin-badge--paused" title="<?= htmlspecialchars((string) $lockedRaw, ENT_QUOTES, 'UTF-8') ?>">Bloqué temporairement</span>
                <?php elseif ((int) ($a['failed_login_attempts'] ?? 0) > 0): ?>
                  <span class="admin-users-sec-hint"><?= (int) $a['failed_login_attempts'] ?> échec<?= (int) $a['failed_login_attempts'] !== 1 ? 's' : '' ?> récent<?= (int) $a['failed_login_attempts'] !== 1 ? 's' : '' ?></span>
                <?php else: ?>
                  <span class="admin-users-sec-ok">OK</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
