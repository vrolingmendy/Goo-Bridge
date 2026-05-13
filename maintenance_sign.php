<?php

declare(strict_types=1);

/**
 * Page publique (sans connexion) : le client ouvre le lien pour valider / signer une intervention.
 */

require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=UTF-8');

function ms_format_datetime(?string $sqlTs): string
{
    if ($sqlTs === null || $sqlTs === '') {
        return '—';
    }
    $ts = strtotime($sqlTs);

    return $ts !== false ? date('d/m/Y à H:i', $ts) : htmlspecialchars($sqlTs, ENT_QUOTES, 'UTF-8');
}

function ms_format_date(?string $sqlDate): string
{
    if ($sqlDate === null || $sqlDate === '') {
        return '—';
    }
    $ts = strtotime($sqlDate);

    return $ts !== false ? date('d/m/Y', $ts) : htmlspecialchars($sqlDate, ENT_QUOTES, 'UTF-8');
}

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$token = $isPost
    ? trim((string) ($_POST['t'] ?? ''))
    : trim((string) ($_GET['t'] ?? ''));

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Lien invalide</title></head><body><p>Lien invalide ou expiré.</p></body></html>';
    exit;
}

$pdo = db();

if ($isPost) {
    $confirm = isset($_POST['confirm_validation']) && $_POST['confirm_validation'] === '1';

    if (!$confirm) {
        http_response_code(400);
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur</title></head><body><p>Requête invalide.</p></body></html>';
        exit;
    }

    $stmt = $pdo->prepare(
        'UPDATE client_maintenances SET validated_at = NOW()
         WHERE public_token = :tok AND validated_at IS NULL LIMIT 1'
    );
    $stmt->execute(['tok' => $token]);

    header('Location: ' . url('maintenance_sign.php?t=' . rawurlencode($token)), true, 303);
    exit;
}

$stmt = $pdo->prepare(
    'SELECT m.performed_at, m.summary, m.notes, m.validated_at, c.company_name
     FROM client_maintenances m
     INNER JOIN clients c ON c.id = m.client_id
     WHERE m.public_token = :tok
     LIMIT 1'
);
$stmt->execute(['tok' => $token]);
$row = $stmt->fetch();

if ($row === false) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Introuvable</title></head><body><p>Cette intervention est introuvable.</p></body></html>';
    exit;
}

$pageTitle = 'Validation intervention — Goo-Bridge';
$signed = !empty($row['validated_at']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(url('style.css'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    .ms-wrap { max-width: 560px; margin: 0 auto; padding: 48px 20px 80px; }
    .ms-card {
      background: #fff;
      border: 1px solid var(--border-strong);
      border-radius: var(--radius-lg);
      padding: 28px;
      box-shadow: 0 8px 40px rgba(13, 26, 13, 0.06);
    }
    .ms-card::before {
      content: '';
      display: block;
      height: 4px;
      margin: -28px -28px 22px;
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      background: linear-gradient(90deg, var(--green), #86efac);
    }
    .ms-brand { font-weight: 800; font-size: 1rem; margin-bottom: 8px; color: var(--text); }
    .ms-brand span { color: var(--green); }
    .ms-h1 { font-size: 1.35rem; margin: 0 0 12px; letter-spacing: -0.02em; }
    .ms-lead { font-size: 0.92rem; color: var(--text-muted); margin-bottom: 22px; line-height: 1.5; }
    .ms-block { margin-bottom: 16px; }
    .ms-label { font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--text-muted); margin-bottom: 6px; }
    .ms-value { font-size: 0.95rem; color: var(--text); line-height: 1.5; word-break: break-word; }
    .ms-signed {
      margin-top: 24px;
      padding: 18px;
      border-radius: var(--radius-md);
      background: rgba(22, 163, 74, 0.1);
      border: 1px solid var(--green-border);
      color: #15803d;
      font-weight: 700;
      font-size: 0.95rem;
    }
    .ms-signed time { font-weight: 800; }
    .ms-form { margin-top: 24px; }
    .ms-note { font-size: 0.82rem; color: var(--text-muted); margin-top: 14px; line-height: 1.45; }
    .ms-actions .btn-primary { width: 100%; justify-content: center; margin-top: 8px; }
  </style>
</head>
<body style="background: var(--bg-surface);">
  <div class="ms-wrap">
    <div class="ms-card">
      <p class="ms-brand">Goo<span>-Bridge</span></p>
      <h1 class="ms-h1">Intervention de maintenance</h1>
      <p class="ms-lead">
        Bonjour — cette page concerne <strong><?= htmlspecialchars((string) $row['company_name'], ENT_QUOTES, 'UTF-8') ?></strong>.
        Vérifiez le récapitulatif ci-dessous<?= $signed ? '' : ', puis validez si tout correspond bien à l’intervention réalisée' ?>.
      </p>

      <div class="ms-block">
        <div class="ms-label">Date de l’intervention</div>
        <div class="ms-value"><?= ms_format_date((string) $row['performed_at']) ?></div>
      </div>

      <?php if (!empty($row['summary'])): ?>
        <div class="ms-block">
          <div class="ms-label">Résumé</div>
          <div class="ms-value"><?= htmlspecialchars((string) $row['summary'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
      <?php endif; ?>

      <?php if (!empty($row['notes'])): ?>
        <div class="ms-block">
          <div class="ms-label">Détail des travaux effectués</div>
          <div class="ms-value"><?= nl2br(htmlspecialchars((string) $row['notes'], ENT_QUOTES, 'UTF-8')) ?></div>
        </div>
      <?php endif; ?>

      <?php if ($signed): ?>
        <div class="ms-signed" role="status">
          ✓ Intervention <strong>validée et signée électroniquement</strong><br>
          <span style="font-weight:600;font-size:0.88rem;">Le <time datetime="<?= htmlspecialchars((string) $row['validated_at'], ENT_QUOTES, 'UTF-8') ?>"><?= ms_format_datetime((string) $row['validated_at']) ?></time></span>
        </div>
        <p class="ms-note">Vous pouvez conserver cette page comme accusé de réception.</p>
      <?php else: ?>
        <form method="post" class="ms-form ms-actions" action="<?= htmlspecialchars(url('maintenance_sign.php'), ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="t" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="confirm_validation" value="1">
          <button type="submit" class="btn-primary">Valider et signer électroniquement</button>
        </form>
        <p class="ms-note">En validant, vous confirmez avoir pris connaissance du contenu ci-dessus. Aucun compte ni mot de passe n’est nécessaire.</p>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
