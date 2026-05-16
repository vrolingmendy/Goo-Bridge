<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/client_logo.php';

require_admin();

$pageTitle = 'Clients & entreprises — Goo-Bridge Admin';
$activeNav = 'clients';

$flash = isset($_GET['flash']) ? (string) $_GET['flash'] : '';
$flashOk = [
    'created' => 'Entreprise ajoutée.',
    'created_logo_skip' => 'Entreprise ajoutée. Le logo n’a pas été importé (JPG, PNG, WebP ou GIF — max. 2 Mo).',
    'updated' => 'Fiche mise à jour.',
    'deleted' => 'Entreprise supprimée.',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        header('Location: ' . url('admin/clients.php?flash=csrf'), true, 302);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        $pdo = db();

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                client_logo_delete_files_for_client($id);
                $stmt = $pdo->prepare('DELETE FROM clients WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
            }
            header('Location: ' . url('admin/clients.php?flash=deleted'), true, 302);
            exit;
        }
    } catch (Throwable $e) {
        header('Location: ' . url('admin/clients.php?flash=error'), true, 302);
        exit;
    }

    header('Location: ' . url('admin/clients.php'), true, 302);
    exit;
}

$pdo = db();
$clients = $pdo->query(
    'SELECT c.*, COALESCE(y.cnt, 0) AS maintenances_done_year
     FROM clients c
     LEFT JOIN (
       SELECT client_id, COUNT(*) AS cnt
       FROM client_maintenances
       WHERE YEAR(performed_at) = YEAR(CURDATE())
       GROUP BY client_id
     ) y ON y.client_id = c.id
     ORDER BY c.company_name ASC'
)->fetchAll();

$totalClients = count($clients);
$statusTotals = ['active' => 0, 'paused' => 0, 'completed' => 0];
$billingTotal = 0;
foreach ($clients as $cRow) {
    $s = (string) ($cRow['status'] ?? '');
    if (isset($statusTotals[$s])) {
        $statusTotals[$s]++;
    }
    $qRow = (int) ($cRow['maintenances_per_year'] ?? 0);
    $dRow = (int) ($cRow['maintenances_done_year'] ?? 0);
    $overflowRow = $qRow === 0 ? $dRow : max(0, $dRow - $qRow);
    if ($overflowRow > 0) {
        $billingTotal++;
    }
}

$hrefDashboard = url('admin/dashboard.php');
$hrefNewCompany = url('admin/clients_new.php');

$flashMsg = '';
if ($flash === 'csrf') {
    $flashMsg = 'Session invalide — rechargez la page.';
} elseif ($flash === 'error') {
    $flashMsg = 'Une erreur est survenue.';
} elseif (isset($flashOk[$flash])) {
    $flashMsg = $flashOk[$flash];
}

function admin_status_badge_row(string $status): string
{
    return match ($status) {
        'active' => '<span class="admin-badge admin-badge--active">Actif</span>',
        'paused' => '<span class="admin-badge admin-badge--paused">En pause</span>',
        'completed' => '<span class="admin-badge admin-badge--completed">Terminé</span>',
        default => htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
    };
}

function admin_client_initials(string $name): string
{
    $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');
    if ($name === '') {
        return '?';
    }
    $parts = explode(' ', $name);
    if (count($parts) === 1) {
        return mb_strtoupper(mb_substr($parts[0], 0, 2, 'UTF-8'), 'UTF-8');
    }
    $first = mb_substr($parts[0], 0, 1, 'UTF-8');
    $last = mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
    return mb_strtoupper($first . $last, 'UTF-8');
}

function admin_client_avatar_palette(string $name): array
{
    $palettes = [
        ['#16a34a', '#22c55e'],
        ['#0ea5e9', '#38bdf8'],
        ['#8b5cf6', '#a78bfa'],
        ['#f97316', '#fb923c'],
        ['#ec4899', '#f472b6'],
        ['#14b8a6', '#2dd4bf'],
        ['#f59e0b', '#fbbf24'],
        ['#6366f1', '#818cf8'],
    ];
    $hash = abs(crc32($name));
    return $palettes[$hash % count($palettes)];
}

require __DIR__ . '/inc/header.php';
?>

<div class="admin-clients-page">
  <header class="admin-clients-hero">
    <div class="admin-clients-hero__grid">
      <div class="admin-clients-hero__intro">
        <span class="admin-clients-hero__eyebrow">Répertoire</span>
        <h1 class="admin-clients-hero__title">Clients &amp; entreprises</h1>
        <div class="admin-clients-hero__cta">
          <a class="btn-primary admin-clients-hero__btn-pri" href="<?= htmlspecialchars($hrefDashboard, ENT_QUOTES, 'UTF-8') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />
              <polyline points="9 22 9 12 15 12 15 22" />
            </svg>
            Tableau de bord
          </a>
          <a class="admin-clients-hero__btn-ghost" href="<?= htmlspecialchars($hrefNewCompany, ENT_QUOTES, 'UTF-8') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 5v14" />
              <path d="M5 12h14" />
            </svg>
            Ajouter une entreprise
          </a>
        </div>
      </div>
      <div class="admin-clients-hero__visual" aria-hidden="true">
        <div class="admin-clients-hero__orb admin-clients-hero__orb--one"></div>
        <div class="admin-clients-hero__orb admin-clients-hero__orb--two"></div>
        <div class="admin-clients-hero__card-floating">
          <span class="admin-clients-hero__card-label">Portefeuille</span>
          <span class="admin-clients-hero__card-num"><?= $totalClients ?></span>
          <span class="admin-clients-hero__card-sub">entreprise<?= $totalClients !== 1 ? 's' : '' ?> répertoriée<?= $totalClients !== 1 ? 's' : '' ?></span>
        </div>
      </div>
    </div>
  </header>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert admin-clients-page__flash <?= isset($flashOk[$flash]) ? 'admin-alert--ok' : 'admin-alert--error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

<section class="admin-panel admin-panel--client-directory admin-board">
  <header class="admin-board__head">
    <div class="admin-board__head-text">
      <span class="admin-board__eyebrow">Annuaire</span>
      <h2>Toutes les entreprises <span class="admin-heading-count"><?= $totalClients ?></span></h2>
    </div>
    <?php if ($totalClients > 0): ?>
      <ul class="admin-board__stats" role="list">
        <li class="admin-board__stat admin-board__stat--active">
          <span class="admin-board__stat-dot" aria-hidden="true"></span>
          <span class="admin-board__stat-num"><?= $statusTotals['active'] ?></span>
          <span class="admin-board__stat-label">Actif<?= $statusTotals['active'] > 1 ? 's' : '' ?></span>
        </li>
        <li class="admin-board__stat admin-board__stat--paused">
          <span class="admin-board__stat-dot" aria-hidden="true"></span>
          <span class="admin-board__stat-num"><?= $statusTotals['paused'] ?></span>
          <span class="admin-board__stat-label">Pause</span>
        </li>
        <li class="admin-board__stat admin-board__stat--completed">
          <span class="admin-board__stat-dot" aria-hidden="true"></span>
          <span class="admin-board__stat-num"><?= $statusTotals['completed'] ?></span>
          <span class="admin-board__stat-label">Terminé<?= $statusTotals['completed'] > 1 ? 's' : '' ?></span>
        </li>
        <?php if ($billingTotal > 0): ?>
          <li class="admin-board__stat admin-board__stat--billing">
            <span class="admin-board__stat-dot" aria-hidden="true"></span>
            <span class="admin-board__stat-num"><?= $billingTotal ?></span>
            <span class="admin-board__stat-label">À facturer</span>
          </li>
        <?php endif; ?>
      </ul>
    <?php endif; ?>
  </header>

  <?php if ($clients === []): ?>
    <div class="admin-board__empty">
      <div class="admin-board__empty-icon" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 21V8l9-5 9 5v13" />
          <path d="M9 21v-7h6v7" />
        </svg>
      </div>
      <h3>Aucune entreprise enregistrée</h3>
      <p>Ouvrez la page « Ajouter une entreprise » depuis le bouton ci-dessus pour créer votre première fiche client.</p>
    </div>
  <?php else: ?>
    <div class="admin-board__grid">
      <?php foreach ($clients as $c): ?>
        <?php
          $companyName = (string) $c['company_name'];
          $ptype = trim((string) ($c['project_type'] ?? ''));
          $web = trim((string) ($c['website_url'] ?? ''));
          $quota = (int) ($c['maintenances_per_year'] ?? 0);
          $doneYear = (int) ($c['maintenances_done_year'] ?? 0);
          $remaining = $quota > 0 ? max(0, $quota - $doneYear) : 0;
          $progress = $quota > 0 ? min(100, (int) round(($doneYear / $quota) * 100)) : 0;
          $overflow = $quota === 0 ? $doneYear : max(0, $doneYear - $quota);
          $needsBilling = $overflow > 0;
          $year = (int) date('Y');
          $siteShort = '';
          if ($web !== '') {
              $parsed = parse_url($web);
              if (is_array($parsed) && !empty($parsed['host'])) {
                  $siteShort = (string) $parsed['host'];
              } else {
                  $siteShort = strlen($web) > 42 ? substr($web, 0, 42) . '…' : $web;
              }
          }
          $initials = admin_client_initials($companyName);
          $palette = admin_client_avatar_palette($companyName);
          $tileLogoUrl = client_logo_public_url(isset($c['logo_path']) ? (string) $c['logo_path'] : null);
          $detailHref = url('admin/client_detail.php?id=' . (int) $c['id']);
          $editHref = url('admin/clients_edit.php?id=' . (int) $c['id']);
        ?>
        <article class="admin-tile admin-tile--<?= htmlspecialchars((string) $c['status'], ENT_QUOTES, 'UTF-8') ?><?= $needsBilling ? ' admin-tile--billing-alert' : '' ?>">
          <header class="admin-tile__header">
            <?php if ($tileLogoUrl !== null): ?>
              <span class="admin-tile__avatar admin-tile__avatar--logo" aria-hidden="true">
                <img src="<?= htmlspecialchars($tileLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="46" height="46" loading="lazy" decoding="async" />
              </span>
            <?php else: ?>
              <span class="admin-tile__avatar" aria-hidden="true" style="--avatar-from: <?= $palette[0] ?>; --avatar-to: <?= $palette[1] ?>;"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
            <div class="admin-tile__identity">
              <h3 class="admin-tile__title">
                <a href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></a>
              </h3>
              <p class="admin-tile__subtitle"><?= $ptype !== '' ? htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') : 'Type non renseigné' ?></p>
            </div>
            <div class="admin-tile__status"><?= admin_status_badge_row((string) $c['status']) ?></div>
          </header>

          <div class="admin-tile__body">
            <section class="admin-tile__field" aria-label="Site livré">
              <div class="admin-tile__field-head">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" />
                  <path d="M2 12h20" />
                  <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                </svg>
                <span>Site livré</span>
              </div>
              <?php if ($web !== ''): ?>
                <a class="admin-tile__site" href="<?= htmlspecialchars($web, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($web, ENT_QUOTES, 'UTF-8') ?>">
                  <span class="admin-tile__site-host"><?= htmlspecialchars($siteShort, ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="admin-tile__site-cta">Ouvrir <span aria-hidden="true">↗</span></span>
                </a>
              <?php else: ?>
                <span class="admin-tile__empty">Aucune URL renseignée</span>
              <?php endif; ?>
            </section>

            <section class="admin-tile__field<?= $needsBilling ? ' admin-tile__field--billing' : '' ?>" aria-label="Maintenance annuelle">
              <div class="admin-tile__field-head">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <path d="M14.7 6.3a4 4 0 0 0-5.4 5.4l-6 6a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l6-6a4 4 0 0 0 5.4-5.4l-2 2-2-2-2-2 2-2z" />
                </svg>
                <span>Maintenance <?= $year ?></span>
                <?php if ($needsBilling): ?>
                  <span class="admin-tile__billing-badge" title="Maintenances effectuées hors quota — à facturer">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M12 9v4" />
                      <path d="M12 17h.01" />
                      <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                    </svg>
                    À facturer
                  </span>
                <?php endif; ?>
              </div>
              <?php if ($quota > 0): ?>
                <div class="admin-tile__maint<?= $needsBilling ? ' admin-tile__maint--alert' : '' ?>">
                  <div class="admin-tile__maint-top">
                    <?php if ($needsBilling): ?>
                      <span class="admin-tile__maint-remain admin-tile__maint-remain--alert">+<?= $overflow ?></span>
                      <span class="admin-tile__maint-text admin-tile__maint-text--alert">au-delà du quota · <?= $doneYear ?>/<?= $quota ?></span>
                    <?php else: ?>
                      <span class="admin-tile__maint-remain"><?= $remaining ?></span>
                      <span class="admin-tile__maint-text">restante<?= $remaining !== 1 ? 's' : '' ?> · <?= $doneYear ?>/<?= $quota ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="admin-tile__progress<?= $needsBilling ? ' admin-tile__progress--alert' : '' ?>" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $quota ?>" aria-valuenow="<?= $doneYear ?>" aria-label="<?= $doneYear ?> maintenances sur <?= $quota ?>">
                    <span class="admin-tile__progress-fill<?= $needsBilling ? ' admin-tile__progress-fill--alert' : '' ?>" style="width: <?= $needsBilling ? 100 : $progress ?>%"></span>
                  </div>
                  <?php if ($needsBilling): ?>
                    <p class="admin-tile__maint-bill"><strong><?= $overflow ?></strong> intervention<?= $overflow !== 1 ? 's' : '' ?> hors quota — l'entreprise doit payer.</p>
                  <?php endif; ?>
                </div>
              <?php else: ?>
                <div class="admin-tile__maint admin-tile__maint--no-quota<?= $needsBilling ? ' admin-tile__maint--alert' : '' ?>">
                  <?php if ($needsBilling): ?>
                    <div class="admin-tile__maint-top">
                      <span class="admin-tile__maint-remain admin-tile__maint-remain--alert"><?= $doneYear ?></span>
                      <span class="admin-tile__maint-text admin-tile__maint-text--alert">effectuée<?= $doneYear !== 1 ? 's' : '' ?> · aucun quota</span>
                    </div>
                    <p class="admin-tile__maint-bill"><strong>Aucun quota défini</strong> — chaque intervention est à facturer.</p>
                  <?php else: ?>
                    <p class="admin-tile__maint-text"><strong><?= $doneYear ?></strong> effectuée<?= $doneYear !== 1 ? 's' : '' ?></p>
                    <p class="admin-tile__maint-text admin-tile__maint-text--muted">Quota / an non défini</p>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </section>
          </div>

          <footer class="admin-tile__footer">
            <a class="admin-tile__action admin-tile__action--primary" href="<?= htmlspecialchars($detailHref, ENT_QUOTES, 'UTF-8') ?>">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
              Détails
            </a>
            <a class="admin-tile__action admin-tile__action--ghost" href="<?= htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8') ?>" title="Modifier">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 20h9" />
                <path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4z" />
              </svg>
              <span class="admin-tile__action-label">Modifier</span>
            </a>
            <form method="post" class="admin-inline-form admin-tile__delete">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button type="submit" class="admin-tile__action admin-tile__action--danger" title="Supprimer cette entreprise" onclick="return confirm('Supprimer définitivement <?= htmlspecialchars(addslashes($companyName), ENT_QUOTES, 'UTF-8') ?> ?');">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <polyline points="3 6 5 6 21 6" />
                  <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                  <path d="M10 11v6" />
                  <path d="M14 11v6" />
                </svg>
                <span class="admin-tile__action-label">Supprimer</span>
              </button>
            </form>
          </footer>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
