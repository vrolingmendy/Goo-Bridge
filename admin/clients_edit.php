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
$stmt = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$row = $stmt->fetch();
if ($row === false) {
    header('Location: ' . url('admin/clients.php'), true, 302);
    exit;
}

$pageTitle = 'Modifier — ' . $row['company_name'];
$activeNav = 'clients';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Session invalide — rechargez la page.';
    } else {
        $company = trim((string) ($_POST['company_name'] ?? ''));
        if ($company === '') {
            $error = 'Le nom de l\'entreprise est obligatoire.';
        } else {
            $maintenancesPerYear = max(0, (int) ($_POST['maintenances_per_year'] ?? 0));
            try {
                $stmt = $pdo->prepare(
                    'UPDATE clients SET company_name=:company, contact_name=:contact, email=:email, phone=:phone,
                     website_url=:web, project_type=:ptype, maintenances_per_year=:maintenances,
                     notes=:notes, status=:status WHERE id=:id LIMIT 1'
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
                    'id' => $id,
                ]);
                header('Location: ' . url('admin/clients.php?flash=updated'), true, 302);
                exit;
            } catch (Throwable $e) {
                $error = 'Impossible d\'enregistrer.';
            }
        }
    }
}

require __DIR__ . '/inc/header.php';
?>

<h1>Modifier la fiche</h1>
<p class="admin-lead"><a href="<?= htmlspecialchars(url('admin/clients.php'), ENT_QUOTES, 'UTF-8') ?>">← Retour à la liste</a></p>

<?php if ($error !== ''): ?>
  <p class="admin-alert admin-alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<div class="admin-panel">
  <form method="post" class="admin-form">
    <?= csrf_field() ?>
    <div class="admin-grid-2">
      <div class="admin-field">
        <label for="company_name">Nom de l'entreprise *</label>
        <input type="text" id="company_name" name="company_name" required maxlength="255"
          value="<?= htmlspecialchars($_POST['company_name'] ?? $row['company_name'], ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="contact_name">Contact référent</label>
        <input type="text" id="contact_name" name="contact_name" maxlength="255"
          value="<?= htmlspecialchars($_POST['contact_name'] ?? (string) ($row['contact_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" maxlength="255"
          value="<?= htmlspecialchars($_POST['email'] ?? (string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="phone">Téléphone</label>
        <input type="text" id="phone" name="phone" maxlength="64"
          value="<?= htmlspecialchars($_POST['phone'] ?? (string) ($row['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="website_url">URL du site livré</label>
        <input type="url" id="website_url" name="website_url" maxlength="512"
          value="<?= htmlspecialchars($_POST['website_url'] ?? (string) ($row['website_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="project_type">Type de projet</label>
        <input type="text" id="project_type" name="project_type" maxlength="128"
          value="<?= htmlspecialchars($_POST['project_type'] ?? (string) ($row['project_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
      </div>
      <div class="admin-field">
        <label for="maintenances_per_year">Maintenances par an</label>
        <input type="number" id="maintenances_per_year" name="maintenances_per_year" min="0" step="1"
          value="<?= (int) ($_POST['maintenances_per_year'] ?? ($row['maintenances_per_year'] ?? 0)) ?>">
      </div>
    </div>
    <?php
      $st = $_POST['status'] ?? $row['status'];
?>
    <div class="admin-field">
      <label for="status">Statut suivi</label>
      <select id="status" name="status">
        <option value="active" <?= $st === 'active' ? 'selected' : '' ?>>Actif</option>
        <option value="paused" <?= $st === 'paused' ? 'selected' : '' ?>>En pause</option>
        <option value="completed" <?= $st === 'completed' ? 'selected' : '' ?>>Terminé</option>
      </select>
    </div>
    <div class="admin-field">
      <label for="notes">Notes internes</label>
      <textarea id="notes" name="notes"><?= htmlspecialchars($_POST['notes'] ?? (string) ($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
    </div>
    <button type="submit" class="btn-primary admin-submit" style="width:auto;">Enregistrer les modifications</button>
  </form>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
