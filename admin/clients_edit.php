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
            $logoFile = $_FILES['company_logo'] ?? null;
            if (client_logo_upload_errored($logoFile)) {
                $error = 'Erreur lors de l\'envoi du logo.';
            } else {
                $maintenancesPerYear = max(0, (int) ($_POST['maintenances_per_year'] ?? 0));
                $projectPrice = parse_money_amount($_POST['project_price'] ?? null);
                $hostingPrice = parse_money_amount($_POST['hosting_price'] ?? null);
                $maintenanceAnnualPrice = parse_money_amount($_POST['maintenance_annual_price'] ?? null);
                $billingCurrency = normalize_billing_currency((string) ($_POST['billing_currency'] ?? 'EUR'));
                try {
                    $stmt = $pdo->prepare(
                        'UPDATE clients SET company_name=:company, contact_name=:contact, email=:email, phone=:phone,
                         website_url=:web, project_type=:ptype, maintenances_per_year=:maintenances,
                         project_price=:project_price, hosting_price=:hosting_price, maintenance_annual_price=:maintenance_annual_price,
                         billing_currency=:billing_currency,
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
                        'project_price' => $projectPrice,
                        'hosting_price' => $hostingPrice,
                        'maintenance_annual_price' => $maintenanceAnnualPrice,
                        'billing_currency' => $billingCurrency,
                        'notes' => trim((string) ($_POST['notes'] ?? '')) ?: null,
                        'status' => in_array($_POST['status'] ?? '', ['active', 'paused', 'completed'], true)
                            ? $_POST['status']
                            : 'active',
                        'id' => $id,
                    ]);
                    if (client_logo_has_upload($logoFile)) {
                        $logoErr = client_logo_save_upload($pdo, $id, $logoFile);
                        if ($logoErr !== null) {
                            $error = $logoErr;
                        }
                    } elseif (!empty($_POST['remove_company_logo'])) {
                        client_logo_clear($pdo, $id);
                    }
                    if ($error === '') {
                        header('Location: ' . url('admin/clients.php?flash=updated'), true, 302);
                        exit;
                    }
                } catch (Throwable $e) {
                    $error = 'Impossible d\'enregistrer.';
                }
            }
        }
    }
}

require __DIR__ . '/inc/header.php';

$existingLogoUrl = client_logo_public_url(isset($row['logo_path']) ? (string) $row['logo_path'] : null);

$rowPriceProject = (float) ($row['project_price'] ?? 0);
$rowPriceHosting = (float) ($row['hosting_price'] ?? 0);
$rowPriceMaintAnnual = (float) ($row['maintenance_annual_price'] ?? 0);
$rowBillingCurrency = normalize_billing_currency((string) ($row['billing_currency'] ?? 'EUR'));
$isFormPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$moneyField = static function (string $key, float $dbFallback) use ($isFormPost, $rowBillingCurrency): string {
    if ($isFormPost) {
        return htmlspecialchars((string) ($_POST[$key] ?? ''), ENT_QUOTES, 'UTF-8');
    }

    return htmlspecialchars(format_money_for_input($dbFallback, $rowBillingCurrency), ENT_QUOTES, 'UTF-8');
};
$currencySelectVal = $isFormPost
    ? normalize_billing_currency((string) ($_POST['billing_currency'] ?? $rowBillingCurrency))
    : $rowBillingCurrency;

$statusVal = (string) ($_POST['status'] ?? $row['status'] ?? 'active');
if (!in_array($statusVal, ['active', 'paused', 'completed'], true)) {
    $statusVal = 'active';
}

$hrefClients = url('admin/clients.php');
$hrefDetail = url('admin/client_detail.php?id=' . $id);
$companyEsc = htmlspecialchars((string) $row['company_name'], ENT_QUOTES, 'UTF-8');
?>

<div class="admin-edit-client-page">
  <nav class="admin-detail-breadcrumb admin-detail-breadcrumb--bar admin-edit-client-page__nav" aria-label="Navigation">
    <a href="<?= htmlspecialchars($hrefClients, ENT_QUOTES, 'UTF-8') ?>">
      <span aria-hidden="true">←</span> Clients
    </a>
    <span class="admin-detail-breadcrumb__sep" aria-hidden="true">/</span>
    <a href="<?= htmlspecialchars($hrefDetail, ENT_QUOTES, 'UTF-8') ?>">Fiche <?= $companyEsc ?></a>
    <span class="admin-detail-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="admin-detail-breadcrumb__current" aria-current="page">Modifier</span>
  </nav>

  <?php if ($error !== ''): ?>
    <p class="admin-alert admin-alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <section class="admin-panel admin-create-panel admin-create-panel--edit">
    <header class="admin-create-panel__head admin-create-panel__head--edit">
      <div class="admin-create-panel__head-text">
        <span class="admin-create-panel__eyebrow">Édition guidée</span>
        <h2><?= $companyEsc ?></h2>
        <p class="admin-create-panel__hint">Ajustez les informations du dossier : identité, contact, tarification et suivi. Les modifications sont enregistrées pour toute l’équipe admin.</p>
      </div>
      <div class="admin-create-panel__head-aside">
        <span class="admin-create-panel__pill admin-create-panel__pill--id" title="Identifiant interne">#<?= (int) $id ?></span>
        <span class="admin-create-panel__accent-dot" aria-hidden="true"></span>
      </div>
    </header>

    <form method="post" class="admin-form admin-create-form admin-edit-client-form" autocomplete="off" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <fieldset class="admin-create-form__group admin-create-form__group--edit">
        <legend class="admin-create-form__legend">
          <span class="admin-create-form__legend-num">01</span>
          Identité &amp; statut
        </legend>
        <div class="admin-create-form__grid">
          <div class="admin-field admin-create-form__field admin-create-form__field--wide">
            <label for="company_name">Nom de l’entreprise <span class="admin-create-form__required" aria-hidden="true">*</span></label>
            <input type="text" id="company_name" name="company_name" required maxlength="255" placeholder="Raison sociale"
              value="<?= htmlspecialchars($_POST['company_name'] ?? $row['company_name'], ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="project_type">Type de projet</label>
            <input type="text" id="project_type" name="project_type" maxlength="128" placeholder="E-commerce, ERP…"
              value="<?= htmlspecialchars($_POST['project_type'] ?? (string) ($row['project_type'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="status">Statut suivi</label>
            <select id="status" name="status" class="admin-create-form__status">
              <option value="active" <?= $statusVal === 'active' ? 'selected' : '' ?>>● Actif</option>
              <option value="paused" <?= $statusVal === 'paused' ? 'selected' : '' ?>>● En pause</option>
              <option value="completed" <?= $statusVal === 'completed' ? 'selected' : '' ?>>● Terminé</option>
            </select>
            <p class="admin-create-form__help">Reflet du cycle de vie du contrat ou du projet.</p>
          </div>
        </div>
        <div class="admin-create-form__logo-edit">
          <?php if ($existingLogoUrl !== null): ?>
            <div class="admin-create-form__logo-current">
              <img src="<?= htmlspecialchars($existingLogoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" width="96" height="96" loading="lazy" decoding="async" />
              <label class="admin-create-form__logo-remove">
                <input type="checkbox" name="remove_company_logo" value="1" <?= !empty($_POST['remove_company_logo']) ? 'checked' : '' ?> />
                Supprimer le logo
              </label>
            </div>
          <?php endif; ?>
          <div class="admin-field admin-create-form__field admin-create-form__field--full">
            <label for="company_logo"><?= $existingLogoUrl !== null ? 'Remplacer le logo' : 'Logo (optionnel)' ?></label>
            <input type="file" id="company_logo" name="company_logo" accept="image/jpeg,image/png,image/webp,image/gif" />
            <p class="admin-create-form__help">JPG, PNG, WebP ou GIF — max. 2&nbsp;Mo. Affiché sur la fiche et dans l’annuaire.</p>
          </div>
        </div>
      </fieldset>

      <fieldset class="admin-create-form__group admin-create-form__group--edit">
        <legend class="admin-create-form__legend">
          <span class="admin-create-form__legend-num">02</span>
          Contact référent
        </legend>
        <div class="admin-create-form__grid">
          <div class="admin-field admin-create-form__field">
            <label for="contact_name">Nom du contact</label>
            <input type="text" id="contact_name" name="contact_name" maxlength="255" placeholder="Prénom Nom"
              value="<?= htmlspecialchars($_POST['contact_name'] ?? (string) ($row['contact_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="255" placeholder="contact@entreprise.com"
              value="<?= htmlspecialchars($_POST['email'] ?? (string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="phone">Téléphone</label>
            <input type="text" id="phone" name="phone" maxlength="64" placeholder="+33 …"
              value="<?= htmlspecialchars($_POST['phone'] ?? (string) ($row['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>
      </fieldset>

      <fieldset class="admin-create-form__group admin-create-form__group--edit admin-create-form__group--pricing">
        <legend class="admin-create-form__legend">
          <span class="admin-create-form__legend-num">03</span>
          Projet, tarifs &amp; maintenance
        </legend>
        <div class="admin-create-form__grid">
          <div class="admin-field admin-create-form__field admin-create-form__field--wide">
            <label for="website_url">URL du site livré</label>
            <input type="url" id="website_url" name="website_url" maxlength="512" placeholder="https://…"
              value="<?= htmlspecialchars($_POST['website_url'] ?? (string) ($row['website_url'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <p class="admin-create-form__help">Lien public ou pré-production selon votre organisation.</p>
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="billing_currency">Devise de facturation</label>
            <select id="billing_currency" name="billing_currency" class="admin-create-form__status">
              <option value="EUR" <?= $currencySelectVal === 'EUR' ? 'selected' : '' ?>>Euro (EUR)</option>
              <option value="XOF" <?= $currencySelectVal === 'XOF' ? 'selected' : '' ?>>Franc CFA — UEMOA (XOF)</option>
            </select>
            <p class="admin-create-form__help">Les montants ci-dessous sont dans cette devise.</p>
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="maintenances_per_year">Maintenances / an</label>
            <input type="number" id="maintenances_per_year" name="maintenances_per_year" min="0" step="1"
              value="<?= (int) ($_POST['maintenances_per_year'] ?? ($row['maintenances_per_year'] ?? 0)) ?>">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="project_price">Prix du projet</label>
            <input type="text" id="project_price" name="project_price" inputmode="decimal" autocomplete="off" placeholder="0"
              value="<?= $moneyField('project_price', $rowPriceProject) ?>">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="hosting_price">Prix hébergement <span class="admin-create-form__suffix-hint">/ an</span></label>
            <input type="text" id="hosting_price" name="hosting_price" inputmode="decimal" autocomplete="off" placeholder="0"
              value="<?= $moneyField('hosting_price', $rowPriceHosting) ?>">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="maintenance_annual_price">Maintenance annuelle <span class="admin-create-form__suffix-hint">/ an</span></label>
            <input type="text" id="maintenance_annual_price" name="maintenance_annual_price" inputmode="decimal" autocomplete="off" placeholder="0"
              value="<?= $moneyField('maintenance_annual_price', $rowPriceMaintAnnual) ?>">
          </div>
        </div>
      </fieldset>

      <fieldset class="admin-create-form__group admin-create-form__group--edit">
        <legend class="admin-create-form__legend">
          <span class="admin-create-form__legend-num">04</span>
          Notes internes
        </legend>
        <div class="admin-create-form__grid">
          <div class="admin-field admin-create-form__field admin-create-form__field--full">
            <label for="notes">Mémo équipe</label>
            <textarea id="notes" name="notes" rows="4" placeholder="Facturation, historique commercial, points de vigilance…"><?= htmlspecialchars($_POST['notes'] ?? (string) ($row['notes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
        </div>
      </fieldset>

      <div class="admin-create-form__actions admin-create-form__actions--edit">
        <div class="admin-edit-client-form__footer-text">
          <p class="admin-create-form__legal">Le nom de l’entreprise est obligatoire. Les autres champs restent optionnels.</p>
          <p class="admin-edit-client-form__shortcut"><a href="<?= htmlspecialchars($hrefDetail, ENT_QUOTES, 'UTF-8') ?>">← Annuler et retourner à la fiche</a></p>
        </div>
        <button type="submit" class="btn-primary admin-create-form__submit admin-create-form__submit--edit">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
            <polyline points="17 21 17 13 7 13 7 21" />
            <polyline points="7 3 7 8 15 8" />
          </svg>
          Enregistrer les modifications
        </button>
      </div>
    </form>
  </section>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
