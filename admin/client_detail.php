<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

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

require __DIR__ . '/inc/header.php';
?>

<div class="admin-detail-page">
  <nav class="admin-detail-breadcrumb" aria-label="Navigation">
    <a href="<?= htmlspecialchars(url('admin/clients.php'), ENT_QUOTES, 'UTF-8') ?>">← Liste des clients</a>
    <span class="admin-detail-breadcrumb__sep" aria-hidden="true">·</span>
    <a href="<?= htmlspecialchars(url('admin/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">Tableau de bord</a>
  </nav>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert <?= $flashOk ? 'admin-alert--ok' : 'admin-alert--error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <header class="admin-detail-hero">
    <div class="admin-detail-hero__intro">
      <h1 class="admin-detail-hero__title"><?= htmlspecialchars($row['company_name'], ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="admin-detail-hero__tagline"><?= $ptypeDisplay !== '' ? htmlspecialchars($ptypeDisplay, ENT_QUOTES, 'UTF-8') : 'Type de projet non renseigné' ?></p>
    </div>
    <div class="admin-detail-hero__toolbar">
      <div class="admin-detail-hero__badge"><?= admin_status_badge_detail((string) $row['status']) ?></div>
      <div class="admin-detail-hero__actions">
        <a class="btn-primary admin-detail-hero__btn-pri" href="<?= htmlspecialchars(url('admin/clients_edit.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Modifier la fiche</a>
        <?php if (!empty($row['website_url'])): ?>
          <a class="admin-btn admin-btn--ghost admin-detail-hero__btn-ghost" href="<?= htmlspecialchars($row['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener">Ouvrir le site ↗</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <section class="admin-panel admin-detail-panel admin-detail-panel--info">
    <div class="admin-detail-panel__intro">
      <h2 class="admin-detail-panel__title">Informations</h2>
      <p class="admin-detail-panel__hint">Récap du dossier et contacts.</p>
    </div>
    <div class="admin-detail-tiles">
      <div class="admin-detail-tile">
        <span class="admin-detail-tile__label">Dernière mise à jour</span>
        <span class="admin-detail-tile__value"><?= format_fr_datetime((string) ($row['updated_at'] ?? '')) ?></span>
      </div>
      <div class="admin-detail-tile admin-detail-tile--accent">
        <span class="admin-detail-tile__label">Solde maintenance <?= $calendarYear ?></span>
        <?php if ($quotaYear > 0): ?>
          <span class="admin-detail-tile__value admin-detail-tile__value--lg"><?= $maintRemainingYear ?></span>
          <span class="admin-detail-tile__meta">restante<?= $maintRemainingYear !== 1 ? 's' : '' ?> sur <?= $quotaYear ?> prévues · <?= $maintDoneThisYear ?> déjà effectuée<?= $maintDoneThisYear !== 1 ? 's' : '' ?></span>
        <?php else: ?>
          <span class="admin-detail-tile__value"><?= $maintDoneThisYear ?> effectuée<?= $maintDoneThisYear !== 1 ? 's' : '' ?></span>
          <span class="admin-detail-tile__meta">Quota annuel non renseigné — définissez « Maintenances par an » dans la fiche.</span>
        <?php endif; ?>
      </div>
      <div class="admin-detail-tile">
        <span class="admin-detail-tile__label">Contact référent</span>
        <span class="admin-detail-tile__value"><?= htmlspecialchars((string) ($row['contact_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?: '—' ?></span>
      </div>
      <div class="admin-detail-tile">
        <span class="admin-detail-tile__label">Email</span>
        <span class="admin-detail-tile__value">
          <?php if (!empty($row['email'])): ?>
            <a href="mailto:<?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            —
          <?php endif; ?>
        </span>
      </div>
      <div class="admin-detail-tile">
        <span class="admin-detail-tile__label">Téléphone</span>
        <span class="admin-detail-tile__value">
          <?php if ($detailPhone !== ''): ?>
            <a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $detailPhone), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($detailPhone, ENT_QUOTES, 'UTF-8') ?></a>
          <?php else: ?>
            —
          <?php endif; ?>
        </span>
      </div>
    </div>
    <?php if (!empty($row['notes'])): ?>
      <div class="admin-detail-notesbox">
        <h3 class="admin-detail-notesbox__title">Notes internes</h3>
        <div class="admin-detail-notesbox__body"><?= nl2br(htmlspecialchars((string) $row['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
      </div>
    <?php endif; ?>
  </section>

  <section class="admin-panel admin-detail-panel admin-detail-panel--maint">
    <div class="admin-detail-panel__intro">
      <h2 class="admin-detail-panel__title">Enregistrer une maintenance</h2>
    </div>
    <form method="post" class="admin-form admin-detail-maint-form">
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

  <section class="admin-panel admin-detail-panel admin-detail-panel--history">
    <div class="admin-detail-panel__intro">
      <h2 class="admin-detail-panel__title">Historique des maintenances</h2>
      <span class="admin-detail-panel__count"><?= count($maintenances) ?> entrée<?= count($maintenances) !== 1 ? 's' : '' ?></span>
    </div>
    <?php if ($maintenances === []): ?>
      <p class="admin-detail-empty-state">Aucune intervention enregistrée pour cette entreprise.</p>
    <?php else: ?>
      <ul class="admin-maint-cards">
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
