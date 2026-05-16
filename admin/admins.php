<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_super_admin();

$pageTitle = 'Utilisateurs — Goo-Bridge Admin';
$activeNav = 'admins';

$pdo = db();
$admins = $pdo->query(
    'SELECT id, email, first_name, last_name, COALESCE(is_super_admin, 0) AS is_super_admin, created_at,
            locked_until, COALESCE(failed_login_attempts, 0) AS failed_login_attempts
     FROM admins ORDER BY id ASC'
)->fetchAll();

$flash = isset($_GET['flash']) ? (string) $_GET['flash'] : '';
$flashOk = ['created' => true, 'updated' => true, 'deleted' => true];
$flashMsg = '';
if ($flash === 'csrf') {
    $flashMsg = 'Session invalide — rechargez la page.';
} elseif ($flash === 'error') {
    $flashMsg = 'Une erreur est survenue.';
} elseif ($flash === 'notfound') {
    $flashMsg = 'Compte introuvable.';
} elseif ($flash === 'selfdelete') {
    $flashMsg = 'Vous ne pouvez pas supprimer votre propre compte.';
} elseif ($flash === 'lastsuper') {
    $flashMsg = 'Impossible de supprimer ou de rétrograder le dernier super-administrateur.';
} elseif ($flash === 'created') {
    $flashMsg = 'Compte créé avec succès.';
} elseif ($flash === 'updated') {
    $flashMsg = 'Compte mis à jour.';
} elseif ($flash === 'deleted') {
    $flashMsg = 'Compte supprimé.';
}

$currentId = current_admin_id();
$superCount = 0;
foreach ($admins as $ax) {
    if (((int) ($ax['is_super_admin'] ?? 0)) === 1) {
        $superCount++;
    }
}

$hrefCreate = url('admin/admins_create.php');

require __DIR__ . '/inc/header.php';
?>

<div class="admin-admins-page">
  <header class="admin-admins-hero">
    <div class="admin-admins-hero__grid">
      <div class="admin-admins-hero__intro">
        <span class="admin-admins-hero__eyebrow">Équipe</span>
        <h1 class="admin-admins-hero__title">Utilisateurs</h1>
        <div class="admin-admins-hero__cta">
          <a class="btn-primary admin-admins-hero__btn-pri" href="<?= htmlspecialchars($hrefCreate, ENT_QUOTES, 'UTF-8') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
            Nouvel administrateur
          </a>
        </div>
      </div>
      <div class="admin-admins-hero__visual" aria-hidden="true">
        <div class="admin-admins-hero__orb admin-admins-hero__orb--one"></div>
        <div class="admin-admins-hero__orb admin-admins-hero__orb--two"></div>
        <div class="admin-admins-hero__stat">
          <span class="admin-admins-hero__stat-label">Comptes</span>
          <span class="admin-admins-hero__stat-num"><?= count($admins) ?></span>
          <span class="admin-admins-hero__stat-sub"><?= $superCount ?> super-admin<?= $superCount !== 1 ? 's' : '' ?></span>
        </div>
      </div>
    </div>
  </header>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert admin-admins-page__flash <?= isset($flashOk[$flash]) ? 'admin-alert--ok' : 'admin-alert--error' ?>" role="status"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <section class="admin-panel admin-admins-board">
    <?php if ($admins === []): ?>
      <p class="admin-admins-empty">Aucun compte. Créez un premier administrateur avec le bouton ci-dessus.</p>
    <?php else: ?>
      <ul class="admin-admins-grid" role="list">
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
            $parts = array_filter([
                trim((string) ($a['first_name'] ?? '')),
                trim((string) ($a['last_name'] ?? '')),
            ]);
            $displayName = implode(' ', $parts);
            $isSelf = $currentId !== null && $aid === $currentId;
            $canDelete = !$isSelf && !($isSuper && $superCount <= 1);
            $editHref = url('admin/admins_edit.php?id=' . $aid);
          ?>
          <li>
            <article class="admin-admin-card<?= $isSelf ? ' admin-admin-card--self' : '' ?>">
              <header class="admin-admin-card__header">
                <span class="admin-admin-card__avatar" aria-hidden="true"><?= htmlspecialchars($displayName !== '' ? mb_strtoupper(mb_substr($displayName, 0, 1, 'UTF-8'), 'UTF-8') : '?', ENT_QUOTES, 'UTF-8') ?></span>
                <div class="admin-admin-card__identity">
                  <h2 class="admin-admin-card__name">
                    <?= $displayName !== ''
                        ? htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8')
                        : '<span class="admin-users-noname">—</span>' ?>
                    <?php if ($isSelf): ?>
                      <span class="admin-users-you">(vous)</span>
                    <?php endif; ?>
                  </h2>
                  <p class="admin-admin-card__email"><?= htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8') ?></p>
                </div>
                <span class="admin-admin-card__badge <?= $isSuper ? 'admin-admin-card__badge--super' : '' ?>">
                  <?= $isSuper ? 'Super-admin' : 'Administrateur' ?>
                </span>
              </header>
              <dl class="admin-admin-card__meta">
                <div class="admin-admin-card__meta-row">
                  <dt>Créé le</dt>
                  <dd><?= htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') ?></dd>
                </div>
                <div class="admin-admin-card__meta-row">
                  <dt>Sécurité</dt>
                  <dd>
                    <?php if ($isLocked): ?>
                      <span class="admin-badge admin-badge--paused" title="<?= htmlspecialchars((string) $lockedRaw, ENT_QUOTES, 'UTF-8') ?>">Bloqué</span>
                    <?php elseif ((int) ($a['failed_login_attempts'] ?? 0) > 0): ?>
                      <span class="admin-users-sec-hint"><?= (int) $a['failed_login_attempts'] ?> échec<?= (int) $a['failed_login_attempts'] !== 1 ? 's' : '' ?></span>
                    <?php else: ?>
                      <span class="admin-users-sec-ok">OK</span>
                    <?php endif; ?>
                  </dd>
                </div>
              </dl>
              <footer class="admin-admin-card__footer">
                <a class="admin-admin-card__btn admin-admin-card__btn--primary" href="<?= htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8') ?>">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4z" /></svg>
                  Modifier
                </a>
                <?php if ($canDelete): ?>
                  <form method="post" action="<?= htmlspecialchars(url('admin/admins_edit.php'), ENT_QUOTES, 'UTF-8') ?>" class="admin-inline-form admin-admin-card__del-form" onsubmit="return confirm('Supprimer définitivement ce compte ?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $aid ?>">
                    <button type="submit" class="admin-admin-card__btn admin-admin-card__btn--danger">Supprimer</button>
                  </form>
                <?php endif; ?>
              </footer>
            </article>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
