<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/country_dial.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: text/html; charset=UTF-8');

$pageTitle = 'Connexion — Support Goo-Bridge';
$error = '';
$notRecognized = false;

$allowedCc = country_allowed_dials();
$phoneCc = in_array(trim((string) ($_POST['phone_cc'] ?? '221')), $allowedCc, true)
    ? trim((string) ($_POST['phone_cc'] ?? '221'))
    : '221';
$phoneLocal = trim((string) ($_POST['phone_local'] ?? ''));
$emailVal = trim((string) ($_POST['email'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Session expirée : rechargez la page et réessayez.';
    } elseif (!isset($_POST['accept_terms']) || (string) $_POST['accept_terms'] !== '1') {
        $error = 'Vous devez accepter la politique de confidentialité pour continuer.';
    } elseif ($emailVal === '' || !filter_var($emailVal, FILTER_VALIDATE_EMAIL)) {
        $error = 'Indiquez une adresse email valide.';
    } elseif ($phoneLocal === '' || strlen($phoneLocal) > 40
        || !preg_match('/^[\d\s().+-]{4,40}$/', $phoneLocal)) {
        $error = 'Indiquez votre numéro de téléphone (sans répéter l’indicatif).';
    } else {
        $digitsIn = preg_replace('/\D+/', '', $phoneLocal);
        if (strlen($digitsIn) < 5) {
            $error = 'Indiquez un numéro de téléphone valide.';
        } else {
            try {
                $pdo = db();
                $emailNorm = mb_strtolower($emailVal, 'UTF-8');
                $stmt = $pdo->prepare(
                    'SELECT id, ticket_portal_token, phone FROM clients
                     WHERE email IS NOT NULL AND TRIM(LOWER(email)) = :em'
                );
                $stmt->execute(['em' => $emailNorm]);
                $rows = $stmt->fetchAll();

                $token = null;
                foreach ($rows as $row) {
                    if (country_phone_matches($row['phone'] ?? null, $phoneCc, $phoneLocal)) {
                        $tok = trim((string) ($row['ticket_portal_token'] ?? ''));
                        if ($tok === '') {
                            $up = $pdo->prepare(
                                'UPDATE clients SET ticket_portal_token = :tok WHERE id = :id LIMIT 1'
                            );
                            $tok = bin2hex(random_bytes(32));
                            $up->execute(['tok' => $tok, 'id' => (int) $row['id']]);
                        }
                        $token = $tok;
                        break;
                    }
                }

                if ($token !== null) {
                    $_SESSION['support_client_token'] = $token;
                    $_SESSION['support_client_auth_at'] = time();
                    header(
                        'Location: ' . url('client_support.php?t=' . rawurlencode($token)),
                        true,
                        303
                    );
                    exit;
                }

                $error = 'Aucune entreprise ne correspond à ces identifiants. Vérifiez votre email et votre numéro de téléphone.';
                $notRecognized = true;
            } catch (Throwable $e) {
                $error = 'Une erreur technique est survenue. Réessayez dans quelques instants.';
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(url('favicon.svg'), ENT_QUOTES, 'UTF-8') ?>" />
  <link rel="stylesheet" href="<?= htmlspecialchars(url('style.css'), ENT_QUOTES, 'UTF-8') ?>">
  <style>
    :root {
      --lg-green: #16a34a;
      --lg-green-dark: #15803d;
      --lg-text: #0f172a;
      --lg-muted: #64748b;
      --lg-border: #e2e8f0;
      --lg-bg: #f6f8f7;
      --lg-bg-soft: #f1f5f9;
    }
    body.lg-page { margin: 0; background: var(--lg-bg); }
    .lg-shell {
      min-height: calc(100vh - var(--nav-h, 64px));
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 16px 60px;
      box-sizing: border-box;
      background:
        radial-gradient(ellipse 100% 70% at 20% 0%, rgba(22, 163, 74, 0.14) 0%, transparent 55%),
        radial-gradient(ellipse 80% 60% at 100% 100%, rgba(99, 102, 241, 0.10) 0%, transparent 55%),
        var(--lg-bg);
    }
    .lg-card {
      position: relative;
      width: 100%;
      max-width: 480px;
      background: #fff;
      border-radius: 22px;
      padding: clamp(28px, 5vw, 40px);
      border: 1px solid rgba(15, 23, 42, 0.06);
      box-shadow:
        0 1px 0 rgba(255,255,255,0.9) inset,
        0 30px 60px -20px rgba(15,23,42,0.18),
        0 12px 28px -10px rgba(15,23,42,0.08);
    }
    .lg-logo {
      width: 64px; height: 64px;
      margin: 0 auto 14px;
      border-radius: 20px;
      display: flex; align-items: center; justify-content: center;
      background: linear-gradient(135deg, rgba(22,163,74,0.18), rgba(99,102,241,0.18));
      border: 1px solid rgba(22,163,74,0.25);
    }
    .lg-h1 {
      text-align: center;
      margin: 0 0 10px;
      font-size: clamp(1.7rem, 4vw, 2rem);
      font-weight: 800;
      color: var(--lg-text);
      letter-spacing: -0.03em;
    }
    .lg-sub {
      text-align: center;
      margin: 0 auto 26px;
      max-width: 360px;
      color: var(--lg-muted);
      font-size: 0.97rem;
      line-height: 1.5;
    }
    .lg-alert {
      padding: 12px 14px; border-radius: 12px; margin-bottom: 18px;
      font-size: 0.9rem; line-height: 1.45;
      background: rgba(185, 28, 28, 0.07);
      border: 1px solid rgba(185, 28, 28, 0.22);
      color: #991b1b;
      display: flex; gap: 10px; align-items: flex-start;
    }
    .lg-alert svg { flex-shrink: 0; margin-top: 2px; }

    .lg-field { margin-bottom: 16px; }
    .lg-field > label,
    .lg-field-cell > label {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      margin-bottom: 8px;
      color: var(--lg-text);
      letter-spacing: -0.005em;
    }
    .lg-input {
      width: 100%;
      box-sizing: border-box;
      padding: 15px 16px;
      border-radius: 16px;
      border: 1px solid var(--lg-border);
      background: #fff;
      font-size: 0.95rem;
      font-family: inherit;
      color: var(--lg-text);
      transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .lg-input::placeholder { color: #94a3b8; }
    .lg-input:hover { border-color: #cbd5e1; }
    .lg-input:focus {
      outline: none;
      border-color: var(--lg-green);
      box-shadow: 0 0 0 4px rgba(22, 163, 74, 0.16);
    }

    .lg-phone-grid {
      display: grid;
      grid-template-columns: minmax(118px, 34%) 1fr;
      gap: 12px;
    }
    .lg-phone-grid > .lg-field-cell { min-width: 0; }
    .lg-phone-cc {
      cursor: pointer;
      appearance: none;
      font-weight: 600;
      font-size: 0.92rem;
      padding: 15px 36px 15px 14px;
      background-color: #fff;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%2394a3b8' stroke-width='2.2' stroke-linecap='round'%3E%3Cpath d='M3.5 5.5L7 9l3.5-3.5'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 12px center;
      text-overflow: ellipsis;
      white-space: nowrap;
      overflow: hidden;
    }

    .lg-check {
      display: flex; align-items: flex-start; gap: 10px;
      margin: 18px 0 22px;
      font-size: 0.9rem;
      color: var(--lg-text);
      line-height: 1.45;
      cursor: pointer;
    }
    .lg-check input[type="checkbox"] {
      flex-shrink: 0;
      width: 18px; height: 18px;
      margin-top: 1px;
      accent-color: var(--lg-green);
      cursor: pointer;
    }
    .lg-check a { color: var(--lg-green); font-weight: 700; text-decoration: underline; text-underline-offset: 2px; }

    .lg-submit {
      width: 100%;
      padding: 16px 22px;
      border-radius: 16px;
      border: 0;
      background: var(--lg-green);
      color: #fff;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      letter-spacing: 0.01em;
      box-shadow: 0 10px 26px -8px rgba(22, 163, 74, 0.55);
      transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
    }
    .lg-submit:hover { background: var(--lg-green-dark); transform: translateY(-1px); }
    .lg-submit:active { transform: translateY(0); }

    .lg-foot {
      margin-top: 22px;
      padding-top: 18px;
      border-top: 1px solid var(--lg-border);
      text-align: center;
      font-size: 0.85rem;
      color: var(--lg-muted);
    }
    .lg-foot a { color: var(--lg-green); font-weight: 600; text-decoration: none; }
    .lg-foot a:hover { text-decoration: underline; }

    /* Encart "pas encore client ?" */
    .lg-contact {
      margin-top: 22px;
      padding: 22px;
      border-radius: 18px;
      background: linear-gradient(135deg, rgba(99,102,241,0.07) 0%, rgba(22,163,74,0.08) 100%);
      border: 1px solid rgba(99,102,241,0.20);
      display: flex;
      gap: 14px;
      align-items: flex-start;
    }
    .lg-contact--highlight {
      background: linear-gradient(135deg, rgba(245,158,11,0.10) 0%, rgba(220,38,38,0.06) 100%);
      border: 1px solid rgba(245,158,11,0.35);
      box-shadow: 0 0 0 4px rgba(245,158,11,0.10);
      animation: lg-pulse 2.4s ease-in-out infinite;
    }
    @keyframes lg-pulse {
      0%, 100% { box-shadow: 0 0 0 4px rgba(245,158,11,0.10); }
      50%      { box-shadow: 0 0 0 8px rgba(245,158,11,0.04); }
    }
    .lg-contact__icon {
      flex-shrink: 0;
      width: 38px; height: 38px;
      border-radius: 12px;
      display: inline-flex; align-items: center; justify-content: center;
      background: #fff;
      color: var(--lg-green);
      border: 1px solid rgba(15,23,42,0.06);
      box-shadow: 0 4px 12px -4px rgba(15,23,42,0.18);
    }
    .lg-contact--highlight .lg-contact__icon { color: #b45309; }
    .lg-contact__body { min-width: 0; flex: 1; }
    .lg-contact__title {
      font-size: 0.95rem; font-weight: 700;
      color: var(--lg-text); margin: 0 0 4px;
      letter-spacing: -0.005em;
    }
    .lg-contact__text {
      margin: 0 0 12px;
      font-size: 0.85rem; line-height: 1.5;
      color: var(--lg-muted);
    }
    .lg-contact__btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 11px 18px;
      border-radius: 12px;
      background: var(--lg-green);
      color: #fff;
      font-weight: 700; font-size: 0.88rem;
      text-decoration: none;
      box-shadow: 0 8px 22px -10px rgba(22,163,74,0.55);
      transition: background .2s, transform .15s;
    }
    .lg-contact__btn:hover { background: var(--lg-green-dark); transform: translateY(-1px); }
    .lg-contact--highlight .lg-contact__btn { background: #b45309; box-shadow: 0 8px 22px -10px rgba(180,83,9,0.55); }
    .lg-contact--highlight .lg-contact__btn:hover { background: #92400e; }
    @media (max-width: 420px) {
      .lg-contact { flex-direction: column; gap: 10px; }
    }

    body.lg-page main.lg-shell { padding-top: calc(40px + var(--nav-h, 64px)); }
  </style>
</head>
<body class="bridge-body lg-page">
  <?php require __DIR__ . '/partials/site_header.php'; ?>

  <main class="lg-shell">
    <section class="lg-card" aria-labelledby="lg-title">
      <div class="lg-logo" aria-hidden="true">
        <svg width="32" height="32" viewBox="0 0 28 28" fill="none">
          <path d="M14 2L3 8V20L14 26L25 20V8L14 2Z" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
          <path d="M3 8L14 14L25 8" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
          <path d="M14 14V26" stroke="#16a34a" stroke-width="2" />
        </svg>
      </div>

      <h1 class="lg-h1" id="lg-title">Connexion</h1>
      <p class="lg-sub">Accédez à votre espace pour suivre, déclarer et gérer vos tickets de support.</p>

      <?php if ($error !== ''): ?>
        <div class="lg-alert" role="alert">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= htmlspecialchars(url('support.php'), ENT_QUOTES, 'UTF-8') ?>" autocomplete="on" novalidate>
        <?= csrf_field() ?>

        <div class="lg-field">
          <label for="email">Email</label>
          <input type="email" id="email" name="email" required maxlength="255" inputmode="email"
            class="lg-input" autocomplete="email"
            value="<?= htmlspecialchars($emailVal, ENT_QUOTES, 'UTF-8') ?>"
            placeholder="vous@entreprise.com">
        </div>

        <div class="lg-field">
          <div class="lg-phone-grid">
            <div class="lg-field-cell">
              <label for="phone_cc">Indicatif</label>
              <?= render_country_dial_select('phone_cc', $phoneCc, [
                  'id' => 'phone_cc',
                  'class' => 'lg-input lg-phone-cc',
                  'autocomplete' => 'country',
                  'aria-label' => 'Indicatif téléphonique pays',
              ]) ?>
            </div>
            <div class="lg-field-cell">
              <label for="phone_local">Téléphone</label>
              <input type="tel" id="phone_local" name="phone_local" required maxlength="40" inputmode="tel"
                class="lg-input" autocomplete="tel-national"
                value="<?= htmlspecialchars($phoneLocal, ENT_QUOTES, 'UTF-8') ?>"
                placeholder="77 123 45 67">
            </div>
          </div>
        </div>

        <label class="lg-check">
          <input type="checkbox" name="accept_terms" value="1" required<?= isset($_POST['accept_terms']) ? ' checked' : '' ?>>
          <span>J’ai lu et j’accepte la <a href="<?= htmlspecialchars(url('index.php#contact'), ENT_QUOTES, 'UTF-8') ?>">politique de confidentialité</a>.</span>
        </label>

        <button type="submit" class="lg-submit">Connexion</button>

        <div class="lg-foot">
          <a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">← Retour au site</a>
        </div>
      </form>

      <?php
        // Encart vers le formulaire de contact — accentué si l'identification a échoué.
        $contactUrl = url('index.php#contact');
        $isHighlight = $notRecognized;
      ?>
      <aside class="lg-contact <?= $isHighlight ? 'lg-contact--highlight' : '' ?>" aria-live="polite">
        <div class="lg-contact__icon" aria-hidden="true">
          <?php if ($isHighlight): ?>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
              <circle cx="12" cy="12" r="10"/>
            </svg>
          <?php else: ?>
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
              <polyline points="22,6 12,13 2,6"/>
            </svg>
          <?php endif; ?>
        </div>
        <div class="lg-contact__body">
          <?php if ($isHighlight): ?>
            <p class="lg-contact__title">Pas encore client Goo-Bridge ?</p>
            <p class="lg-contact__text">
              Vos identifiants ne sont pas reconnus. Si vous n’avez pas encore travaillé avec nous,
              envoyez-nous votre demande via le formulaire de contact — nous reviendrons vers vous
              sous 24 à 72 h ouvrées.
            </p>
          <?php else: ?>
            <p class="lg-contact__title">Une question avant de vous connecter&nbsp;?</p>
            <p class="lg-contact__text">
              Si vous n’êtes pas encore client ou si vous voulez nous parler d’un nouveau projet,
              passez plutôt par notre formulaire de contact.
            </p>
          <?php endif; ?>
          <a href="<?= htmlspecialchars($contactUrl, ENT_QUOTES, 'UTF-8') ?>" class="lg-contact__btn">
            <?= $isHighlight ? 'Envoyer ma demande via le contact' : 'Nous contacter' ?>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="5" y1="12" x2="19" y2="12"/>
              <polyline points="12 5 19 12 12 19"/>
            </svg>
          </a>
        </div>
      </aside>
    </section>
  </main>
</body>
</html>
