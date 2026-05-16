<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/money.php';
require_once __DIR__ . '/../includes/client_logo.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . url('admin/clients.php'), true, 302);
    exit;
}

$pdo = db();

function format_fr_datetime(?string $sqlTs): string
{
    if ($sqlTs === null || $sqlTs === '') {
        return '—';
    }
    $ts = strtotime($sqlTs);

    return $ts !== false ? date('d/m/Y à H:i', $ts) : htmlspecialchars($sqlTs, ENT_QUOTES, 'UTF-8');
}

function format_fr_date(?string $sqlDate): string
{
    if ($sqlDate === null || $sqlDate === '') {
        return '—';
    }
    $ts = strtotime($sqlDate);

    return $ts !== false ? date('d/m/Y', $ts) : htmlspecialchars($sqlDate, ENT_QUOTES, 'UTF-8');
}

function admin_status_badge_detail(string $status): string
{
    return match ($status) {
        'active' => '<span class="admin-badge admin-badge--active">Actif</span>',
        'paused' => '<span class="admin-badge admin-badge--paused">En pause</span>',
        'completed' => '<span class="admin-badge admin-badge--completed">Terminé</span>',
        default => htmlspecialchars($status, ENT_QUOTES, 'UTF-8'),
    };
}

function admin_detail_client_initials(string $name): string
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
function admin_detail_avatar_palette(string $name): array
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        header('Location: ' . url('admin/client_detail.php?id=' . $id . '&flash=csrf'), true, 302);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add_maintenance') {
            $rawDate = trim((string) ($_POST['performed_at'] ?? ''));
            $dt = DateTimeImmutable::createFromFormat('Y-m-d', $rawDate);
            $validDate = $dt !== false && $dt->format('Y-m-d') === $rawDate;

            if (!$validDate) {
                header('Location: ' . url('admin/client_detail.php?id=' . $id . '&flash=bad_date'), true, 302);
                exit;
            }

            $summary = trim((string) ($_POST['summary'] ?? ''));
            $notes = trim((string) ($_POST['notes'] ?? ''));

            $check = $pdo->prepare('SELECT id FROM clients WHERE id = :id LIMIT 1');
            $check->execute(['id' => $id]);
            if ($check->fetch() === false) {
                header('Location: ' . url('admin/clients.php'), true, 302);
                exit;
            }

            $publicToken = bin2hex(random_bytes(32));
            $ins = $pdo->prepare(
                'INSERT INTO client_maintenances (client_id, performed_at, summary, notes, public_token)
                 VALUES (:cid, :pdate, :summary, :notes, :ptok)'
            );
            $ins->execute([
                'cid' => $id,
                'pdate' => $rawDate,
                'summary' => $summary !== '' ? $summary : null,
                'notes' => $notes !== '' ? $notes : null,
                'ptok' => $publicToken,
            ]);

            header('Location: ' . url('admin/client_detail.php?id=' . $id . '&flash=maint_added'), true, 302);
            exit;
        }

        if ($action === 'delete_maintenance') {
            $mid = (int) ($_POST['maintenance_id'] ?? 0);
            if ($mid > 0) {
                $del = $pdo->prepare(
                    'DELETE FROM client_maintenances WHERE id = :mid AND client_id = :cid LIMIT 1'
                );
                $del->execute(['mid' => $mid, 'cid' => $id]);
            }
            header('Location: ' . url('admin/client_detail.php?id=' . $id . '&flash=maint_deleted'), true, 302);
            exit;
        }
    } catch (Throwable $e) {
        header('Location: ' . url('admin/client_detail.php?id=' . $id . '&flash=error'), true, 302);
        exit;
    }

    header('Location: ' . url('admin/client_detail.php?id=' . $id), true, 302);
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if ($row === false) {
    header('Location: ' . url('admin/clients.php'), true, 302);
    exit;
}

$mStmt = $pdo->prepare(
    'SELECT * FROM client_maintenances WHERE client_id = :cid ORDER BY performed_at DESC, id DESC'
);
$mStmt->execute(['cid' => $id]);
$maintenances = $mStmt->fetchAll();

$ensureTok = $pdo->prepare(
    'UPDATE client_maintenances SET public_token = :tok WHERE id = :id AND (public_token IS NULL OR public_token = \'\') LIMIT 1'
);
foreach ($maintenances as $idx => $m) {
    if (empty($m['public_token'])) {
        $tok = bin2hex(random_bytes(32));
        $ensureTok->execute(['tok' => $tok, 'id' => (int) $m['id']]);
        $maintenances[$idx]['public_token'] = $tok;
    }
}

$yStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM client_maintenances WHERE client_id = :cid AND YEAR(performed_at) = YEAR(CURDATE())'
);
$yStmt->execute(['cid' => $id]);
$maintDoneThisYear = (int) $yStmt->fetchColumn();
$quotaYear = (int) ($row['maintenances_per_year'] ?? 0);
$maintRemainingYear = $quotaYear > 0 ? max(0, $quotaYear - $maintDoneThisYear) : 0;
$calendarYear = (int) date('Y');

$projectTotal = 0;
$projectDone = 0;
$projectPending = 0;
try {
    $pStmt = $pdo->prepare(
        'SELECT status, COUNT(*) AS n FROM client_project_tasks WHERE client_id = :cid GROUP BY status'
    );
    $pStmt->execute(['cid' => $id]);
    foreach ($pStmt->fetchAll() as $r) {
        $n = (int) $r['n'];
        $projectTotal += $n;
        if ((string) $r['status'] === 'done') {
            $projectDone += $n;
        } else {
            $projectPending += $n;
        }
    }
} catch (Throwable $e) {
    // Table peut ne pas exister sur une base ancienne — ignoré silencieusement.
}

$pageTitle = 'Fiche — ' . $row['company_name'];
$activeNav = 'clients';

$flash = isset($_GET['flash']) ? (string) $_GET['flash'] : '';
$flashMsg = '';
$flashOk = false;

if ($flash === 'csrf') {
    $flashMsg = 'Session invalide — rechargez la page.';
} elseif ($flash === 'bad_date') {
    $flashMsg = 'Date de maintenance invalide.';
} elseif ($flash === 'error') {
    $flashMsg = 'Une erreur est survenue.';
} elseif ($flash === 'maint_added') {
    $flashMsg = 'Maintenance enregistrée.';
    $flashOk = true;
} elseif ($flash === 'maint_deleted') {
    $flashMsg = 'Entrée supprimée.';
    $flashOk = true;
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$ptypeDisplay = trim((string) ($row['project_type'] ?? ''));
$detailPhone = trim((string) ($row['phone'] ?? ''));
$detailCompanyName = (string) $row['company_name'];
$detailInitials = admin_detail_client_initials($detailCompanyName);
$detailPalette = admin_detail_avatar_palette($detailCompanyName);
$maintProgressPct = $quotaYear > 0 ? min(100, (int) round(($maintDoneThisYear / $quotaYear) * 100)) : 0;
$projectProgressPct = $projectTotal > 0 ? (int) round(($projectDone / $projectTotal) * 100) : 0;
$priceProject = (float) ($row['project_price'] ?? 0);
$priceHosting = (float) ($row['hosting_price'] ?? 0);
$priceMaintAnnual = (float) ($row['maintenance_annual_price'] ?? 0);
$billingCurrency = normalize_billing_currency((string) ($row['billing_currency'] ?? 'EUR'));
$detailLogoUrl = client_logo_public_url(isset($row['logo_path']) ? (string) $row['logo_path'] : null);

require __DIR__ . '/inc/header.php';
?>

<div class="admin-detail-page admin-detail-page--structured">
  <nav class="admin-detail-breadcrumb admin-detail-breadcrumb--bar" aria-label="Navigation">
    <a href="<?= htmlspecialchars(url('admin/clients.php'), ENT_QUOTES, 'UTF-8') ?>">
      <span aria-hidden="true">←</span> Clients
    </a>
    <span class="admin-detail-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="admin-detail-breadcrumb__current" aria-current="page"><?= htmlspecialchars($detailCompanyName, ENT_QUOTES, 'UTF-8') ?></span>
    <span class="admin-detail-breadcrumb__spacer" aria-hidden="true"></span>
    <a href="<?= htmlspecialchars(url('admin/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="admin-detail-breadcrumb__side">Tableau de bord</a>
  </nav>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert <?= $flashOk ? 'admin-alert--ok' : 'admin-alert--error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <header class="admin-detail-hero admin-detail-hero--structured">
    <div class="admin-detail-hero__primary">
      <?php if ($detailLogoUrl !== null): ?>
        <img class="admin-detail-hero__avatar admin-detail-hero__avatar--logo" src="<?= htmlspecialchars($detailLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo <?= htmlspecialchars($detailCompanyName, ENT_QUOTES, 'UTF-8') ?>" width="64" height="64" loading="lazy" decoding="async" />
      <?php else: ?>
        <span class="admin-detail-hero__avatar" aria-hidden="true" style="--detail-avatar-from: <?= htmlspecialchars($detailPalette[0], ENT_QUOTES, 'UTF-8') ?>; --detail-avatar-to: <?= htmlspecialchars($detailPalette[1], ENT_QUOTES, 'UTF-8') ?>;"><?= htmlspecialchars($detailInitials, ENT_QUOTES, 'UTF-8') ?></span>
      <?php endif; ?>
      <div class="admin-detail-hero__text">
        <div class="admin-detail-hero__meta-row">
          <span class="admin-detail-hero__eyebrow">Fiche client</span>
          <span class="admin-detail-hero__id">#<?= (int) $id ?></span>
          <span class="admin-detail-hero__badge-inline"><?= admin_status_badge_detail((string) $row['status']) ?></span>
        </div>
        <h1 class="admin-detail-hero__title"><?= htmlspecialchars($detailCompanyName, ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="admin-detail-hero__tagline"><?= $ptypeDisplay !== '' ? htmlspecialchars($ptypeDisplay, ENT_QUOTES, 'UTF-8') : 'Type de projet non renseigné — à compléter dans la fiche.' ?></p>
        <?php if (!empty($row['website_url'])): ?>
          <p class="admin-detail-hero__site">
            <a href="<?= htmlspecialchars($row['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="admin-detail-hero__site-link">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M2 12h20" /><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" /></svg>
              <?= htmlspecialchars(parse_url((string) $row['website_url'], PHP_URL_HOST) ?: $row['website_url'], ENT_QUOTES, 'UTF-8') ?>
              <span aria-hidden="true"> ↗</span>
            </a>
          </p>
        <?php endif; ?>
      </div>
    </div>
    <div class="admin-detail-hero__actions-card" role="group" aria-label="Actions rapides">
      <a class="btn-primary admin-detail-hero__btn-pri admin-detail-hero__btn-project" href="<?= htmlspecialchars(url('admin/project_tasks.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M9 11l3 3L22 4" />
          <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
        </svg>
        Gestion de projet
        <?php if ($projectPending > 0): ?>
          <span class="admin-detail-hero__btn-count" aria-label="<?= $projectPending ?> tâche(s) à faire"><?= $projectPending ?></span>
        <?php endif; ?>
      </a>
      <a class="admin-btn admin-btn--ghost admin-detail-hero__btn-ghost" href="<?= htmlspecialchars(url('admin/clients_edit.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9" /><path d="M16.5 3.5a2.121 2.121 0 1 1 3 3L7 19l-4 1 1-4z" /></svg>
        Modifier la fiche
      </a>
      <?php if (!empty($row['website_url'])): ?>
        <a class="admin-btn admin-btn--ghost admin-detail-hero__btn-ghost" href="<?= htmlspecialchars($row['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6" /><polyline points="15 3 21 3 21 9" /><line x1="10" y1="14" x2="21" y2="3" /></svg>
          Ouvrir le site
        </a>
      <?php endif; ?>
    </div>
  </header>

  <div class="admin-detail-layout">
    <section class="admin-panel admin-detail-panel admin-detail-panel--overview" aria-labelledby="overview-heading">
      <header class="admin-detail-section-head">
        <div>
          <h2 id="overview-heading" class="admin-detail-section-head__title">Vue d’ensemble</h2>
          <p class="admin-detail-section-head__hint">Indicateurs clés, suivi projet et coordonnées du référent.</p>
        </div>
      </header>

      <div class="admin-detail-kpi-grid" role="list">
        <article class="admin-detail-kpi" role="listitem">
          <div class="admin-detail-kpi__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" /></svg>
          </div>
          <span class="admin-detail-kpi__label">Dernière mise à jour</span>
          <span class="admin-detail-kpi__value"><?= format_fr_datetime((string) ($row['updated_at'] ?? '')) ?></span>
          <span class="admin-detail-kpi__meta">Synchronisé depuis la fiche entreprise</span>
        </article>

        <article class="admin-detail-kpi admin-detail-kpi--accent" role="listitem">
          <div class="admin-detail-kpi__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4l-6 6a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l6-6a4 4 0 0 0 5.4-5.4l-2 2-2-2-2-2 2-2z" /></svg>
          </div>
          <span class="admin-detail-kpi__label">Maintenance <?= $calendarYear ?></span>
          <?php if ($quotaYear > 0): ?>
            <span class="admin-detail-kpi__value admin-detail-kpi__value--xl"><?= $maintRemainingYear ?></span>
            <span class="admin-detail-kpi__subline">restante<?= $maintRemainingYear !== 1 ? 's' : '' ?> sur <?= $quotaYear ?> · <?= $maintDoneThisYear ?> effectuée<?= $maintDoneThisYear !== 1 ? 's' : '' ?></span>
            <span class="admin-detail-kpi__meta">Consommation du quota annuel</span>
            <div class="admin-detail-kpi__bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?= $quotaYear ?>" aria-valuenow="<?= $maintDoneThisYear ?>" aria-label="<?= $maintDoneThisYear ?> sur <?= $quotaYear ?> maintenances utilisées">
              <span class="admin-detail-kpi__bar-fill" style="width: <?= $maintProgressPct ?>%"></span>
            </div>
          <?php else: ?>
            <span class="admin-detail-kpi__value"><?= $maintDoneThisYear ?> effectuée<?= $maintDoneThisYear !== 1 ? 's' : '' ?></span>
            <span class="admin-detail-kpi__meta">Quota annuel non défini — renseignez « Maintenances par an » dans la fiche.</span>
          <?php endif; ?>
        </article>

        <article class="admin-detail-kpi admin-detail-kpi--project" role="listitem">
          <div class="admin-detail-kpi__icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4" /><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" /></svg>
          </div>
          <span class="admin-detail-kpi__label">Tâches projet</span>
          <?php if ($projectTotal > 0): ?>
            <span class="admin-detail-kpi__value admin-detail-kpi__value--xl"><?= $projectDone ?></span>
            <span class="admin-detail-kpi__subline">terminée<?= $projectDone !== 1 ? 's' : '' ?> sur <?= $projectTotal ?> · <?= $projectPending ?> en cours</span>
            <span class="admin-detail-kpi__meta">Avancement du carnet de tâches</span>
            <div class="admin-detail-kpi__bar admin-detail-kpi__bar--blue" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $projectProgressPct ?>">
              <span class="admin-detail-kpi__bar-fill" style="width: <?= $projectProgressPct ?>%"></span>
            </div>
          <?php else: ?>
            <span class="admin-detail-kpi__value">—</span>
            <span class="admin-detail-kpi__meta">Aucune tâche — créez un planning depuis la gestion de projet.</span>
          <?php endif; ?>
          <a class="admin-detail-kpi__link" href="<?= htmlspecialchars(url('admin/project_tasks.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Ouvrir les tâches</a>
        </article>
      </div>

      <div class="admin-detail-pricing" aria-labelledby="pricing-heading">
        <h3 id="pricing-heading" class="admin-detail-pricing__title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23" /><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" /></svg>
          Tarifs convenus
          <span class="admin-detail-pricing__badge"><?= htmlspecialchars(billing_currency_label($billingCurrency), ENT_QUOTES, 'UTF-8') ?></span>
        </h3>
        <div class="admin-detail-pricing__grid" role="list">
          <div class="admin-detail-pricing__cell" role="listitem">
            <span class="admin-detail-pricing__label">Prix du projet</span>
            <span class="admin-detail-pricing__value"><?= $priceProject > 0 ? format_money_display($priceProject, $billingCurrency) : '—' ?></span>
            <span class="admin-detail-pricing__hint">Prestation livrée</span>
          </div>
          <div class="admin-detail-pricing__cell" role="listitem">
            <span class="admin-detail-pricing__label">Hébergement</span>
            <span class="admin-detail-pricing__value"><?= $priceHosting > 0 ? format_money_display($priceHosting, $billingCurrency) : '—' ?></span>
            <span class="admin-detail-pricing__hint">Par an</span>
          </div>
          <div class="admin-detail-pricing__cell" role="listitem">
            <span class="admin-detail-pricing__label">Maintenance annuelle</span>
            <span class="admin-detail-pricing__value"><?= $priceMaintAnnual > 0 ? format_money_display($priceMaintAnnual, $billingCurrency) : '—' ?></span>
            <span class="admin-detail-pricing__hint">Forfait / an</span>
          </div>
        </div>
        <p class="admin-detail-pricing__foot">Montants exprimés en <strong><?= htmlspecialchars(billing_currency_label($billingCurrency), ENT_QUOTES, 'UTF-8') ?></strong> (HT ou TTC selon votre usage interne). <a href="<?= htmlspecialchars(url('admin/clients_edit.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Modifier les tarifs ou la devise</a></p>
      </div>

      <div class="admin-detail-contact-shell">
        <h3 class="admin-detail-contact-shell__title">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></svg>
          Contact référent
        </h3>
        <dl class="admin-detail-dl">
          <div class="admin-detail-dl__row">
            <dt>Nom</dt>
            <dd>
              <?php
                $contactNm = trim((string) ($row['contact_name'] ?? ''));
                if ($contactNm !== '') {
                    echo htmlspecialchars($contactNm, ENT_QUOTES, 'UTF-8');
                } else {
                    echo '<span class="admin-detail-dl__empty">Non renseigné</span>';
                }
              ?>
            </dd>
          </div>
          <div class="admin-detail-dl__row">
            <dt>Email</dt>
            <dd>
              <?php if (!empty($row['email'])): ?>
                <a href="mailto:<?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?></a>
              <?php else: ?>
                <span class="admin-detail-dl__empty">Non renseigné</span>
              <?php endif; ?>
            </dd>
          </div>
          <div class="admin-detail-dl__row">
            <dt>Téléphone</dt>
            <dd>
              <?php if ($detailPhone !== ''): ?>
                <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $detailPhone), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($detailPhone, ENT_QUOTES, 'UTF-8') ?></a>
              <?php else: ?>
                <span class="admin-detail-dl__empty">Non renseigné</span>
              <?php endif; ?>
            </dd>
          </div>
        </dl>
      </div>

      <?php if (!empty($row['notes'])): ?>
        <div class="admin-detail-notesbox admin-detail-notesbox--card">
          <h3 class="admin-detail-notesbox__title">Notes internes</h3>
          <div class="admin-detail-notesbox__body"><?= nl2br(htmlspecialchars((string) $row['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
        </div>
      <?php endif; ?>
    </section>

    <div class="admin-detail-stack">
      <section class="admin-panel admin-detail-panel admin-detail-panel--maint-card" aria-labelledby="maint-form-heading">
        <header class="admin-detail-section-head admin-detail-section-head--compact">
          <div>
            <h2 id="maint-form-heading" class="admin-detail-section-head__title">Nouvelle maintenance</h2>
            <p class="admin-detail-section-head__hint">Enregistrez une intervention pour mettre à jour le solde annuel et générer un lien de validation si besoin.</p>
          </div>
          <div class="admin-detail-maint-quickstat" aria-label="Synthèse annuelle">
            <span class="admin-detail-maint-quickstat__num"><?= $maintDoneThisYear ?></span>
            <span class="admin-detail-maint-quickstat__label">effectuée<?= $maintDoneThisYear !== 1 ? 's' : '' ?> en <?= $calendarYear ?></span>
          </div>
        </header>
        <form method="post" class="admin-form admin-detail-maint-form admin-detail-maint-form--card">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_maintenance">
          <div class="admin-grid-2">
            <div class="admin-field">
              <label for="performed_at">Date de la maintenance *</label>
              <input type="date" id="performed_at" name="performed_at" required value="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="admin-field">
              <label for="summary">Résumé (optionnel)</label>
              <input type="text" id="summary" name="summary" maxlength="255" placeholder="Ex. Mise à jour sécurité, correctifs boutique…">
            </div>
          </div>
          <div class="admin-field">
            <label for="notes">Travaux effectués / détail</label>
            <textarea id="notes" name="notes" placeholder="Détail de l’intervention, pièces jointes à noter, prochaine échéance…"></textarea>
          </div>
          <button type="submit" class="btn-primary admin-submit admin-detail-maint-submit">Enregistrer la maintenance</button>
        </form>
      </section>

      <section class="admin-panel admin-detail-panel admin-detail-panel--history admin-detail-panel--history-card" aria-labelledby="history-heading">
        <header class="admin-detail-section-head admin-detail-section-head--compact">
          <div>
            <h2 id="history-heading" class="admin-detail-section-head__title">Historique des maintenances</h2>
            <p class="admin-detail-section-head__hint">Validation client et lien de signature pour chaque entrée.</p>
          </div>
          <span class="admin-detail-panel__count"><?= count($maintenances) ?> entrée<?= count($maintenances) !== 1 ? 's' : '' ?></span>
        </header>
        <?php if ($maintenances === []): ?>
          <div class="admin-detail-empty-card">
            <p>Aucune intervention enregistrée pour cette entreprise.</p>
          </div>
        <?php else: ?>
          <ul class="admin-maint-cards admin-maint-cards--structured">
        <?php foreach ($maintenances as $m): ?>
          <?php
            $tok = (string) ($m['public_token'] ?? '');
            $signUrl = $tok !== '' ? maintenance_sign_absolute_url($tok) : '';
            $validatedAt = $m['validated_at'] ?? null;
          ?>
          <li class="admin-maint-card">
            <div class="admin-maint-card__top">
              <div class="admin-maint-card__heading">
                <time class="admin-maint-card__date" datetime="<?= htmlspecialchars((string) $m['performed_at'], ENT_QUOTES, 'UTF-8') ?>"><?= format_fr_date((string) $m['performed_at']) ?></time>
                <?php if (!empty($validatedAt)): ?>
                  <span class="admin-maint-badge admin-maint-badge--signed">Signé le <?= htmlspecialchars(format_fr_datetime((string) $validatedAt), ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                  <span class="admin-maint-badge admin-maint-badge--pending">En attente de validation</span>
                <?php endif; ?>
              </div>
              <div class="admin-maint-card__tools">
                <?php if ($signUrl !== ''): ?>
                  <button type="button" class="admin-btn admin-maint-copy-link" data-copy-maint-url="<?= htmlspecialchars($signUrl, ENT_QUOTES, 'UTF-8') ?>">Copier le lien</button>
                <?php endif; ?>
                <form method="post" class="admin-inline-form admin-maint-card__del">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_maintenance">
                  <input type="hidden" name="maintenance_id" value="<?= (int) $m['id'] ?>">
                  <button type="submit" class="admin-btn admin-btn--danger admin-btn--compact">Supprimer</button>
                </form>
              </div>
            </div>
            <?php if (!empty($m['summary'])): ?>
              <p class="admin-maint-card__summary"><?= htmlspecialchars((string) $m['summary'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <?php if (!empty($m['notes'])): ?>
              <div class="admin-maint-card__notes"><?= nl2br(htmlspecialchars((string) $m['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
      </section>
    </div>
  </div>
</div>

<script>
(function () {
  document.querySelectorAll('.admin-maint-copy-link[data-copy-maint-url]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var u = btn.getAttribute('data-copy-maint-url');
      if (!u) return;
      function done() {
        var prev = btn.textContent;
        btn.textContent = 'Copié !';
        btn.disabled = true;
        setTimeout(function () { btn.textContent = prev; btn.disabled = false; }, 2200);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(u).then(done).catch(function () {
          window.prompt('Copiez ce lien :', u);
        });
      } else {
        window.prompt('Copiez ce lien :', u);
      }
    });
  });
})();
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
