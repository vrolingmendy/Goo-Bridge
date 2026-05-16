<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/money.php';
require_once __DIR__ . '/../includes/client_logo.php';
require_once __DIR__ . '/../includes/country_dial.php';

require_admin();

$pageTitle = 'Ajouter une entreprise — Goo-Bridge Admin';
$activeNav = 'clients';

$flash = isset($_GET['flash']) ? (string) $_GET['flash'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        header('Location: ' . url('admin/clients_new.php?flash=csrf'), true, 302);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');
    if ($action !== 'create') {
        header('Location: ' . url('admin/clients_new.php'), true, 302);
        exit;
    }

    try {
        $pdo = db();

        $company = trim((string) ($_POST['company_name'] ?? ''));
        $maintenancesPerYear = max(0, (int) ($_POST['maintenances_per_year'] ?? 0));
        $projectPrice = parse_money_amount($_POST['project_price'] ?? null);
        $hostingPrice = parse_money_amount($_POST['hosting_price'] ?? null);
        $maintenanceAnnualPrice = parse_money_amount($_POST['maintenance_annual_price'] ?? null);
        $billingCurrency = normalize_billing_currency((string) ($_POST['billing_currency'] ?? 'EUR'));
        if ($company === '') {
            header('Location: ' . url('admin/clients_new.php?flash=required'), true, 302);
            exit;
        }

        $ticketTok = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare(
            'INSERT INTO clients (company_name, contact_name, email, phone, website_url, project_type, maintenances_per_year,
             project_price, hosting_price, maintenance_annual_price, billing_currency, notes, status, ticket_portal_token)
             VALUES (:company, :contact, :email, :phone, :web, :ptype, :maintenances,
             :project_price, :hosting_price, :maintenance_annual_price, :billing_currency, :notes, :status, :ttok)'
        );
        $phoneCcIn = trim((string) ($_POST['phone_cc'] ?? ''));
        $phoneLocalIn = trim((string) ($_POST['phone'] ?? ''));
        $allowedCcIn = country_allowed_dials();
        $phoneFinal = null;
        if ($phoneLocalIn !== '') {
            if (in_array($phoneCcIn, $allowedCcIn, true)) {
                $localDigits = preg_replace('/\D+/', '', $phoneLocalIn);
                if ($localDigits !== '') {
                    if (strlen($localDigits) >= 2 && $localDigits[0] === '0') {
                        $localDigits = substr($localDigits, 1);
                    }
                    $phoneFinal = '+' . $phoneCcIn . ' ' . $localDigits;
                }
            } else {
                $phoneFinal = $phoneLocalIn;
            }
        }

        $stmt->execute([
            'company' => $company,
            'contact' => trim((string) ($_POST['contact_name'] ?? '')) ?: null,
            'email' => trim((string) ($_POST['email'] ?? '')) ?: null,
            'phone' => $phoneFinal,
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
            'ttok' => $ticketTok,
        ]);
        $newId = (int) $pdo->lastInsertId();
        $logoFile = $_FILES['company_logo'] ?? null;
        if (client_logo_has_upload($logoFile)) {
            $logoErr = client_logo_save_upload($pdo, $newId, $logoFile);
            header(
                'Location: ' . url(
                    'admin/clients.php?flash=' . ($logoErr !== null ? 'created_logo_skip' : 'created')
                ),
                true,
                302
            );
            exit;
        }
        header('Location: ' . url('admin/clients.php?flash=created'), true, 302);
        exit;
    } catch (Throwable $e) {
        header('Location: ' . url('admin/clients_new.php?flash=error'), true, 302);
        exit;
    }
}

$hrefClientsList = url('admin/clients.php');

$flashMsg = '';
if ($flash === 'csrf') {
    $flashMsg = 'Session invalide — rechargez la page.';
} elseif ($flash === 'required') {
    $flashMsg = 'Le nom de l\'entreprise est obligatoire.';
} elseif ($flash === 'error') {
    $flashMsg = 'Une erreur est survenue.';
}

require __DIR__ . '/inc/header.php';
?>

<div class="admin-clients-page admin-clients-new-page">
  <nav class="admin-detail-breadcrumb admin-detail-breadcrumb--bar admin-clients-new-page__nav" aria-label="Navigation">
    <a href="<?= htmlspecialchars($hrefClientsList, ENT_QUOTES, 'UTF-8') ?>">
      <span aria-hidden="true">←</span> Clients &amp; entreprises
    </a>
    <span class="admin-detail-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="admin-detail-breadcrumb__current" aria-current="page">Ajouter une entreprise</span>
  </nav>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert admin-clients-page__flash admin-alert--error"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <section class="admin-panel admin-create-panel admin-create-panel--page-hero" id="nouvelle-entreprise">
    <div class="admin-create-panel__hero">
      <div class="admin-create-panel__hero-intro">
        <span class="admin-create-panel__eyebrow">Nouvelle fiche</span>
        <h2 class="admin-create-panel__hero-title">Ajouter une entreprise</h2>
      </div>
      <div class="admin-create-panel__hero-visual">
        <div class="admin-create-panel__hero-orb admin-create-panel__hero-orb--one" aria-hidden="true"></div>
        <div class="admin-create-panel__hero-orb admin-create-panel__hero-orb--two" aria-hidden="true"></div>
        <div class="admin-create-panel__hero-card admin-create-panel__logo-card">
          <div class="admin-create-panel__logo-preview-shell">
            <div id="company_logo_placeholder" class="admin-create-panel__logo-placeholder">
              <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4" ry="4" /><circle cx="8.5" cy="8.5" r="1.5" /><polyline points="21 15 16 10 5 21" /></svg>
              <span class="admin-create-panel__logo-placeholder-title">Logo entreprise</span>
              <span class="admin-create-panel__logo-placeholder-meta">optionnel · JPG, PNG, WebP, GIF · max. 2&nbsp;Mo</span>
            </div>
          </div>
          <label for="company_logo" class="admin-create-panel__logo-upload-btn">Choisir une image</label>
          <input type="file" id="company_logo" name="company_logo" form="admin-create-client-form" accept="image/jpeg,image/png,image/webp,image/gif" class="admin-sr-only" />
        </div>
      </div>
    </div>

    <form method="post" id="admin-create-client-form" class="admin-form admin-create-form" autocomplete="off" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create">

      <fieldset class="admin-create-form__group">
        <legend class="admin-create-form__legend">
          <span class="admin-create-form__legend-num">01</span>
          Identité de l'entreprise
        </legend>
        <div class="admin-create-form__grid">
          <div class="admin-field admin-create-form__field admin-create-form__field--wide">
            <label for="company_name">Nom de l'entreprise <span class="admin-create-form__required" aria-hidden="true">*</span></label>
            <input type="text" id="company_name" name="company_name" required maxlength="255" placeholder="Ex. Sugar Paper">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="project_type">Type de projet</label>
            <input type="text" id="project_type" name="project_type" maxlength="128" placeholder="Site vitrine, e-commerce…">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="status">Statut suivi</label>
            <select id="status" name="status" class="admin-create-form__status">
              <option value="active">● Actif</option>
              <option value="paused">● En pause</option>
              <option value="completed">● Terminé</option>
            </select>
          </div>
        </div>
      </fieldset>

      <fieldset class="admin-create-form__group">
        <legend class="admin-create-form__legend">
          <span class="admin-create-form__legend-num">02</span>
          Contact référent
        </legend>
        <div class="admin-create-form__grid">
          <div class="admin-field admin-create-form__field">
            <label for="contact_name">Nom du contact</label>
            <input type="text" id="contact_name" name="contact_name" maxlength="255" placeholder="Prénom Nom">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" maxlength="255" placeholder="nom@entreprise.com">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="phone">Téléphone</label>
            <div class="admin-phone-grid">
              <?= render_country_dial_select('phone_cc', '221', [
                  'id' => 'phone_cc',
                  'class' => 'admin-phone-cc',
                  'autocomplete' => 'country',
                  'aria-label' => 'Indicatif téléphonique pays',
              ]) ?>
              <input type="tel" id="phone" name="phone" maxlength="40" inputmode="tel"
                autocomplete="tel-national" placeholder="77 123 45 67">
            </div>
          </div>
        </div>
      </fieldset>

      <fieldset class="admin-create-form__group">
        <legend class="admin-create-form__legend">
          <span class="admin-create-form__legend-num">03</span>
          Projet & suivi
        </legend>
        <div class="admin-create-form__grid">
          <div class="admin-field admin-create-form__field admin-create-form__field--wide">
            <label for="website_url">URL du site livré</label>
            <input type="url" id="website_url" name="website_url" maxlength="512" placeholder="https://exemple.com">
            <p class="admin-create-form__help">Laissez vide si le site n'est pas encore en ligne.</p>
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="billing_currency">Devise de facturation</label>
            <select id="billing_currency" name="billing_currency" class="admin-create-form__status">
              <option value="EUR" selected>Euro (EUR)</option>
              <option value="XOF">Franc CFA — UEMOA (XOF)</option>
            </select>
            <p class="admin-create-form__help">Les montants ci-dessous sont exprimés dans cette devise.</p>
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="maintenances_per_year">Maintenances / an</label>
            <input type="number" id="maintenances_per_year" name="maintenances_per_year" min="0" step="1" value="0">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="project_price">Prix du projet</label>
            <input type="text" id="project_price" name="project_price" inputmode="decimal" autocomplete="off" placeholder="ex. 4 500 ou 450000">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="hosting_price">Prix hébergement <span class="admin-create-form__suffix-hint">/ an</span></label>
            <input type="text" id="hosting_price" name="hosting_price" inputmode="decimal" autocomplete="off" placeholder="0">
          </div>
          <div class="admin-field admin-create-form__field">
            <label for="maintenance_annual_price">Maintenance annuelle <span class="admin-create-form__suffix-hint">/ an</span></label>
            <input type="text" id="maintenance_annual_price" name="maintenance_annual_price" inputmode="decimal" autocomplete="off" placeholder="0">
          </div>
          <div class="admin-field admin-create-form__field admin-create-form__field--full">
            <label for="notes">Notes internes</label>
            <textarea id="notes" name="notes" rows="3" placeholder="Échéances, facturation, évolutions prévues…"></textarea>
          </div>
        </div>
      </fieldset>

      <div class="admin-create-form__actions">
        <p class="admin-create-form__legal">Les champs marqués <span class="admin-create-form__required" aria-hidden="true">*</span> sont obligatoires.</p>
        <div class="admin-create-form__actions-buttons">
          <a class="btn-ghost admin-create-form__ghost-btn" href="<?= htmlspecialchars($hrefClientsList, ENT_QUOTES, 'UTF-8') ?>">Annuler</a>
          <button type="submit" class="btn-primary admin-create-form__submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
              <path d="M5 12l4 4L19 6" />
            </svg>
            Enregistrer l'entreprise
          </button>
        </div>
      </div>
    </form>
  </section>
</div>

<script>
(function () {
  var input = document.getElementById('company_logo');
  var ph = document.getElementById('company_logo_placeholder');
  if (!input || !ph) return;
  var titleEl = ph.querySelector('.admin-create-panel__logo-placeholder-title');
  var metaEl = ph.querySelector('.admin-create-panel__logo-placeholder-meta');
  var defaultTitle = titleEl ? titleEl.textContent.trim() : '';
  var defaultMeta = metaEl ? metaEl.textContent.trim() : '';
  input.addEventListener('change', function () {
    var f = input.files && input.files[0];
    if (!f) {
      if (titleEl) titleEl.textContent = defaultTitle;
      if (metaEl) metaEl.textContent = defaultMeta;
      return;
    }
    if (titleEl) titleEl.textContent = 'Logo sélectionné';
    var name = f.name || '';
    if (name.length > 32) name = name.slice(0, 30) + '…';
    if (metaEl) metaEl.textContent = name || defaultMeta;
  });
})();
</script>

<?php require __DIR__ . '/inc/footer.php'; ?>
