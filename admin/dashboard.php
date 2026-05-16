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
    'SELECT id, company_name, project_type, status, website_url, updated_at FROM clients ORDER BY updated_at DESC LIMIT 8'
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

function admin_dashboard_initials(string $name): string
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

/** @return array{0: string, 1: string} */
function admin_dashboard_avatar_palette(string $name): array
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

function admin_dashboard_format_updated(?string $sqlTs): string
{
    if ($sqlTs === null || $sqlTs === '') {
        return '—';
    }
    $ts = strtotime($sqlTs);

    return $ts !== false ? date('d/m/Y · H:i', $ts) : '—';
}

$hrefClients = url('admin/clients.php');
$hrefNew = url('admin/clients_new.php');
$hrefTickets = url('admin/support_tickets.php');

// Compteurs tickets (avec garde si la table n'existe pas encore)
$ticketsTotal = 0;
$ticketsOpen = 0;
try {
    $ticketsTotal = (int) $pdo->query('SELECT COUNT(*) FROM client_support_tickets')->fetchColumn();
    $ticketsOpen = (int) $pdo->query("SELECT COUNT(*) FROM client_support_tickets WHERE status = 'open'")->fetchColumn();
} catch (Throwable $e) {
    $ticketsTotal = 0;
    $ticketsOpen = 0;
}

require __DIR__ . '/inc/header.php';
?>

<div class="admin-dashboard">
  <header class="admin-dash-hero">
    <div class="admin-dash-hero__grid">
      <div class="admin-dash-hero__intro">
        <span class="admin-dash-hero__eyebrow">Pilotage</span>
        <h1 class="admin-dash-hero__title">Tableau de bord</h1>
        <p class="admin-dash-hero__lead">Vue consolidée du portefeuille et des dossiers les plus récemment mis à jour.</p>
        <div class="admin-dash-hero__cta">
          <a class="btn-primary admin-dash-hero__btn-pri" href="<?= htmlspecialchars($hrefClients, ENT_QUOTES, 'UTF-8') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></svg>
            Clients &amp; entreprises
          </a>
          <a class="admin-dash-hero__btn-ghost" href="<?= htmlspecialchars($hrefNew, ENT_QUOTES, 'UTF-8') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14" /><path d="M5 12h14" /></svg>
            Nouvelle entreprise
          </a>
          <a class="admin-dash-hero__btn-tickets" href="<?= htmlspecialchars($hrefTickets, ENT_QUOTES, 'UTF-8') ?>">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4v-2z"/><path d="M9 5v14"/></svg>
            Tickets
            <?php if ($ticketsOpen > 0): ?>
              <span class="admin-dash-hero__btn-badge" aria-label="<?= (int) $ticketsOpen ?> ouverts"><?= (int) $ticketsOpen ?></span>
            <?php endif; ?>
          </a>
        </div>
      </div>
      <div class="admin-dash-hero__visual" aria-hidden="true">
        <div class="admin-dash-hero__orb admin-dash-hero__orb--one"></div>
        <div class="admin-dash-hero__orb admin-dash-hero__orb--two"></div>
        <div class="admin-dash-hero__card-floating">
          <span class="admin-dash-hero__card-label">Portefeuille</span>
          <span class="admin-dash-hero__card-num"><?= $totalClients ?></span>
          <span class="admin-dash-hero__card-sub">entreprise<?= $totalClients !== 1 ? 's' : '' ?> suivie<?= $totalClients !== 1 ? 's' : '' ?></span>
        </div>
      </div>
    </div>
  </header>

  <section class="admin-dash-kpis" aria-label="Indicateurs clés">
    <article class="admin-dash-kpi admin-dash-kpi--total">
      <div class="admin-dash-kpi__icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg>
      </div>
      <div class="admin-dash-kpi__body">
        <span class="admin-dash-kpi__value"><?= $totalClients ?></span>
        <span class="admin-dash-kpi__label">Clients enregistrés</span>
      </div>
    </article>
    <article class="admin-dash-kpi admin-dash-kpi--active">
      <div class="admin-dash-kpi__icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>
      </div>
      <div class="admin-dash-kpi__body">
        <span class="admin-dash-kpi__value"><?= $statusCounts['active'] ?></span>
        <span class="admin-dash-kpi__label">Projets actifs</span>
      </div>
    </article>
    <article class="admin-dash-kpi admin-dash-kpi--paused">
      <div class="admin-dash-kpi__icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><line x1="10" y1="15" x2="10" y2="9" /><line x1="14" y1="15" x2="14" y2="9" /></svg>
      </div>
      <div class="admin-dash-kpi__body">
        <span class="admin-dash-kpi__value"><?= $statusCounts['paused'] ?></span>
        <span class="admin-dash-kpi__label">En pause</span>
      </div>
    </article>
    <article class="admin-dash-kpi admin-dash-kpi--done">
      <div class="admin-dash-kpi__icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" /></svg>
      </div>
      <div class="admin-dash-kpi__body">
        <span class="admin-dash-kpi__value"><?= $statusCounts['completed'] ?></span>
        <span class="admin-dash-kpi__label">Terminés</span>
      </div>
    </article>
    <a class="admin-dash-kpi admin-dash-kpi--tickets" href="<?= htmlspecialchars($hrefTickets, ENT_QUOTES, 'UTF-8') ?>">
      <div class="admin-dash-kpi__icon" aria-hidden="true">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4v-2z"/><path d="M9 5v14"/></svg>
      </div>
      <div class="admin-dash-kpi__body">
        <span class="admin-dash-kpi__value"><?= $ticketsOpen ?></span>
        <span class="admin-dash-kpi__label">Tickets ouverts<?= $ticketsTotal > 0 ? ' / ' . $ticketsTotal . ' total' : '' ?></span>
      </div>
    </a>
  </section>

  <section class="admin-panel admin-dash-feed">
    <header class="admin-dash-feed__head">
      <div class="admin-dash-feed__head-text">
        <span class="admin-dash-feed__eyebrow">Activité</span>
        <h2 class="admin-dash-feed__title">Derniers dossiers mis à jour</h2>
      </div>
      <a class="admin-dash-feed__all" href="<?= htmlspecialchars($hrefClients, ENT_QUOTES, 'UTF-8') ?>">
        Tout l’annuaire
        <span aria-hidden="true">→</span>
      </a>
    </header>

    <?php if ($recent === []): ?>
      <div class="admin-dash-empty">
        <div class="admin-dash-empty__icon" aria-hidden="true">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><line x1="19" y1="8" x2="19" y2="14" /><line x1="22" y1="11" x2="16" y2="11" /></svg>
        </div>
        <h3 class="admin-dash-empty__title">Aucune entreprise encore</h3>
        <p class="admin-dash-empty__text">Créez votre première fiche pour voir les indicateurs et l’historique ici.</p>
        <a class="btn-primary admin-dash-empty__btn" href="<?= htmlspecialchars($hrefNew, ENT_QUOTES, 'UTF-8') ?>">Ajouter une entreprise</a>
      </div>
    <?php else: ?>
      <ul class="admin-dash-feed__list" role="list">
        <?php foreach ($recent as $r): ?>
          <?php
            $cid = (int) $r['id'];
            $cname = (string) $r['company_name'];
            $initials = admin_dashboard_initials($cname);
            $palette = admin_dashboard_avatar_palette($cname);
            $ptype = trim((string) ($r['project_type'] ?? ''));
            $detailUrl = url('admin/client_detail.php?id=' . $cid);
            $tasksUrl = url('admin/project_tasks.php?id=' . $cid);
            $st = (string) $r['status'];
          ?>
          <li>
            <article class="admin-dash-feed-card admin-dash-feed-card--<?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?>">
              <span class="admin-dash-feed-card__avatar" aria-hidden="true" style="--dash-avatar-from: <?= htmlspecialchars($palette[0], ENT_QUOTES, 'UTF-8') ?>; --dash-avatar-to: <?= htmlspecialchars($palette[1], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars($initials, ENT_QUOTES, 'UTF-8') ?></span>
              <div class="admin-dash-feed-card__main">
                <h3 class="admin-dash-feed-card__title">
                  <a href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cname, ENT_QUOTES, 'UTF-8') ?></a>
                </h3>
                <p class="admin-dash-feed-card__type"><?= $ptype !== '' ? htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') : 'Type non renseigné' ?></p>
                <div class="admin-dash-feed-card__meta">
                  <?= admin_status_badge($st) ?>
                  <span class="admin-dash-feed-card__updated" title="Dernière mise à jour sur la fiche">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>
                    <?= htmlspecialchars(admin_dashboard_format_updated((string) ($r['updated_at'] ?? '')), ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <?php if (!empty($r['website_url'])): ?>
                    <a class="admin-dash-feed-card__site" href="<?= htmlspecialchars($r['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Site ↗</a>
                  <?php endif; ?>
                </div>
              </div>
              <div class="admin-dash-feed-card__actions">
                <a class="admin-dash-feed-card__btn admin-dash-feed-card__btn--ghost" href="<?= htmlspecialchars($tasksUrl, ENT_QUOTES, 'UTF-8') ?>">Tâches</a>
                <a class="btn-primary admin-dash-feed-card__btn admin-dash-feed-card__btn--pri" href="<?= htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8') ?>">Fiche</a>
              </div>
            </article>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
