<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pageTitle = 'Dashboard administrateur — Goo-Bridge Admin';
$activeNav = 'dashboard';

$pdo = db();

$totalClients = (int) $pdo->query('SELECT COUNT(*) FROM clients')->fetchColumn();

$statusCounts = ['active' => 0, 'paused' => 0, 'completed' => 0];
$stmt = $pdo->query('SELECT status, COUNT(*) AS c FROM clients GROUP BY status');
while ($row = $stmt->fetch()) {
    $statusCounts[$row['status']] = (int) $row['c'];
}

$recent = $pdo->query(
    'SELECT id, company_name, project_type, status, website_url FROM clients ORDER BY updated_at DESC LIMIT 8'
)->fetchAll();

function admin_status_badge(string $status): string
{
    return match ($status) {
        'active' => '<span class="admin-badge admin-badge--active">Actif</span>',
        'paused' => '<span class="admin-badge admin-badge--paused">En pause</span>',
        'completed' => '<span class="admin-badge admin-badge--completed">Terminé</span>',
        default => htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
    };
}

require __DIR__ . '/inc/header.php';
?>

<h1>Dashboard administrateur</h1>

<div class="admin-cards">
  <div class="admin-card">
    <strong><?= $totalClients ?></strong>
    <span>Clients enregistrés</span>
  </div>
  <div class="admin-card">
    <strong><?= $statusCounts['active'] ?></strong>
    <span>Projets actifs</span>
  </div>
  <div class="admin-card">
    <strong><?= $statusCounts['paused'] ?></strong>
    <span>En pause</span>
  </div>
  <div class="admin-card">
    <strong><?= $statusCounts['completed'] ?></strong>
    <span>Terminés</span>
  </div>
</div>

<div class="admin-panel admin-panel--dash">
  <div class="admin-panel-head">
    <h2>Derniers dossiers</h2>
    <p class="admin-panel-head__hint">Aperçu rapide — ouvrez une fiche pour voir les dates et enregistrer les maintenances.</p>
  </div>
  <?php if ($recent === []): ?>
    <p class="admin-lead" style="margin:0;">Aucun client pour le moment. <a href="<?= htmlspecialchars(url('admin/clients.php'), ENT_QUOTES, 'UTF-8') ?>">Ajouter une entreprise</a>.</p>
  <?php else: ?>
    <div class="admin-dash-stack">
      <?php foreach ($recent as $r): ?>
        <article class="admin-dash-card">
          <div class="admin-dash-card__main">
            <h3 class="admin-dash-card__title"><?= htmlspecialchars($r['company_name'], ENT_QUOTES, 'UTF-8') ?></h3>
            <?php
              $ptype = trim((string) ($r['project_type'] ?? ''));
            ?>
            <p class="admin-dash-card__type"><?= $ptype !== '' ? htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') : 'Type non renseigné' ?></p>
            <div class="admin-dash-card__meta">
              <?= admin_status_badge((string) $r['status']) ?>
              <?php if (!empty($r['website_url'])): ?>
                <a class="admin-dash-card__link" href="<?= htmlspecialchars($r['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Site livré ↗</a>
              <?php endif; ?>
            </div>
          </div>
          <div class="admin-dash-card__actions">
            <a class="btn-primary admin-dash-detail" href="<?= htmlspecialchars(url('admin/client_detail.php?id=' . (int) $r['id']), ENT_QUOTES, 'UTF-8') ?>">Détails</a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="admin-dash-footer"><a class="admin-btn" href="<?= htmlspecialchars(url('admin/clients.php'), ENT_QUOTES, 'UTF-8') ?>">Gérer tous les clients</a></p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
