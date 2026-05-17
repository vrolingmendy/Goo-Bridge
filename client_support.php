<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/support_ticket.php';
require_once __DIR__ . '/includes/support_ticket_mail.php';
require_once __DIR__ . '/includes/country_dial.php';

header('Content-Type: text/html; charset=UTF-8');

$validCategories = array_keys(support_ticket_categories());
$validPriorities = array_keys(support_ticket_priorities());

$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$token = $isPost
    ? trim((string) ($_POST['t'] ?? ''))
    : trim((string) ($_GET['t'] ?? ''));

if ($token === '' || strlen($token) !== 64 || !ctype_xdigit($token)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Lien invalide</title></head><body><p>Lien invalide ou incomplet.</p></body></html>';
    exit;
}

$pdo = db();

$stmt = $pdo->prepare(
    'SELECT id, company_name, contact_name, email, phone FROM clients WHERE ticket_portal_token = :tok LIMIT 1'
);
$stmt->execute(['tok' => $token]);
$client = $stmt->fetch();

if ($client === false) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Introuvable</title></head><body><p>Ce lien ne correspond à aucune entreprise.</p></body></html>';
    exit;
}

$companyLabel = trim((string) ($client['company_name'] ?? ''));
$pageTitle = 'Mes tickets — ' . ($companyLabel !== '' ? $companyLabel : 'Goo-Bridge');

$error = '';
$sent = !$isPost && isset($_GET['sent']) && $_GET['sent'] === '1';

$uploadDir = __DIR__ . '/uploads/support_tickets';
$uploadUrlBase = url('uploads/support_tickets');

$allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$allowedExt = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
$maxImageBytes = 5 * 1024 * 1024; // 5 Mo

if ($isPost) {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        $error = 'Session expirée : rechargez la page et réessayez.';
    } else {
        $category = (string) ($_POST['category'] ?? '');
        if (!in_array($category, $validCategories, true)) {
            $category = 'other';
        }
        $priority = (string) ($_POST['priority'] ?? 'normal');
        if (!in_array($priority, $validPriorities, true)) {
            $priority = 'normal';
        }
        $subject = trim((string) ($_POST['subject'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $requesterName = trim((string) ($_POST['requester_name'] ?? ''));
        $requesterEmail = trim((string) ($_POST['requester_email'] ?? ''));

        $reqPhoneCc = trim((string) ($_POST['requester_phone_cc'] ?? ''));
        $reqPhoneLocal = trim((string) ($_POST['requester_phone_local'] ?? ''));
        $allowedDials = country_allowed_dials();
        $requesterPhone = null;
        if ($reqPhoneLocal !== '') {
            if (!preg_match('/^[\d\s().+-]{4,40}$/', $reqPhoneLocal)) {
                $error = 'Numéro de téléphone à contacter invalide.';
            } else {
                $digits = preg_replace('/\D+/', '', $reqPhoneLocal);
                if ($digits !== '' && strlen($digits) >= 2 && $digits[0] === '0') {
                    $digits = substr($digits, 1);
                }
                $cc = in_array($reqPhoneCc, $allowedDials, true) ? $reqPhoneCc : '221';
                $requesterPhone = '+' . $cc . ' ' . $digits;
            }
        }

        if ($error === '') {
            if ($subject === '') {
                $error = 'Indiquez un sujet pour votre demande.';
            } elseif (mb_strlen($subject) > 255) {
                $error = 'Le sujet est trop long (255 caractères maximum).';
            } elseif ($message === '') {
                $error = 'Décrivez votre demande dans le message.';
            } elseif (mb_strlen($message) > 12000) {
                $error = 'Le message est trop long.';
            } elseif ($requesterEmail !== '' && !filter_var($requesterEmail, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse email invalide.';
            } elseif ($requesterName !== '' && mb_strlen($requesterName) > 255) {
                $error = 'Le nom indiqué est trop long.';
            }
        }

        // Vérification des fichiers (max 3 images)
        $uploadedTmp = [];
        if ($error === '' && isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
            $files = $_FILES['attachments'];
            $count = count($files['name']);
            if ($count > 3) {
                $error = 'Vous pouvez joindre au maximum 3 images.';
            }
            for ($i = 0; $i < $count && $error === ''; $i++) {
                $errCode = (int) $files['error'][$i];
                if ($errCode === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if ($errCode !== UPLOAD_ERR_OK) {
                    $error = 'Erreur lors du téléchargement d’une image (code ' . $errCode . ').';
                    break;
                }
                $tmpName = (string) $files['tmp_name'][$i];
                $origName = (string) $files['name'][$i];
                $size = (int) $files['size'][$i];
                if ($size <= 0 || $size > $maxImageBytes) {
                    $error = 'Chaque image doit faire moins de 5 Mo.';
                    break;
                }
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
                $mime = $finfo ? (string) finfo_file($finfo, $tmpName) : (string) ($files['type'][$i] ?? '');
                if ($finfo) {
                    finfo_close($finfo);
                }
                if (!in_array($mime, $allowedMime, true)) {
                    $error = 'Seules les images JPG, PNG, WebP ou GIF sont acceptées.';
                    break;
                }
                $ext = strtolower((string) pathinfo($origName, PATHINFO_EXTENSION));
                if (!isset($allowedExt[$ext])) {
                    $ext = match ($mime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        'image/gif' => 'gif',
                        default => 'bin',
                    };
                } else {
                    $ext = $allowedExt[$ext];
                }
                $uploadedTmp[] = ['tmp' => $tmpName, 'ext' => $ext];
            }
        }

        if ($error === '') {
            try {
                $cid = (int) $client['id'];
                $ins = $pdo->prepare(
                    'INSERT INTO client_support_tickets
                        (client_id, category, priority, subject, message,
                         requester_name, requester_email, requester_phone, attachments_json)
                     VALUES (:cid, :cat, :pri, :subj, :msg, :rname, :remail, :rphone, :att)'
                );
                $ins->execute([
                    'cid' => $cid,
                    'cat' => $category,
                    'pri' => $priority,
                    'subj' => $subject,
                    'msg' => $message,
                    'rname' => $requesterName !== '' ? $requesterName : null,
                    'remail' => $requesterEmail !== '' ? $requesterEmail : null,
                    'rphone' => $requesterPhone,
                    'att' => null,
                ]);
                $newTicketId = (int) $pdo->lastInsertId();

                $savedPaths = [];
                if ($uploadedTmp !== []) {
                    // Création du dossier racine d'uploads si nécessaire
                    if (!is_dir($uploadDir)) {
                        if (!@mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
                            error_log('[client_support] Impossible de créer le dossier upload racine : ' . $uploadDir);
                            $uploadedTmp = [];
                        } else {
                            @chmod($uploadDir, 0777);
                        }
                    }
                    // Dossier dédié au ticket
                    $ticketDir = $uploadDir . '/' . $newTicketId;
                    if ($uploadedTmp !== [] && !is_dir($ticketDir)) {
                        if (!@mkdir($ticketDir, 0777, true) && !is_dir($ticketDir)) {
                            error_log('[client_support] Impossible de créer le dossier du ticket : ' . $ticketDir);
                            $uploadedTmp = [];
                        } else {
                            @chmod($ticketDir, 0777);
                        }
                    }

                    foreach ($uploadedTmp as $idx => $tmpInfo) {
                        $rand = bin2hex(random_bytes(6));
                        $filename = ($idx + 1) . '-' . $rand . '.' . $tmpInfo['ext'];
                        $dest = $ticketDir . '/' . $filename;
                        if (@move_uploaded_file($tmpInfo['tmp'], $dest)) {
                            @chmod($dest, 0644);
                            $savedPaths[] = 'uploads/support_tickets/' . $newTicketId . '/' . $filename;
                        } else {
                            error_log('[client_support] move_uploaded_file a échoué pour le ticket ' . $newTicketId);
                        }
                    }
                    if ($savedPaths !== []) {
                        $up = $pdo->prepare('UPDATE client_support_tickets SET attachments_json = :j WHERE id = :id LIMIT 1');
                        $up->execute([
                            'j' => json_encode($savedPaths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            'id' => $newTicketId,
                        ]);
                    }
                }

                // Demande de type "update" => considérée automatiquement comme une maintenance
                // (sera comptabilisée dans le quota annuel ou facturable selon le client).
                if ($category === 'update') {
                    try {
                        $maintSummary = 'Demande client (ticket #' . $newTicketId . ') : '
                            . (mb_strlen($subject) > 180 ? mb_substr($subject, 0, 180) . '…' : $subject);
                        $maintNotes = $message;
                        if (mb_strlen($maintNotes) > 4000) {
                            $maintNotes = mb_substr($maintNotes, 0, 4000) . '…';
                        }
                        $insMaint = $pdo->prepare(
                            'INSERT INTO client_maintenances
                                (client_id, ticket_id, performed_at, summary, notes, public_token)
                             VALUES (:cid, :tid, CURDATE(), :sum, :notes, :tok)'
                        );
                        $insMaint->execute([
                            'cid' => $cid,
                            'tid' => $newTicketId,
                            'sum' => $maintSummary,
                            'notes' => $maintNotes,
                            'tok' => bin2hex(random_bytes(32)),
                        ]);
                    } catch (Throwable $e) {
                        // En cas d'échec (colonne ticket_id manquante p.ex.), on n'interrompt pas le flow.
                    }
                }

                $ticketRow = [
                    'id' => $newTicketId,
                    'client_id' => $cid,
                    'category' => $category,
                    'priority' => $priority,
                    'subject' => $subject,
                    'message' => $message,
                    'requester_name' => $requesterName !== '' ? $requesterName : null,
                    'requester_email' => $requesterEmail !== '' ? $requesterEmail : null,
                    'requester_phone' => $requesterPhone,
                ];
                send_support_ticket_admin_mail($client, $ticketRow);

                header('Location: ' . url('client_support.php?t=' . rawurlencode($token) . '&sent=1'), true, 303);
                exit;
            } catch (Throwable $e) {
                $error = 'Impossible d’enregistrer votre demande pour le moment. Réessayez plus tard.';
            }
        }
    }
}

$defaultContactName = trim((string) ($client['contact_name'] ?? ''));
$defaultContactEmail = trim((string) ($client['email'] ?? ''));
$splitPhone = country_split_phone((string) ($client['phone'] ?? ''));
$defaultPhoneCc = $splitPhone['dial'] ?? '221';
$defaultPhoneLocal = $splitPhone['local'] ?? '';

$tickets = [];
try {
    $listStmt = $pdo->prepare(
        'SELECT id, category, priority, subject, message, status, created_at, closed_at,
                requester_name, requester_email, requester_phone, attachments_json
         FROM client_support_tickets WHERE client_id = :cid
         ORDER BY created_at DESC LIMIT 200'
    );
    $listStmt->execute(['cid' => (int) $client['id']]);
    $tickets = $listStmt->fetchAll();
} catch (Throwable $e) {
    $tickets = [];
}

$ticketsCount = count($tickets);
$openCount = 0;
$closedCount = 0;
$criticalCount = 0;
foreach ($tickets as $t) {
    if (($t['status'] ?? 'open') === 'closed') {
        $closedCount++;
    } else {
        $openCount++;
    }
    if (($t['priority'] ?? '') === 'critical' && ($t['status'] ?? 'open') !== 'closed') {
        $criticalCount++;
    }
}

function cs_format_dt(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '—';
    }
    try {
        $dt = new DateTime($iso);
        return $dt->format('d/m/Y à H:i');
    } catch (Throwable $e) {
        return (string) $iso;
    }
}

$catMeta = support_ticket_categories_meta();
$priMeta = support_ticket_priorities_meta();
$postedCategory = (string) ($_POST['category'] ?? 'correction');
if (!isset($catMeta[$postedCategory])) {
    $postedCategory = 'correction';
}
$postedPriority = (string) ($_POST['priority'] ?? 'normal');
if (!isset($priMeta[$postedPriority])) {
    $postedPriority = 'normal';
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
      --ct-text: #0f172a;
      --ct-muted: #64748b;
      --ct-border: #e2e8f0;
      --ct-border-soft: #eef2f6;
      --ct-bg: #f6f8f7;
      --ct-bg-soft: #f1f5f9;
      --ct-green: #16a34a;
      --ct-green-dark: #15803d;
    }
    body.ct-body { margin: 0; background: var(--ct-bg); }
    .ct-shell {
      max-width: 1100px;
      margin: 0 auto;
      padding: clamp(60px, 8vw, 96px) clamp(16px, 4vw, 32px) 80px;
    }

    /* ===== BOARD HEAD (inspiré de admin-board) ===== */
    .ct-board {
      position: relative;
      padding: 28px 30px;
      border-radius: 24px;
      background:
        radial-gradient(circle at 4% 8%, rgba(22,163,74,.14), transparent 42%),
        linear-gradient(180deg, #ffffff 0%, #f7fbf8 100%);
      border: 1px solid rgba(22,163,74,.16);
      box-shadow: 0 18px 48px rgba(15,23,42,.06), 0 1px 0 rgba(255,255,255,.92) inset;
      overflow: hidden;
      margin-bottom: 26px;
    }
    .ct-board::before {
      content: '';
      position: absolute; inset: 0 0 auto;
      height: 4px;
      background: linear-gradient(90deg, #0ea5e9, var(--ct-green) 60%, #86efac);
    }
    .ct-board__head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 24px;
      padding-bottom: 22px;
      margin-bottom: 22px;
      border-bottom: 1px solid rgba(13,26,13,.08);
      flex-wrap: wrap;
    }
    .ct-board__intro { min-width: 0; flex: 1 1 320px; }
    .ct-board__eyebrow {
      display: inline-flex; align-items: center;
      padding: 5px 12px; border-radius: 100px;
      font-size: 0.7rem; font-weight: 800;
      letter-spacing: 0.12em; text-transform: uppercase;
      color: #0c4a6e;
      background: rgba(14,165,233,.12);
      border: 1px solid rgba(14,165,233,.28);
    }
    .ct-board__title {
      margin: 8px 0 6px;
      font-size: clamp(1.4rem, 3vw, 1.7rem);
      letter-spacing: -0.02em;
      color: var(--ct-text);
      display: inline-flex;
      gap: 10px;
      align-items: center;
    }
    .ct-board__hint {
      margin: 0;
      color: var(--ct-muted);
      font-size: 0.92rem;
      line-height: 1.55;
      max-width: 60ch;
    }
    .ct-board__actions { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ct-cta {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--ct-green); color: #fff;
      font-weight: 700; font-size: 0.92rem;
      padding: 12px 20px; border-radius: 100px;
      border: 0; cursor: pointer; text-decoration: none;
      box-shadow: 0 10px 26px -10px rgba(22,163,74,.55);
      transition: background .2s, transform .15s;
    }
    .ct-cta:hover { background: var(--ct-green-dark); transform: translateY(-1px); }
    .ct-logout {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.85rem; font-weight: 600;
      color: var(--ct-muted);
      text-decoration: none;
      padding: 9px 14px;
      border-radius: 100px;
      border: 1px solid var(--ct-border);
      background: #fff;
      transition: .2s;
    }
    .ct-logout:hover { color: #b91c1c; border-color: rgba(185,28,28,.32); }

    .ct-stats {
      display: flex; gap: 10px; flex-wrap: wrap;
      margin: 0; padding: 0; list-style: none;
    }
    .ct-stat {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 9px 14px;
      border-radius: 14px;
      background: #fff;
      border: 1px solid var(--ct-border);
      box-shadow: 0 6px 14px rgba(15,23,42,.045);
      font-size: 0.84rem; color: var(--ct-muted);
    }
    .ct-stat__num {
      font-size: 1.05rem; font-weight: 800;
      color: var(--ct-text); letter-spacing: -0.02em;
    }
    .ct-stat__dot { width: 8px; height: 8px; border-radius: 999px; flex-shrink: 0; background: #94a3b8; }
    .ct-stat--open .ct-stat__dot { background: #2563eb; box-shadow: 0 0 10px rgba(37,99,235,.45); }
    .ct-stat--closed .ct-stat__dot { background: var(--ct-green); box-shadow: 0 0 10px rgba(22,163,74,.45); }
    .ct-stat--crit {
      background: linear-gradient(135deg, rgba(254,226,226,.85), rgba(254,242,242,.95));
      border-color: rgba(220,38,38,.32);
      color: #b91c1c;
      box-shadow: 0 6px 14px rgba(220,38,38,.14);
    }
    .ct-stat--crit .ct-stat__num { color: #b91c1c; }
    .ct-stat--crit .ct-stat__dot { background: #dc2626; box-shadow: 0 0 12px rgba(220,38,38,.55); }

    /* ===== SECTION TITLE ===== */
    .ct-section-title {
      display: flex; align-items: center; gap: 10px;
      margin: 32px 0 16px;
      font-size: 1.05rem; font-weight: 700; color: var(--ct-text);
      letter-spacing: -0.01em;
    }
    .ct-section-title__count {
      font-size: 0.74rem; font-weight: 700;
      padding: 3px 9px; border-radius: 100px;
      background: var(--ct-bg-soft); color: var(--ct-muted);
    }

    /* ===== TICKETS LIST ===== */
    .ct-tickets { display: grid; gap: 14px; }
    .ct-ticket {
      background: #fff;
      border: 1px solid var(--ct-border);
      border-radius: 16px;
      padding: 18px 20px;
      transition: .2s;
      position: relative;
    }
    .ct-ticket:hover { border-color: #cbd5e1; box-shadow: 0 6px 22px -10px rgba(15,23,42,.12); }
    .ct-ticket__top {
      display: flex; align-items: flex-start; justify-content: space-between;
      gap: 14px; margin-bottom: 8px; flex-wrap: wrap;
    }
    .ct-ticket__id-title { display: flex; align-items: baseline; gap: 8px; min-width: 0; }
    .ct-ticket__id {
      font-size: 0.78rem; font-weight: 700;
      color: var(--ct-muted); letter-spacing: 0.02em;
      background: var(--ct-bg-soft);
      padding: 3px 8px; border-radius: 8px;
    }
    .ct-ticket__title {
      margin: 0; font-size: 1rem; font-weight: 700;
      color: var(--ct-text); letter-spacing: -0.005em;
    }
    .ct-ticket__badges { display: flex; gap: 6px; flex-wrap: wrap; }
    .ct-pill {
      display: inline-flex; align-items: center; gap: 6px;
      font-size: 0.72rem; font-weight: 700;
      padding: 4px 10px; border-radius: 100px;
      text-transform: uppercase; letter-spacing: 0.06em;
    }
    .ct-pill--open { background: rgba(37,99,235,.1); color: #2563eb; border: 1px solid rgba(37,99,235,.2); }
    .ct-pill--closed { background: rgba(22,163,74,.1); color: #15803d; border: 1px solid rgba(22,163,74,.25); }
    .ct-pill--cat { background: var(--ct-bg-soft); color: #475569; border: 1px solid var(--ct-border); text-transform: none; letter-spacing: 0; }
    .ct-pill--pri { color: #fff; border: 1px solid rgba(0,0,0,.1); }

    .ct-ticket__meta {
      font-size: 0.82rem; color: var(--ct-muted);
      display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
      margin-top: 4px;
    }
    .ct-ticket__meta strong { color: var(--ct-text); font-weight: 600; }
    .ct-ticket__msg {
      font-size: 0.9rem; color: #475569; line-height: 1.55;
      margin: 10px 0 0;
      overflow: hidden;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    }
    .ct-ticket__thumbs { display: flex; gap: 8px; margin-top: 12px; flex-wrap: wrap; }
    .ct-ticket__thumb {
      width: 64px; height: 64px;
      border-radius: 10px;
      object-fit: cover;
      border: 1px solid var(--ct-border);
      cursor: zoom-in;
      transition: transform .15s;
    }
    .ct-ticket__thumb:hover { transform: scale(1.04); }

    .ct-empty {
      background: rgba(255,255,255,.7);
      border: 1px dashed rgba(22,163,74,.32);
      border-radius: 20px;
      padding: 48px 24px;
      text-align: center; color: var(--ct-muted);
    }
    .ct-empty__icon {
      width: 56px; height: 56px;
      margin: 0 auto 14px;
      border-radius: 16px;
      display: flex; align-items: center; justify-content: center;
      color: var(--ct-green);
      background: rgba(22,163,74,.1);
    }
    .ct-empty h3 { color: var(--ct-text); margin: 0 0 6px; font-size: 1.05rem; }
    .ct-empty p { margin: 0; font-size: 0.92rem; }

    /* ===== FORM CARD ===== */
    .ct-form-card {
      background: #fff;
      border-radius: 22px;
      border: 1px solid var(--ct-border);
      box-shadow: 0 18px 44px -22px rgba(15,23,42,.18);
      padding: clamp(22px, 3vw, 32px);
      position: relative;
      overflow: hidden;
    }
    .ct-form-card::before {
      content: '';
      position: absolute; inset: 0 0 auto;
      height: 4px;
      background: linear-gradient(90deg, var(--ct-green), #6366f1 55%, #8b5cf6);
    }
    .ct-form-head { display: flex; align-items: center; gap: 12px; margin: 6px 0 22px; flex-wrap: wrap; }
    .ct-form-head__icon {
      width: 40px; height: 40px;
      border-radius: 12px;
      background: rgba(22,163,74,.1); color: var(--ct-green);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .ct-form-head h2 { margin: 0; font-size: 1.15rem; color: var(--ct-text); letter-spacing: -0.01em; }
    .ct-form-head p { margin: 2px 0 0; color: var(--ct-muted); font-size: 0.88rem; }

    .ct-fieldset { border: 0; padding: 0; margin: 0 0 22px; }
    .ct-fieldset__legend {
      display: flex; align-items: center; gap: 10px;
      font-size: 0.78rem; font-weight: 800;
      text-transform: uppercase; letter-spacing: 0.1em;
      color: var(--ct-muted);
      margin-bottom: 12px;
    }
    .ct-fieldset__num {
      display: inline-flex; align-items: center; justify-content: center;
      width: 24px; height: 24px;
      border-radius: 100px;
      background: var(--ct-green); color: #fff;
      font-size: 0.7rem; font-weight: 800;
    }

    /* Cartes radio (type de demande) */
    .ct-card-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px;
    }
    .ct-card-radio {
      position: relative;
      display: flex; align-items: flex-start; gap: 12px;
      padding: 14px 16px;
      border-radius: 14px;
      border: 1.5px solid var(--ct-border);
      background: #fff;
      cursor: pointer;
      transition: .18s;
    }
    .ct-card-radio:hover { border-color: rgba(22,163,74,.35); background: #fafdfb; }
    .ct-card-radio input { position: absolute; opacity: 0; pointer-events: none; }
    .ct-card-radio__icon {
      width: 38px; height: 38px;
      border-radius: 11px;
      display: inline-flex; align-items: center; justify-content: center;
      color: var(--ct-muted);
      background: var(--ct-bg-soft);
      flex-shrink: 0;
      transition: .18s;
    }
    .ct-card-radio__body { min-width: 0; }
    .ct-card-radio__title {
      font-size: 0.92rem; font-weight: 700;
      color: var(--ct-text);
      margin-bottom: 2px;
    }
    .ct-card-radio__desc {
      font-size: 0.8rem; color: var(--ct-muted);
      line-height: 1.4;
    }
    .ct-card-radio__check {
      position: absolute;
      top: 12px; right: 12px;
      width: 18px; height: 18px;
      border-radius: 999px;
      border: 1.5px solid var(--ct-border);
      background: #fff;
    }
    .ct-card-radio input:checked + .ct-card-radio__icon {
      color: var(--ct-green);
      background: rgba(22,163,74,.12);
    }
    .ct-card-radio:has(input:checked) {
      border-color: var(--ct-green);
      background: rgba(22,163,74,.04);
      box-shadow: 0 0 0 3px rgba(22,163,74,.1);
    }
    .ct-card-radio:has(input:checked) .ct-card-radio__check {
      border-color: var(--ct-green);
      background: var(--ct-green);
      box-shadow: inset 0 0 0 3px #fff;
    }

    /* Boutons radio segmentés (priorité) */
    .ct-priority-grid {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }
    @media (max-width: 620px) { .ct-priority-grid { grid-template-columns: repeat(2, 1fr); } }
    .ct-priority {
      position: relative;
      display: flex; flex-direction: column; gap: 4px;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1.5px solid var(--ct-border);
      background: #fff;
      cursor: pointer;
      transition: .18s;
      --pri-color: #94a3b8;
    }
    .ct-priority input { position: absolute; opacity: 0; pointer-events: none; }
    .ct-priority__dot {
      width: 10px; height: 10px;
      border-radius: 999px;
      background: var(--pri-color);
      box-shadow: 0 0 8px color-mix(in srgb, var(--pri-color) 40%, transparent);
    }
    .ct-priority__label { font-size: 0.9rem; font-weight: 700; color: var(--ct-text); }
    .ct-priority__desc { font-size: 0.75rem; color: var(--ct-muted); line-height: 1.35; }
    .ct-priority:has(input:checked) {
      border-color: var(--pri-color);
      background: color-mix(in srgb, var(--pri-color) 6%, white);
      box-shadow: 0 0 0 3px color-mix(in srgb, var(--pri-color) 18%, transparent);
    }

    /* Champs */
    .ct-field { margin-bottom: 14px; }
    .ct-field > label {
      display: block;
      font-size: 0.82rem; font-weight: 700;
      color: var(--ct-text); margin-bottom: 7px;
    }
    .ct-field .ct-hint {
      font-size: 0.78rem; color: var(--ct-muted);
      margin: 6px 0 0;
    }
    .ct-input, .ct-select, .ct-textarea {
      width: 100%; box-sizing: border-box;
      padding: 12px 14px;
      border-radius: 13px;
      border: 1px solid var(--ct-border);
      background: #fff;
      font-size: 0.94rem;
      font-family: inherit;
      color: var(--ct-text);
      transition: border-color .2s, box-shadow .2s, background .2s;
    }
    .ct-input::placeholder, .ct-textarea::placeholder { color: #94a3b8; }
    .ct-input:focus, .ct-select:focus, .ct-textarea:focus {
      outline: none;
      border-color: var(--ct-green);
      box-shadow: 0 0 0 4px rgba(22,163,74,.16);
    }
    .ct-textarea { min-height: 140px; resize: vertical; }

    .ct-grid-2 {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }
    @media (max-width: 620px) { .ct-grid-2 { grid-template-columns: 1fr; } }

    /* Sélecteur indicatif + numéro */
    .ct-phone-row {
      display: grid;
      grid-template-columns: minmax(118px, 34%) 1fr;
      gap: 10px;
    }
    .ct-phone-row > * { min-width: 0; }
    @media (max-width: 480px) {
      .ct-phone-row { grid-template-columns: 1fr; }
    }
    .ct-phone-cc {
      cursor: pointer; appearance: none;
      font-weight: 600; font-size: 0.9rem;
      padding: 12px 34px 12px 12px;
      background-color: #fff;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' fill='none' stroke='%2394a3b8' stroke-width='2.2' stroke-linecap='round'%3E%3Cpath d='M3.5 5.5L7 9l3.5-3.5'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 10px center;
      text-overflow: ellipsis; white-space: nowrap; overflow: hidden;
    }

    /* Upload images (3 max) */
    .ct-uploads { display: flex; gap: 10px; flex-wrap: wrap; }
    .ct-upload-slot {
      position: relative;
      width: 120px; height: 120px;
      border-radius: 14px;
      border: 1.5px dashed var(--ct-border);
      background: var(--ct-bg-soft);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      gap: 6px;
      color: var(--ct-muted);
      cursor: pointer;
      overflow: hidden;
      transition: .18s;
    }
    .ct-upload-slot:hover { border-color: var(--ct-green); color: var(--ct-green); background: #fff; }
    .ct-upload-slot input[type="file"] {
      position: absolute; inset: 0;
      opacity: 0; cursor: pointer;
    }
    .ct-upload-slot__label { font-size: 0.78rem; font-weight: 600; text-align: center; padding: 0 6px; }
    .ct-upload-slot__preview {
      position: absolute; inset: 0;
      width: 100%; height: 100%; object-fit: cover;
      display: none;
      pointer-events: none;
    }
    .ct-upload-slot.has-file .ct-upload-slot__preview { display: block; }
    .ct-upload-slot.has-file .ct-upload-slot__placeholder { opacity: 0; }
    .ct-upload-slot__remove {
      position: absolute; top: 6px; right: 6px;
      width: 24px; height: 24px;
      border-radius: 999px;
      background: rgba(15,23,42,.7); color: #fff;
      border: 0; cursor: pointer;
      display: none;
      align-items: center; justify-content: center;
      font-size: 0.9rem; font-weight: 700;
      z-index: 2;
    }
    .ct-upload-slot.has-file .ct-upload-slot__remove { display: inline-flex; }

    .ct-submit {
      display: inline-flex; align-items: center; gap: 8px;
      background: var(--ct-green); color: #fff;
      font-weight: 700; font-size: 0.96rem;
      padding: 14px 28px;
      border-radius: 13px;
      border: 0; cursor: pointer;
      box-shadow: 0 10px 26px -10px rgba(22,163,74,.55);
      transition: .2s;
    }
    .ct-submit:hover { background: var(--ct-green-dark); transform: translateY(-1px); }
    .ct-form-foot {
      margin-top: 20px;
      display: flex; align-items: center; justify-content: space-between;
      gap: 14px; flex-wrap: wrap;
    }
    .ct-form-foot__hint { font-size: 0.8rem; color: var(--ct-muted); margin: 0; }

    /* Alertes */
    .ct-alert {
      padding: 14px 16px; border-radius: 14px; margin-bottom: 22px;
      font-size: 0.9rem; line-height: 1.5;
      display: flex; gap: 10px; align-items: flex-start;
    }
    .ct-alert--ok { background: rgba(22,163,74,.1); border: 1px solid rgba(22,163,74,.25); color: #15803d; }
    .ct-alert--err { background: rgba(185,28,28,.07); border: 1px solid rgba(185,28,28,.22); color: #991b1b; }

    /* Lightbox simple */
    .ct-lightbox {
      position: fixed; inset: 0;
      background: rgba(15,23,42,.85);
      display: none;
      align-items: center; justify-content: center;
      z-index: 1000; padding: 24px;
      cursor: zoom-out;
    }
    .ct-lightbox.is-open { display: flex; }
    .ct-lightbox img {
      max-width: 100%; max-height: 100%;
      border-radius: 14px;
      box-shadow: 0 30px 60px rgba(0,0,0,.5);
    }
  </style>
</head>
<body class="bridge-body ct-body">
  <?php require __DIR__ . '/partials/site_header.php'; ?>

  <main class="ct-shell">
    <!-- Hero / bandeau (style admin-board) -->
    <section class="ct-board">
      <div class="ct-board__head">
        <div class="ct-board__intro">
          <span class="ct-board__eyebrow">Espace support</span>
          <h1 class="ct-board__title">
            Bonjour <?= htmlspecialchars($companyLabel !== '' ? $companyLabel : 'cher client', ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <p class="ct-board__hint">
            Consultez vos tickets et créez-en de nouveaux. Notre équipe traite chaque demande
            selon son niveau d’urgence. Vous pouvez joindre jusqu’à 3 captures d’écran.
          </p>
        </div>
        <div class="ct-board__actions">
          <a href="#new-ticket" class="ct-cta">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
              <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Nouveau ticket
          </a>
          <a class="ct-logout" href="<?= htmlspecialchars(url('support.php'), ENT_QUOTES, 'UTF-8') ?>">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
            </svg>
            Se déconnecter
          </a>
        </div>
      </div>

      <ul class="ct-stats">
        <li class="ct-stat">
          <span class="ct-stat__dot"></span>
          <span class="ct-stat__num"><?= $ticketsCount ?></span>
          <span class="ct-stat__label">Total</span>
        </li>
        <li class="ct-stat ct-stat--open">
          <span class="ct-stat__dot"></span>
          <span class="ct-stat__num"><?= $openCount ?></span>
          <span class="ct-stat__label">Ouverts</span>
        </li>
        <li class="ct-stat ct-stat--closed">
          <span class="ct-stat__dot"></span>
          <span class="ct-stat__num"><?= $closedCount ?></span>
          <span class="ct-stat__label">Résolus</span>
        </li>
        <?php if ($criticalCount > 0): ?>
        <li class="ct-stat ct-stat--crit">
          <span class="ct-stat__dot"></span>
          <span class="ct-stat__num"><?= $criticalCount ?></span>
          <span class="ct-stat__label">Critique</span>
        </li>
        <?php endif; ?>
      </ul>
    </section>

    <?php if ($sent): ?>
      <div class="ct-alert ct-alert--ok" role="status">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
        </svg>
        <span>Votre ticket a bien été créé. Notre équipe vous répondra rapidement par email.</span>
      </div>
    <?php endif; ?>

    <h2 class="ct-section-title">
      Mes tickets
      <span class="ct-section-title__count"><?= $ticketsCount ?></span>
    </h2>

    <div class="ct-tickets">
      <?php if ($tickets === []): ?>
        <div class="ct-empty">
          <div class="ct-empty__icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
          </div>
          <h3>Aucun ticket pour le moment</h3>
          <p>Utilisez le formulaire ci-dessous pour créer votre première demande.</p>
        </div>
      <?php else: ?>
        <?php foreach ($tickets as $t):
          $tStatus = (string) ($t['status'] ?? 'open');
          $tCat = (string) ($t['category'] ?? 'other');
          $tPri = (string) ($t['priority'] ?? 'normal');
          $tSubj = (string) ($t['subject'] ?? '');
          $tMsg = (string) ($t['message'] ?? '');
          $tCreated = (string) ($t['created_at'] ?? '');
          $tClosed = (string) ($t['closed_at'] ?? '');
          $tPhone = (string) ($t['requester_phone'] ?? '');
          $tAtt = support_ticket_decode_attachments($t['attachments_json'] ?? null);
          $priColor = $priMeta[$tPri]['color'] ?? '#64748b';
          $priLab = $priMeta[$tPri]['label'] ?? ucfirst($tPri);
        ?>
          <article class="ct-ticket">
            <div class="ct-ticket__top">
              <div class="ct-ticket__id-title">
                <span class="ct-ticket__id">#<?= (int) $t['id'] ?></span>
                <h3 class="ct-ticket__title"><?= htmlspecialchars($tSubj, ENT_QUOTES, 'UTF-8') ?></h3>
              </div>
              <div class="ct-ticket__badges">
                <span class="ct-pill ct-pill--pri" style="background: <?= htmlspecialchars($priColor, ENT_QUOTES, 'UTF-8') ?>">
                  <?= htmlspecialchars($priLab, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <span class="ct-pill ct-pill--<?= $tStatus === 'closed' ? 'closed' : 'open' ?>">
                  <?= $tStatus === 'closed' ? 'Résolu' : 'En cours' ?>
                </span>
              </div>
            </div>
            <div class="ct-ticket__meta">
              <span class="ct-pill ct-pill--cat"><?= htmlspecialchars(support_ticket_category_label($tCat), ENT_QUOTES, 'UTF-8') ?></span>
              <span>Créé le <strong><?= htmlspecialchars(cs_format_dt($tCreated), ENT_QUOTES, 'UTF-8') ?></strong></span>
              <?php if ($tStatus === 'closed' && $tClosed !== ''): ?>
                <span>· Clôturé le <strong><?= htmlspecialchars(cs_format_dt($tClosed), ENT_QUOTES, 'UTF-8') ?></strong></span>
              <?php endif; ?>
              <?php if ($tPhone !== ''): ?>
                <span>· Contact : <strong><?= htmlspecialchars($tPhone, ENT_QUOTES, 'UTF-8') ?></strong></span>
              <?php endif; ?>
            </div>
            <p class="ct-ticket__msg"><?= nl2br(htmlspecialchars($tMsg, ENT_QUOTES, 'UTF-8')) ?></p>
            <?php if ($tAtt !== []): ?>
              <div class="ct-ticket__thumbs">
                <?php foreach ($tAtt as $relPath):
                  $abs = url($relPath);
                ?>
                  <img class="ct-ticket__thumb" src="<?= htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') ?>"
                       alt="Pièce jointe du ticket #<?= (int) $t['id'] ?>"
                       data-lightbox="<?= htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') ?>">
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <h2 class="ct-section-title" id="new-ticket-title" style="margin-top: 42px;">
      Nouveau ticket
    </h2>

    <section id="new-ticket" class="ct-form-card">
      <div class="ct-form-head">
        <div class="ct-form-head__icon">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
          </svg>
        </div>
        <div>
          <h2>Créer une demande</h2>
          <p>Décrivez votre besoin, choisissez l’urgence et joignez des captures si utile.</p>
        </div>
      </div>

      <?php if ($error !== ''): ?>
        <div class="ct-alert ct-alert--err" role="alert">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <span><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= htmlspecialchars(url('client_support.php'), ENT_QUOTES, 'UTF-8') ?>#new-ticket" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="t" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

        <!-- 01 — Type de demande -->
        <fieldset class="ct-fieldset">
          <legend class="ct-fieldset__legend">
            <span class="ct-fieldset__num">1</span>
            Type de demande
          </legend>
          <div class="ct-card-grid">
            <?php foreach ($catMeta as $slug => $meta): ?>
              <label class="ct-card-radio">
                <input type="radio" name="category" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"
                  <?= $postedCategory === $slug ? 'checked' : '' ?>>
                <span class="ct-card-radio__icon"><?= $meta['icon'] ?></span>
                <span class="ct-card-radio__body">
                  <span class="ct-card-radio__title"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="ct-card-radio__desc"><?= htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                </span>
                <span class="ct-card-radio__check" aria-hidden="true"></span>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>

        <!-- 02 — Sujet & priorité -->
        <fieldset class="ct-fieldset">
          <legend class="ct-fieldset__legend">
            <span class="ct-fieldset__num">2</span>
            Sujet &amp; urgence
          </legend>
          <div class="ct-field">
            <label for="subject">Sujet de la demande</label>
            <input type="text" id="subject" name="subject" required maxlength="255"
              class="ct-input"
              placeholder="Ex. Bouton « contact » cassé sur mobile"
              value="<?= htmlspecialchars((string) ($_POST['subject'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          </div>

          <div class="ct-field" style="margin-bottom: 4px;">
            <label>Niveau d’urgence</label>
            <div class="ct-priority-grid">
              <?php foreach ($priMeta as $slug => $meta): ?>
                <label class="ct-priority" style="--pri-color: <?= htmlspecialchars($meta['color'], ENT_QUOTES, 'UTF-8') ?>;">
                  <input type="radio" name="priority" value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>"
                    <?= $postedPriority === $slug ? 'checked' : '' ?>>
                  <span class="ct-priority__dot" aria-hidden="true"></span>
                  <span class="ct-priority__label"><?= htmlspecialchars($meta['label'], ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="ct-priority__desc"><?= htmlspecialchars($meta['desc'], ENT_QUOTES, 'UTF-8') ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </fieldset>

        <!-- 03 — Description -->
        <fieldset class="ct-fieldset">
          <legend class="ct-fieldset__legend">
            <span class="ct-fieldset__num">3</span>
            Description
          </legend>
          <div class="ct-field" style="margin-bottom: 6px;">
            <label for="message">Détaillez votre demande</label>
            <textarea id="message" name="message" required maxlength="12000"
              class="ct-textarea"
              placeholder="Que se passe-t-il ? Sur quelle page ? Depuis quand ?…"><?= htmlspecialchars((string) ($_POST['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
            <p class="ct-hint">Plus c’est précis, plus on traite vite.</p>
          </div>
        </fieldset>

        <!-- 04 — Images (max 3) -->
        <fieldset class="ct-fieldset">
          <legend class="ct-fieldset__legend">
            <span class="ct-fieldset__num">4</span>
            Captures d’écran <span style="text-transform:none;letter-spacing:0;color:var(--ct-muted);font-weight:600;">— jusqu’à 3 images</span>
          </legend>
          <div class="ct-uploads" id="ct-uploads">
            <?php for ($i = 0; $i < 3; $i++): ?>
              <label class="ct-upload-slot" data-slot="<?= $i ?>">
                <button type="button" class="ct-upload-slot__remove" aria-label="Retirer cette image">×</button>
                <img class="ct-upload-slot__preview" alt="">
                <span class="ct-upload-slot__placeholder" style="display:flex;flex-direction:column;align-items:center;gap:6px;">
                  <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3" ry="3"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                  <span class="ct-upload-slot__label">Ajouter une image</span>
                </span>
                <input type="file" name="attachments[]" accept="image/jpeg,image/png,image/webp,image/gif">
              </label>
            <?php endfor; ?>
          </div>
          <p class="ct-hint">JPG, PNG, WebP ou GIF — 5 Mo max par image.</p>
        </fieldset>

        <!-- 05 — Contact -->
        <fieldset class="ct-fieldset">
          <legend class="ct-fieldset__legend">
            <span class="ct-fieldset__num">5</span>
            Personne à contacter <span style="text-transform:none;letter-spacing:0;color:var(--ct-muted);font-weight:600;">— optionnel</span>
          </legend>
          <div class="ct-grid-2">
            <div class="ct-field">
              <label for="requester_name">Nom du contact</label>
              <input type="text" id="requester_name" name="requester_name" maxlength="255"
                class="ct-input" placeholder="Prénom Nom"
                value="<?= htmlspecialchars((string) ($_POST['requester_name'] ?? $defaultContactName), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <div class="ct-field">
              <label for="requester_email">Email du contact</label>
              <input type="email" id="requester_email" name="requester_email" maxlength="255"
                class="ct-input" placeholder="nom@entreprise.com"
                value="<?= htmlspecialchars((string) ($_POST['requester_email'] ?? $defaultContactEmail), ENT_QUOTES, 'UTF-8') ?>">
            </div>
          </div>
          <div class="ct-field" style="margin-bottom: 0;">
            <label>Téléphone à contacter</label>
            <div class="ct-phone-row">
              <?= render_country_dial_select('requester_phone_cc',
                  (string) ($_POST['requester_phone_cc'] ?? ($defaultPhoneCc ?? '221')),
                  [
                    'id' => 'requester_phone_cc',
                    'class' => 'ct-select ct-phone-cc',
                    'autocomplete' => 'country',
                    'aria-label' => 'Indicatif téléphonique pays',
                  ]) ?>
              <input type="tel" id="requester_phone_local" name="requester_phone_local"
                maxlength="40" inputmode="tel"
                class="ct-input" autocomplete="tel-national"
                placeholder="77 123 45 67"
                value="<?= htmlspecialchars((string) ($_POST['requester_phone_local'] ?? $defaultPhoneLocal), ENT_QUOTES, 'UTF-8') ?>">
            </div>
            <p class="ct-hint">Laissez vide si vous préférez être contacté uniquement par email.</p>
          </div>
        </fieldset>

        <div class="ct-form-foot">
          <p class="ct-form-foot__hint">Vos données restent confidentielles et sont utilisées uniquement pour traiter votre demande.</p>
          <button type="submit" class="ct-submit">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
            </svg>
            Envoyer le ticket
          </button>
        </div>
      </form>
    </section>
  </main>

  <div class="ct-lightbox" id="ct-lightbox" role="dialog" aria-modal="true" aria-hidden="true">
    <img id="ct-lightbox-img" src="" alt="Pièce jointe agrandie">
  </div>

  <script>
  (function () {
    // Upload slots preview + remove
    document.querySelectorAll('.ct-upload-slot').forEach(function (slot) {
      var input = slot.querySelector('input[type="file"]');
      var preview = slot.querySelector('.ct-upload-slot__preview');
      var removeBtn = slot.querySelector('.ct-upload-slot__remove');
      input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        if (!file) {
          slot.classList.remove('has-file');
          preview.src = '';
          return;
        }
        if (file.size > 5 * 1024 * 1024) {
          alert('Image trop lourde : 5 Mo maximum.');
          input.value = '';
          slot.classList.remove('has-file');
          return;
        }
        var reader = new FileReader();
        reader.onload = function (e) {
          preview.src = e.target.result;
          slot.classList.add('has-file');
        };
        reader.readAsDataURL(file);
      });
      removeBtn.addEventListener('click', function (ev) {
        ev.preventDefault();
        ev.stopPropagation();
        input.value = '';
        preview.src = '';
        slot.classList.remove('has-file');
      });
    });

    // Lightbox sur miniatures
    var lb = document.getElementById('ct-lightbox');
    var lbImg = document.getElementById('ct-lightbox-img');
    document.querySelectorAll('.ct-ticket__thumb').forEach(function (img) {
      img.addEventListener('click', function () {
        lbImg.src = img.getAttribute('data-lightbox') || img.src;
        lb.classList.add('is-open');
        lb.setAttribute('aria-hidden', 'false');
      });
    });
    if (lb) {
      lb.addEventListener('click', function () {
        lb.classList.remove('is-open');
        lb.setAttribute('aria-hidden', 'true');
        lbImg.src = '';
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && lb.classList.contains('is-open')) {
          lb.classList.remove('is-open');
          lb.setAttribute('aria-hidden', 'true');
          lbImg.src = '';
        }
      });
    }
  })();
  </script>
</body>
</html>
