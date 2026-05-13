<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';

require_admin();

$pageTitle = 'Clients & entreprises — Goo-Bridge Admin';
$activeNav = 'clients';

$flash = isset($_GET['flash']) ? (string) $_GET['flash'] : '';
$flashOk = [
    'created' => 'Entreprise ajoutée.',
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

        if ($action === 'create') {
            $company = trim((string) ($_POST['company_name'] ?? ''));
            $maintenancesPerYear = max(0, (int) ($_POST['maintenances_per_year'] ?? 0));
            if ($company === '') {
                header('Location: ' . url('admin/clients.php?flash=required'), true, 302);
                exit;
            }

            $stmt = $pdo->prepare(
                'INSERT INTO clients (company_name, contact_name, email, phone, website_url, project_type, maintenances_per_year, notes, status)
                 VALUES (:company, :contact, :email, :phone, :web, :ptype, :maintenances, :notes, :status)'
            );
            $stmt->execute([
                'company' => $company,
                'contact' => trim((string) ($_POST['contact_name'] ?? '')) ?: null,
                'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
                'phone' => trim((string) ($_POST['phone'] ?? '')) ?: null,
                'web' => trim((string) ($_POST['website_url'] ?? '')) ?: null,
                'ptype' => trim((string) ($_POST['project_type'] ?? '')) ?: null,
                'maintenances' => $maintenancesPerYear,
                'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                'status' => in_array($_POST['status'] ?? '', ['active', 'paused', 'completed'], true)
                    ? $_POST['status']
                    : 'active',
            ]);
            header('Location: ' . url('admin/clients.php?flash=created'), true, 302);
            exit;
        }

        if ($action === 'delete') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
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

$flashMsg = '';
if ($flash === 'csrf') {
    $flashMsg = 'Session invalide — rechargez la page.';
} elseif ($flash === 'required') {
    $flashMsg = 'Le nom de l\'entreprise est obligatoire.';
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

require __DIR__ . '/inc/header.php';
?>

<h1>Clients &amp; entreprises</h1>
<p class="admin-lead">
  Répertoire des sociétés pour lesquelles vous avez créé un site ou une plateforme — pour un suivi clair de vos livrables.
</p>

<?php if ($flashMsg !== ''): ?>
  <p class="admin-alert <?= isset($flashOk[$flash]) ? 'admin-alert--ok' : 'admin-alert--error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<div class="admin-panel">
  <h2>Ajouter une entreprise</h2>
  <form method="post" class="admin-form admin-form--enterprise-single-row">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="admin-enterprise-single-row">
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--company">
        <label for="company_name">Nom de l'entreprise *</label>
        <input type="text" id="company_name" name="company_name" required maxlength="255">
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--contact">
        <label for="contact_name">Contact référent</label>
        <input type="text" id="contact_name" name="contact_name" maxlength="255">
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--email">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="255">
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--phone">
        <label for="phone">Téléphone</label>
        <input type="text" id="phone" name="phone" maxlength="64">
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--url">
        <label for="website_url">URL du site livré</label>
        <input type="url" id="website_url" name="website_url" maxlength="512" placeholder="https://">
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--project">
        <label for="project_type">Type de projet</label>
        <input type="text" id="project_type" name="project_type" maxlength="128" placeholder="Site vitrine, e-commerce…">
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--maint">
        <label for="maintenances_per_year">Maintenances par an</label>
        <input type="number" id="maintenances_per_year" name="maintenances_per_year" min="0" step="1" value="0">
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--status">
        <label for="status">Statut suivi</label>
        <select id="status" name="status">
          <option value="active">Actif</option>
          <option value="paused">En pause</option>
          <option value="completed">Terminé</option>
        </select>
      </div>
      <div class="admin-field admin-enterprise-single-row__field admin-enterprise-single-row__field--notes">
        <label for="notes">Notes internes</label>
        <textarea id="notes" name="notes" rows="2" placeholder="Échéances, facturation, évolutions prévues…"></textarea>
      </div>
      <div class="admin-enterprise-single-row__submit">
        <button type="submit" class="btn-primary admin-submit admin-enterprise-single-row__btn">Enregistrer l'entreprise</button>
      </div>
    </div>
  </form>
</div>

<div class="admin-panel admin-panel--client-directory">
  <div class="admin-panel-head admin-panel-head--directory">
    <div class="admin-panel-head__text">
      <h2>Toutes les entreprises <span class="admin-heading-count"><?= count($clients) ?></span></h2>
      <p class="admin-panel-head__hint">Solde annuel : les interventions enregistrées sur la fiche sont déduites du quota « par an ».</p>
    </div>
  </div>
  <?php if ($clients === []): ?>
    <p class="admin-lead admin-client-directory-empty">Aucune entrée — utilisez le formulaire ci-dessus.</p>
  <?php else: ?>
    <div class="admin-client-grid">
      <?php foreach ($clients as $c): ?>
        <?php
          $ptype = trim((string) ($c['project_type'] ?? ''));
          $web = trim((string) ($c['website_url'] ?? ''));
          $quota = (int) ($c['maintenances_per_year'] ?? 0);
          $doneYear = (int) ($c['maintenances_done_year'] ?? 0);
          $remaining = $quota > 0 ? max(0, $quota - $doneYear) : 0;
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
        ?>
        <article class="admin-client-card">
          <header class="admin-client-card__header">
            <div class="admin-client-card__identity">
              <h3 class="admin-client-card__title"><?= htmlspecialchars($c['company_name'], ENT_QUOTES, 'UTF-8') ?></h3>
              <p class="admin-client-card__subtitle"><?= $ptype !== '' ? htmlspecialchars($ptype, ENT_QUOTES, 'UTF-8') : 'Type non renseigné' ?></p>
            </div>
            <div class="admin-client-card__status"><?= admin_status_badge_row((string) $c['status']) ?></div>
          </header>

          <div class="admin-client-card__body">
            <section class="admin-client-card__field" aria-label="Site livré">
              <span class="admin-client-card__label">Site livré</span>
              <div class="admin-client-card__value">
                <?php if ($web !== ''): ?>
                  <a class="admin-client-card__site-btn" href="<?= htmlspecialchars($web, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" title="<?= htmlspecialchars($web, ENT_QUOTES, 'UTF-8') ?>">
                    <span class="admin-client-card__site-btn-main"><?= htmlspecialchars($siteShort, ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="admin-client-card__site-btn-action">Ouvrir <span aria-hidden="true">↗</span></span>
                  </a>
                <?php else: ?>
                  <span class="admin-client-card__empty">Aucune URL</span>
                <?php endif; ?>
              </div>
            </section>

            <section class="admin-client-card__field admin-client-card__field--maintstat" aria-label="Maintenance annuelle">
              <span class="admin-client-card__label">Solde maintenance <?= $year ?></span>
              <div class="admin-client-card__value admin-client-card__maint-stack">
                <?php if ($quota > 0): ?>
                  <div class="admin-client-card__maint-main">
                    <span class="admin-client-card__maint-remain" title="Restant sur le quota annuel"><?= $remaining ?></span>
                    <span class="admin-client-card__maint-remain-label">restante<?= $remaining !== 1 ? 's' : '' ?> sur <?= $quota ?></span>
                  </div>
                  <p class="admin-client-card__maint-detail"><?= $doneYear ?> effectuée<?= $doneYear !== 1 ? 's' : '' ?> cette année</p>
                <?php else: ?>
                  <p class="admin-client-card__maint-detail"><strong><?= $doneYear ?></strong> effectuée<?= $doneYear !== 1 ? 's' : '' ?> en <?= $year ?></p>
                  <p class="admin-client-card__maint-detail admin-client-card__maint-detail--muted">Quota / an non défini</p>
                <?php endif; ?>
              </div>
            </section>
          </div>

          <footer class="admin-client-card__footer">
            <div class="admin-client-card__actions-row">
              <a class="btn-primary admin-client-card__btn-primary" href="<?= htmlspecialchars(url('admin/client_detail.php?id=' . (int) $c['id']), ENT_QUOTES, 'UTF-8') ?>">Voir plus</a>
              <a class="admin-btn admin-btn--ghost admin-client-card__btn-secondary" href="<?= htmlspecialchars(url('admin/clients_edit.php?id=' . (int) $c['id']), ENT_QUOTES, 'UTF-8') ?>">Modifier</a>
              <form method="post" class="admin-inline-form admin-client-card__delete-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                <button type="submit" class="admin-btn admin-btn--danger admin-btn--compact admin-client-card__btn-danger" title="Supprimer cette entreprise">Supprimer</button>
              </form>
            </div>
          </footer>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
