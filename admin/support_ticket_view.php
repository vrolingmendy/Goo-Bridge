<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/support_ticket.php';
require_once __DIR__ . '/../includes/support_ticket_mail.php';

require_admin();

/**
 * Récupère ticket + ligne client pour envoyer les notifications email.
 * @return array{0: array, 1: array}|null  [ticket, client] ou null si absent
 */
function tv2_load_ticket_with_client(PDO $pdo, int $tid): ?array
{
    $sql = 'SELECT t.*, c.id AS c_id, c.company_name, c.email AS client_email,
                   c.contact_name, c.phone AS client_phone, c.ticket_portal_token
            FROM client_support_tickets t
            LEFT JOIN clients c ON c.id = t.client_id
            WHERE t.id = :id LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute(['id' => $tid]);
    $r = $st->fetch();
    if (!$r) return null;
    $client = [
        'id' => (int) ($r['c_id'] ?? 0),
        'company_name' => (string) ($r['company_name'] ?? ''),
        'email' => (string) ($r['client_email'] ?? ''),
        'contact_name' => (string) ($r['contact_name'] ?? ''),
        'phone' => (string) ($r['client_phone'] ?? ''),
        'ticket_portal_token' => (string) ($r['ticket_portal_token'] ?? ''),
    ];
    $ticket = [
        'id' => (int) $r['id'],
        'client_id' => (int) $r['client_id'],
        'category' => (string) $r['category'],
        'priority' => (string) $r['priority'],
        'subject' => (string) $r['subject'],
        'message' => (string) $r['message'],
        'requester_name' => (string) ($r['requester_name'] ?? ''),
        'requester_email' => (string) ($r['requester_email'] ?? ''),
        'requester_phone' => (string) ($r['requester_phone'] ?? ''),
        'status' => (string) $r['status'],
    ];
    return [$ticket, $client];
}

function tv2_admin_email(PDO $pdo, ?int $adminId): ?string
{
    if (!$adminId) return null;
    $st = $pdo->prepare('SELECT email FROM admins WHERE id = :id LIMIT 1');
    $st->execute(['id' => $adminId]);
    $v = $st->fetchColumn();
    return is_string($v) && $v !== '' ? $v : null;
}

$pdo = db();
$adminId = current_admin_id();

$ticketId = (int) ($_GET['id'] ?? $_POST['ticket_id'] ?? 0);

/* === Action POST ======================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        header('Location: ' . url('admin/support_tickets.php?flash=csrf'), true, 302);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    if ($ticketId <= 0) {
        header('Location: ' . url('admin/support_tickets.php'), true, 302);
        exit;
    }
    try {
        if ($action === 'add_note') {
            $body = trim((string) ($_POST['note_body'] ?? ''));
            if ($body === '') {
                header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=note_empty#notes'), true, 303);
                exit;
            }
            // Garde-fou : refuse l'ajout si le ticket est déjà résolu
            $stRow = $pdo->prepare('SELECT status FROM client_support_tickets WHERE id = :id LIMIT 1');
            $stRow->execute(['id' => $ticketId]);
            $stCurrent = (string) ($stRow->fetchColumn() ?: '');
            if ($stCurrent === 'closed') {
                header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=note_locked#notes'), true, 303);
                exit;
            }
            if (mb_strlen($body) > 5000) {
                $body = mb_substr($body, 0, 5000);
            }
            $ins = $pdo->prepare(
                'INSERT INTO client_support_ticket_notes (ticket_id, admin_id, body)
                 VALUES (:tid, :aid, :body)'
            );
            $ins->execute(['tid' => $ticketId, 'aid' => $adminId, 'body' => $body]);
            // Auto-assignation à l'admin si pas encore pris en charge
            $pdo->prepare(
                'UPDATE client_support_tickets
                 SET assigned_admin_id = COALESCE(assigned_admin_id, :aid),
                     taken_at = COALESCE(taken_at, NOW())
                 WHERE id = :id LIMIT 1'
            )->execute(['aid' => $adminId, 'id' => $ticketId]);

            // Notification email au client : nouvelle intervention
            $loaded = tv2_load_ticket_with_client($pdo, $ticketId);
            if ($loaded !== null) {
                [$tk, $cl] = $loaded;
                send_support_ticket_note_client_mail($cl, $tk, $body, tv2_admin_email($pdo, $adminId));
            }

            header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=note_added#notes'), true, 303);
            exit;
        }
        if ($action === 'take') {
            // Lit le ticket AVANT pour savoir s'il était déjà assigné
            $wasAssignedStmt = $pdo->prepare('SELECT assigned_admin_id FROM client_support_tickets WHERE id = :id LIMIT 1');
            $wasAssignedStmt->execute(['id' => $ticketId]);
            $wasAssigned = (int) ($wasAssignedStmt->fetchColumn() ?: 0);

            $up = $pdo->prepare(
                'UPDATE client_support_tickets
                 SET assigned_admin_id = :aid, taken_at = NOW()
                 WHERE id = :id LIMIT 1'
            );
            $up->execute(['aid' => $adminId, 'id' => $ticketId]);

            // Notif client uniquement si c'est une vraie prise en charge (pas réassignation interne)
            if ($wasAssigned === 0 || $wasAssigned !== (int) $adminId) {
                $loaded = tv2_load_ticket_with_client($pdo, $ticketId);
                if ($loaded !== null) {
                    [$tk, $cl] = $loaded;
                    send_support_ticket_taken_client_mail($cl, $tk, tv2_admin_email($pdo, $adminId));
                }
            }
        } elseif ($action === 'release') {
            $up = $pdo->prepare(
                'UPDATE client_support_tickets
                 SET assigned_admin_id = NULL, taken_at = NULL
                 WHERE id = :id LIMIT 1'
            );
            $up->execute(['id' => $ticketId]);
        } elseif ($action === 'transfer') {
            $newAdminId = (int) ($_POST['new_admin_id'] ?? 0);
            if ($newAdminId <= 0) {
                header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=error'), true, 303);
                exit;
            }
            // Vérifier que l'admin destinataire existe
            $chk = $pdo->prepare('SELECT id FROM admins WHERE id = :id LIMIT 1');
            $chk->execute(['id' => $newAdminId]);
            if (!$chk->fetchColumn()) {
                header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=error'), true, 303);
                exit;
            }
            $up = $pdo->prepare(
                'UPDATE client_support_tickets
                 SET assigned_admin_id = :aid, taken_at = NOW()
                 WHERE id = :id LIMIT 1'
            );
            $up->execute(['aid' => $newAdminId, 'id' => $ticketId]);

            // Notification client : nouvel admin prend en charge
            $loaded = tv2_load_ticket_with_client($pdo, $ticketId);
            if ($loaded !== null) {
                [$tk, $cl] = $loaded;
                send_support_ticket_taken_client_mail($cl, $tk, tv2_admin_email($pdo, $newAdminId));
            }

            header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=transferred'), true, 303);
            exit;
        } elseif ($action === 'close') {
            // État avant
            $wasStatusStmt = $pdo->prepare('SELECT status FROM client_support_tickets WHERE id = :id LIMIT 1');
            $wasStatusStmt->execute(['id' => $ticketId]);
            $wasStatus = (string) ($wasStatusStmt->fetchColumn() ?: '');

            $up = $pdo->prepare(
                "UPDATE client_support_tickets
                 SET status='closed', closed_at=NOW(),
                     assigned_admin_id = COALESCE(assigned_admin_id, :aid),
                     taken_at = COALESCE(taken_at, NOW())
                 WHERE id = :id LIMIT 1"
            );
            $up->execute(['aid' => $adminId, 'id' => $ticketId]);

            if ($wasStatus !== 'closed') {
                $loaded = tv2_load_ticket_with_client($pdo, $ticketId);
                if ($loaded !== null) {
                    [$tk, $cl] = $loaded;
                    send_support_ticket_closed_client_mail($cl, $tk);
                }
            }
        } elseif ($action === 'reopen') {
            $wasStatusStmt = $pdo->prepare('SELECT status FROM client_support_tickets WHERE id = :id LIMIT 1');
            $wasStatusStmt->execute(['id' => $ticketId]);
            $wasStatus = (string) ($wasStatusStmt->fetchColumn() ?: '');

            $up = $pdo->prepare(
                "UPDATE client_support_tickets
                 SET status='open', closed_at=NULL
                 WHERE id = :id LIMIT 1"
            );
            $up->execute(['id' => $ticketId]);

            if ($wasStatus === 'closed') {
                $loaded = tv2_load_ticket_with_client($pdo, $ticketId);
                if ($loaded !== null) {
                    [$tk, $cl] = $loaded;
                    send_support_ticket_reopened_client_mail($cl, $tk);
                }
            }
        }
        header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=updated'), true, 303);
        exit;
    } catch (Throwable $e) {
        header('Location: ' . url('admin/support_ticket_view.php?id=' . $ticketId . '&flash=error'), true, 302);
        exit;
    }
}

if ($ticketId <= 0) {
    header('Location: ' . url('admin/support_tickets.php'), true, 302);
    exit;
}

/* === Lecture du ticket ================================================= */
$sql = 'SELECT t.id, t.client_id, t.category, t.priority, t.subject, t.message,
               t.status, t.created_at, t.closed_at, t.taken_at, t.assigned_admin_id,
               t.requester_name, t.requester_email, t.requester_phone, t.attachments_json,
               c.company_name, c.contact_name AS client_contact, c.email AS client_email,
               c.phone AS client_phone, c.website_url, c.project_type,
               a.email AS assigned_email
        FROM client_support_tickets t
        LEFT JOIN clients c ON c.id = t.client_id
        LEFT JOIN admins a ON a.id = t.assigned_admin_id
        WHERE t.id = :id
        LIMIT 1';
$stmt = $pdo->prepare($sql);
$stmt->execute(['id' => $ticketId]);
$t = $stmt->fetch();

if (!$t) {
    header('Location: ' . url('admin/support_tickets.php?flash=notfound'), true, 302);
    exit;
}

$pageTitle = 'Ticket #' . (int) $t['id'] . ' — Goo-Bridge Admin';
$activeNav = 'tickets';

$catMeta = support_ticket_categories_meta();
$priMeta = support_ticket_priorities_meta();
$tStatus = (string) ($t['status'] ?? 'open');
$tPri = (string) ($t['priority'] ?? 'normal');
$tCat = (string) ($t['category'] ?? 'other');
$priColor = $priMeta[$tPri]['color'] ?? '#64748b';
$priLabel = $priMeta[$tPri]['label'] ?? ucfirst($tPri);
$catLabel = support_ticket_category_label($tCat);
$catIcon = $catMeta[$tCat]['icon'] ?? '';
$att = support_ticket_decode_attachments($t['attachments_json'] ?? null);
$isAssigned = !empty($t['assigned_admin_id']);
$assignedToMe = $isAssigned && $adminId !== null && (int) $t['assigned_admin_id'] === (int) $adminId;
$assignedEmail = (string) ($t['assigned_email'] ?? '');

$flash = (string) ($_GET['flash'] ?? '');
$flashMsg = '';
$flashKind = 'ok';
if ($flash === 'updated') {
    $flashMsg = 'Ticket mis à jour.';
} elseif ($flash === 'transferred') {
    $flashMsg = 'Ticket transféré avec succès.';
} elseif ($flash === 'csrf') {
    $flashMsg = 'Session invalide. Rechargez la page.';
    $flashKind = 'err';
} elseif ($flash === 'error') {
    $flashMsg = 'Erreur lors de la mise à jour du ticket.';
    $flashKind = 'err';
} elseif ($flash === 'note_added') {
    $flashMsg = 'Intervention enregistrée dans l’historique.';
} elseif ($flash === 'note_empty') {
    $flashMsg = 'Le contenu de l’intervention ne peut pas être vide.';
    $flashKind = 'err';
} elseif ($flash === 'note_locked') {
    $flashMsg = 'Ce ticket est résolu : impossible d’ajouter une nouvelle intervention. Rouvrez-le si nécessaire.';
    $flashKind = 'err';
}

/* === Liste des admins (pour le transfert) =============================== */
$otherAdmins = [];
try {
    $aStmt = $pdo->prepare(
        'SELECT id, email FROM admins WHERE id <> :me ORDER BY email ASC'
    );
    $aStmt->execute(['me' => (int) ($adminId ?? 0)]);
    $otherAdmins = $aStmt->fetchAll();
} catch (Throwable $e) {
    $otherAdmins = [];
}

/* === Lecture de l'historique des interventions ========================= */
$notes = [];
try {
    $stmtN = $pdo->prepare(
        'SELECT n.id, n.body, n.created_at, n.admin_id, a.email AS admin_email
         FROM client_support_ticket_notes n
         LEFT JOIN admins a ON a.id = n.admin_id
         WHERE n.ticket_id = :tid
         ORDER BY n.created_at DESC, n.id DESC'
    );
    $stmtN->execute(['tid' => $ticketId]);
    $notes = $stmtN->fetchAll();
} catch (Throwable $e) {
    $notes = [];
}

function astv_format_dt(?string $iso): string
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

function astv_human_diff(?string $iso): string
{
    if ($iso === null || $iso === '') {
        return '';
    }
    $ts = strtotime($iso);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'à l’instant';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return 'il y a ' . $m . ' min';
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return 'il y a ' . $h . ' h';
    }
    $d = (int) floor($diff / 86400);
    return 'il y a ' . $d . ' j';
}

/* === Si la demande est de type "update" → vue maintenance ============== */
$isMaintenance = ($tCat === 'update');
$maintInfo = [
    'exists'    => false,
    'maint_id'  => 0,
    'performed' => '',
    'quota'     => 0,
    'done_year' => 0,
    'remaining' => 0,
    'overflow'  => 0,
    'needs_bill'=> false,
    'currency'  => 'EUR',
    'price'     => 0.0,
];
if ($isMaintenance) {
    try {
        $mStmt = $pdo->prepare(
            'SELECT id, performed_at FROM client_maintenances WHERE ticket_id = :tid LIMIT 1'
        );
        $mStmt->execute(['tid' => (int) $t['id']]);
        $mRow = $mStmt->fetch();
        if ($mRow) {
            $maintInfo['exists'] = true;
            $maintInfo['maint_id'] = (int) $mRow['id'];
            $maintInfo['performed'] = (string) $mRow['performed_at'];
        }

        $cStmt = $pdo->prepare(
            'SELECT COALESCE(maintenances_per_year, 0) AS quota,
                    COALESCE(maintenance_annual_price, 0) AS price,
                    COALESCE(billing_currency, \'EUR\') AS currency
             FROM clients WHERE id = :cid LIMIT 1'
        );
        $cStmt->execute(['cid' => (int) $t['client_id']]);
        $cRow = $cStmt->fetch();
        if ($cRow) {
            $maintInfo['quota'] = (int) $cRow['quota'];
            $maintInfo['price'] = (float) $cRow['price'];
            $maintInfo['currency'] = (string) ($cRow['currency'] ?? 'EUR');
        }

        $yStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM client_maintenances
             WHERE client_id = :cid AND YEAR(performed_at) = YEAR(CURDATE())'
        );
        $yStmt->execute(['cid' => (int) $t['client_id']]);
        $maintInfo['done_year'] = (int) $yStmt->fetchColumn();

        if ($maintInfo['quota'] > 0) {
            $maintInfo['remaining'] = max(0, $maintInfo['quota'] - $maintInfo['done_year']);
            $maintInfo['overflow']  = max(0, $maintInfo['done_year'] - $maintInfo['quota']);
            $maintInfo['needs_bill'] = $maintInfo['overflow'] > 0;
        } else {
            $maintInfo['remaining'] = 0;
            $maintInfo['overflow']  = $maintInfo['done_year'];
            $maintInfo['needs_bill'] = $maintInfo['done_year'] > 0;
        }
    } catch (Throwable $e) {
        // ignoré
    }
}

$tSubject = (string) ($t['subject'] ?? '');
$tMessage = (string) ($t['message'] ?? '');
$tCreated = (string) ($t['created_at'] ?? '');
$tClosed = (string) ($t['closed_at'] ?? '');
$tTaken = (string) ($t['taken_at'] ?? '');
$company = (string) ($t['company_name'] ?? '—');
$reqName = (string) ($t['requester_name'] ?? '');
$reqEmail = (string) ($t['requester_email'] ?? '');
$reqPhone = (string) ($t['requester_phone'] ?? '');
$clientEmail = (string) ($t['client_email'] ?? '');
$clientPhone = (string) ($t['client_phone'] ?? '');
$clientContact = (string) ($t['client_contact'] ?? '');
$mailToUse = $reqEmail !== '' ? $reqEmail : $clientEmail;
$phoneToUse = $reqPhone !== '' ? $reqPhone : $clientPhone;
$clientUrl = url('admin/client_detail.php?id=' . (int) $t['client_id']);
$backUrl = url('admin/support_tickets.php');

require __DIR__ . '/inc/header.php';
?>

<div class="admin-tickets-page admin-ticket-view-page">
  <nav class="admin-ticket-view__crumbs" aria-label="Fil d’Ariane">
    <a href="<?= htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8') ?>">← Retour aux tickets</a>
  </nav>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert admin-alert--<?= $flashKind === 'err' ? 'error' : 'ok' ?> admin-tickets-flash"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <section class="tv2" style="--ticket-pri: <?= htmlspecialchars($priColor, ENT_QUOTES, 'UTF-8') ?>;">

    <!-- ============ HERO ============ -->
    <header class="tv2-hero">
      <div class="tv2-hero__bar" aria-hidden="true"></div>
      <div class="tv2-hero__inner">
        <div class="tv2-hero__left">
          <div class="tv2-hero__crumbs">
            <span class="tv2-hero__id">#<?= (int) $t['id'] ?></span>
            <span class="tv2-hero__sep" aria-hidden="true">·</span>
            <span class="tv2-hero__company">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v14"/><path d="M9 9h.01"/><path d="M9 13h.01"/><path d="M15 9h.01"/><path d="M15 13h.01"/></svg>
              <a href="<?= htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8') ?></a>
            </span>
          </div>

          <div class="tv2-hero__chips">
            <span class="tv2-chip tv2-chip--pri" style="--c: <?= htmlspecialchars($priColor, ENT_QUOTES, 'UTF-8') ?>">
              <span class="tv2-chip__dot" aria-hidden="true"></span>
              <?= htmlspecialchars($priLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="tv2-chip tv2-chip--status tv2-chip--<?= $tStatus === 'closed' ? 'closed' : ($isAssigned ? 'progress' : 'new') ?>">
              <?= $tStatus === 'closed' ? 'Résolu' : ($isAssigned ? 'En traitement' : 'Nouveau') ?>
            </span>
            <span class="tv2-chip tv2-chip--cat">
              <?= $catIcon ?>
              <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
            <?php if ($isMaintenance): ?>
              <span class="tv2-chip tv2-chip--maint">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-3.2 3.2-2.6-2.6 3.2-3.2z"/></svg>
                Maintenance
              </span>
            <?php endif; ?>
          </div>

          <h1 class="tv2-hero__title"><?= htmlspecialchars($tSubject, ENT_QUOTES, 'UTF-8') ?></h1>

          <ul class="tv2-hero__meta" role="list">
            <li title="Date d'ouverture">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
              Ouvert le <?= htmlspecialchars(astv_format_dt($tCreated), ENT_QUOTES, 'UTF-8') ?>
              <?php $diff = astv_human_diff($tCreated); if ($diff !== ''): ?>
                <span class="tv2-hero__rel">(<?= htmlspecialchars($diff, ENT_QUOTES, 'UTF-8') ?>)</span>
              <?php endif; ?>
            </li>
            <?php if ($tStatus === 'closed' && $tClosed !== ''): ?>
              <li>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Clôturé le <?= htmlspecialchars(astv_format_dt($tClosed), ENT_QUOTES, 'UTF-8') ?>
              </li>
            <?php endif; ?>
            <?php if ($isAssigned && $assignedEmail !== ''): ?>
              <li>
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                Pris en charge par <?= htmlspecialchars($assignedEmail, ENT_QUOTES, 'UTF-8') ?>
                <?php if ($assignedToMe): ?><span class="tv2-hero__me">vous</span><?php endif; ?>
              </li>
            <?php endif; ?>
          </ul>
        </div>

        <div class="tv2-hero__actions">
          <?php if ($tStatus === 'open' && !$isAssigned): ?>
            <form method="post" action="<?= htmlspecialchars(url('admin/support_ticket_view.php'), ENT_QUOTES, 'UTF-8') ?>" class="tv2-form">
              <?= csrf_field() ?>
              <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" name="action" value="take" class="tv2-btn tv2-btn--take">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                Prendre en charge
              </button>
            </form>
          <?php elseif ($tStatus === 'open' && $assignedToMe): ?>
            <?php if ($otherAdmins !== []): ?>
              <form method="post" action="<?= htmlspecialchars(url('admin/support_ticket_view.php'), ENT_QUOTES, 'UTF-8') ?>" class="tv2-form tv2-transfer">
                <?= csrf_field() ?>
                <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
                <input type="hidden" name="action" value="transfer">
                <label class="tv2-transfer__label" for="tv2-transfer-select">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                  Transférer à
                </label>
                <select id="tv2-transfer-select" name="new_admin_id" class="tv2-transfer__select" required
                        onchange="if(this.value && this.form) this.form.submit();">
                  <option value="">— choisir un administrateur —</option>
                  <?php foreach ($otherAdmins as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars((string) $a['email'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
                <button type="submit" class="tv2-btn tv2-btn--transfer" aria-label="Valider le transfert">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                </button>
              </form>
            <?php else: ?>
              <span class="tv2-transfer__none">Aucun autre administrateur disponible</span>
            <?php endif; ?>
          <?php elseif ($tStatus === 'open' && $isAssigned && !$assignedToMe): ?>
            <form method="post" action="<?= htmlspecialchars(url('admin/support_ticket_view.php'), ENT_QUOTES, 'UTF-8') ?>" class="tv2-form">
              <?= csrf_field() ?>
              <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" name="action" value="take" class="tv2-btn tv2-btn--take">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                Reprendre
              </button>
            </form>
          <?php endif; ?>

          <?php if ($tStatus === 'open'): ?>
            <form method="post" action="<?= htmlspecialchars(url('admin/support_ticket_view.php'), ENT_QUOTES, 'UTF-8') ?>" class="tv2-form">
              <?= csrf_field() ?>
              <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" name="action" value="close" class="tv2-btn tv2-btn--done">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Marquer résolu
              </button>
            </form>
          <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(url('admin/support_ticket_view.php'), ENT_QUOTES, 'UTF-8') ?>" class="tv2-form">
              <?= csrf_field() ?>
              <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
              <button type="submit" name="action" value="reopen" class="tv2-btn tv2-btn--reopen">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
                Rouvrir
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>
    </header>

    <div class="tv2-body">

    <?php if ($isMaintenance): ?>
      <?php
        $hasQuota = (int) $maintInfo['quota'] > 0;
        $needsBill = (bool) $maintInfo['needs_bill'];
        if ($needsBill) {
          $maintLabel = 'À facturer';
          $maintTone = 'alert';
        } elseif ($hasQuota) {
          $maintLabel = 'Incluse dans le forfait';
          $maintTone = 'ok';
        } else {
          $maintLabel = 'À facturer';
          $maintTone = 'alert';
        }
      ?>
      <section class="tv2-maint tv2-maint--<?= $maintTone ?>" aria-label="Statut maintenance">
        <span class="tv2-maint__icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-3.2 3.2-2.6-2.6 3.2-3.2z"/></svg>
        </span>
        <div class="tv2-maint__text">
          <span class="tv2-maint__label">Maintenance <?= date('Y') ?></span>
          <span class="tv2-maint__sep" aria-hidden="true">·</span>
          <span class="tv2-maint__status"><?= htmlspecialchars($maintLabel, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="tv2-maint__metric">
          <strong><?= (int) $maintInfo['done_year'] ?><?= $hasQuota ? '<span class="tv2-maint__metric-q">/' . (int) $maintInfo['quota'] . '</span>' : '' ?></strong>
          <span>intervention<?= (int) $maintInfo['done_year'] !== 1 ? 's' : '' ?> · <?= date('Y') ?></span>
        </div>
        <a class="tv2-maint__link" href="<?= htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8') ?>#history-heading">
          Historique
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
        </a>
      </section>
    <?php endif; ?>

    <div class="tv2-grid">
      <div class="tv2-main">
        <article class="tv2-card tv2-card--message">
          <header class="tv2-card__head">
            <span class="tv2-card__icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </span>
            <h2 class="tv2-card__title">Message du client</h2>
            <span class="tv2-card__hint">Demande initiale envoyée le <?= htmlspecialchars(astv_format_dt($tCreated), ENT_QUOTES, 'UTF-8') ?></span>
          </header>
          <div class="tv2-card__body">
            <div class="tv2-msg-box">
              <div class="tv2-msg-box__quote" aria-hidden="true">“</div>
              <p class="tv2-msg-box__text"><?= nl2br(htmlspecialchars($tMessage, ENT_QUOTES, 'UTF-8')) ?></p>
            </div>
          </div>
        </article>

        <?php if ($att !== []): ?>
          <article class="tv2-card tv2-card--gallery">
            <header class="tv2-card__head">
              <span class="tv2-card__icon tv2-card__icon--gallery" aria-hidden="true">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              </span>
              <h2 class="tv2-card__title">Captures d’écran transmises par le client</h2>
              <span class="tv2-card__hint"><?= count($att) ?> image<?= count($att) > 1 ? 's' : '' ?></span>
            </header>
            <div class="tv2-card__body">
              <div class="tv2-gallery__grid tv2-gallery__grid--<?= count($att) === 1 ? 'one' : (count($att) === 2 ? 'two' : 'many') ?>" data-lightbox-root>
                <?php foreach ($att as $idx => $relPath):
                  $abs = url($relPath);
                ?>
                  <a class="tv2-gallery__item" href="<?= htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') ?>"
                     data-lightbox-trigger="<?= (int) $idx ?>"
                     aria-label="Agrandir l’image <?= (int) $idx + 1 ?>">
                    <img src="<?= htmlspecialchars($abs, ENT_QUOTES, 'UTF-8') ?>" alt="Pièce jointe <?= (int) $idx + 1 ?>" loading="lazy">
                    <span class="tv2-gallery__zoom" aria-hidden="true">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                    </span>
                    <span class="tv2-gallery__index">#<?= (int) $idx + 1 ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
          </article>
        <?php endif; ?>

        <article class="tv2-card admin-ticket-notes" id="notes">
          <header class="tv2-card__head">
            <span class="tv2-card__icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </span>
            <h2 class="tv2-card__title">Interventions effectuées</h2>
            <?php if ($notes !== []): ?>
              <span class="admin-ticket-notes__count"><?= count($notes) ?></span>
            <?php endif; ?>
          </header>
          <div class="tv2-card__body">
          <?php if ($notes === []): ?>
            <div class="admin-ticket-notes__empty">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
              <p>Aucune intervention enregistrée pour le moment.</p>
            </div>
          <?php else: ?>
            <ol class="admin-ticket-notes__list" role="list">
              <?php foreach ($notes as $n):
                $nBody = (string) ($n['body'] ?? '');
                $nWhen = (string) ($n['created_at'] ?? '');
                $nWho = (string) ($n['admin_email'] ?? '');
                $isMine = !empty($n['admin_id']) && $adminId !== null && (int) $n['admin_id'] === (int) $adminId;
              ?>
                <li class="admin-ticket-note">
                  <div class="admin-ticket-note__head">
                    <span class="admin-ticket-note__who">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                      <?= $nWho !== '' ? htmlspecialchars($nWho, ENT_QUOTES, 'UTF-8') : 'Admin' ?>
                      <?php if ($isMine): ?>
                        <span class="admin-ticket-view__pill-me">vous</span>
                      <?php endif; ?>
                    </span>
                    <span class="admin-ticket-note__when" title="<?= htmlspecialchars(astv_format_dt($nWhen), ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars(astv_format_dt($nWhen), ENT_QUOTES, 'UTF-8') ?>
                      <?php $rel = astv_human_diff($nWhen); if ($rel !== ''): ?>
                        · <span class="admin-ticket-note__rel"><?= htmlspecialchars($rel, ENT_QUOTES, 'UTF-8') ?></span>
                      <?php endif; ?>
                    </span>
                  </div>
                  <div class="admin-ticket-note__body"><?= nl2br(htmlspecialchars($nBody, ENT_QUOTES, 'UTF-8')) ?></div>
                </li>
              <?php endforeach; ?>
            </ol>
          <?php endif; ?>

          <?php if ($tStatus === 'closed'): ?>
            <div class="admin-ticket-notes__locked">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
              <span>Ce ticket est résolu — l’historique est verrouillé. Rouvrez le ticket pour ajouter une nouvelle intervention.</span>
            </div>
          <?php else: ?>
            <form method="post" action="<?= htmlspecialchars(url('admin/support_ticket_view.php'), ENT_QUOTES, 'UTF-8') ?>#notes" class="admin-ticket-notes__form">
              <?= csrf_field() ?>
              <input type="hidden" name="ticket_id" value="<?= (int) $t['id'] ?>">
              <input type="hidden" name="action" value="add_note">
              <label for="ticket-note-body" class="admin-ticket-notes__label">Nouvelle intervention</label>
              <textarea
                id="ticket-note-body"
                name="note_body"
                rows="4"
                maxlength="5000"
                placeholder="Décrivez précisément l’intervention : actions menées, fichiers modifiés, tests réalisés…"
                required></textarea>
              <div class="admin-ticket-notes__form-foot">
                <span class="admin-ticket-notes__hint">L’entrée s’ajoutera en haut de l’historique avec votre identifiant.</span>
                <button type="submit" class="btn-primary admin-ticket-notes__submit">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4 20-7Z"/></svg>
                  Envoyer
                </button>
              </div>
            </form>
          <?php endif; ?>
          </div>
        </article>
      </div>

      <aside class="tv2-side">
        <article class="tv2-card">
          <header class="tv2-card__head">
            <span class="tv2-card__icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v14"/><path d="M9 9h.01"/><path d="M9 13h.01"/><path d="M9 17h.01"/><path d="M15 9h.01"/><path d="M15 13h.01"/><path d="M15 17h.01"/></svg>
            </span>
            <h2 class="tv2-card__title">Entreprise</h2>
          </header>
          <div class="tv2-card__body">
          <div class="admin-ticket-view__kv">
            <span class="admin-ticket-view__k">Nom</span>
            <a class="admin-ticket-view__v admin-ticket-view__v--link" href="<?= htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8') ?></a>
          </div>
          <?php if ($clientContact !== ''): ?>
            <div class="admin-ticket-view__kv">
              <span class="admin-ticket-view__k">Contact réf.</span>
              <span class="admin-ticket-view__v"><?= htmlspecialchars($clientContact, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <?php if (!empty($t['project_type'])): ?>
            <div class="admin-ticket-view__kv">
              <span class="admin-ticket-view__k">Projet</span>
              <span class="admin-ticket-view__v"><?= htmlspecialchars((string) $t['project_type'], ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <?php if (!empty($t['website_url'])): ?>
            <div class="admin-ticket-view__kv">
              <span class="admin-ticket-view__k">Site</span>
              <a class="admin-ticket-view__v admin-ticket-view__v--link" href="<?= htmlspecialchars((string) $t['website_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener"><?= htmlspecialchars((string) $t['website_url'], ENT_QUOTES, 'UTF-8') ?> ↗</a>
            </div>
          <?php endif; ?>
          <a class="admin-ticket-view__more" href="<?= htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8') ?>">Voir la fiche complète →</a>
          </div>
        </article>

        <article class="tv2-card">
          <header class="tv2-card__head">
            <span class="tv2-card__icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </span>
            <h2 class="tv2-card__title">Demandeur</h2>
          </header>
          <div class="tv2-card__body">
          <?php if ($reqName !== ''): ?>
            <div class="admin-ticket-view__kv">
              <span class="admin-ticket-view__k">Nom</span>
              <span class="admin-ticket-view__v"><?= htmlspecialchars($reqName, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
          <?php endif; ?>
          <?php if ($mailToUse !== ''): ?>
            <div class="admin-ticket-view__kv">
              <span class="admin-ticket-view__k">Email</span>
              <a class="admin-ticket-view__v admin-ticket-view__v--link" href="mailto:<?= htmlspecialchars($mailToUse, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($mailToUse, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
          <?php endif; ?>
          <?php if ($phoneToUse !== ''): $telHref = 'tel:' . preg_replace('/[^\d+]/', '', $phoneToUse); ?>
            <div class="admin-ticket-view__kv">
              <span class="admin-ticket-view__k">Téléphone</span>
              <a class="admin-ticket-view__v admin-ticket-view__v--link" href="<?= htmlspecialchars($telHref, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($phoneToUse, ENT_QUOTES, 'UTF-8') ?></a>
            </div>
          <?php endif; ?>
          <?php if ($reqName === '' && $mailToUse === '' && $phoneToUse === ''): ?>
            <p class="admin-ticket-view__hint">Aucune information de contact directe.</p>
          <?php endif; ?>
          </div>
        </article>

        <article class="tv2-card">
          <header class="tv2-card__head">
            <span class="tv2-card__icon" aria-hidden="true">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </span>
            <h2 class="tv2-card__title">Suivi</h2>
          </header>
          <div class="tv2-card__body">
          <div class="admin-ticket-view__kv">
            <span class="admin-ticket-view__k">Statut</span>
            <span class="admin-ticket-view__v">
              <?= $tStatus === 'closed' ? 'Résolu' : ($isAssigned ? 'En traitement' : 'Nouveau, non pris en charge') ?>
            </span>
          </div>
          <div class="admin-ticket-view__kv">
            <span class="admin-ticket-view__k">Urgence</span>
            <span class="admin-ticket-view__v" style="color: <?= htmlspecialchars($priColor, ENT_QUOTES, 'UTF-8') ?>; font-weight:700;">
              <?= htmlspecialchars($priLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
          <?php if ($isAssigned): ?>
            <div class="admin-ticket-view__kv">
              <span class="admin-ticket-view__k">Pris en charge par</span>
              <span class="admin-ticket-view__v">
                <?= $assignedEmail !== '' ? htmlspecialchars($assignedEmail, ENT_QUOTES, 'UTF-8') : 'Admin #' . (int) $t['assigned_admin_id'] ?>
                <?php if ($assignedToMe): ?>
                  <span class="admin-ticket-view__pill-me">vous</span>
                <?php endif; ?>
              </span>
            </div>
            <?php if ($tTaken !== ''): ?>
              <div class="admin-ticket-view__kv">
                <span class="admin-ticket-view__k">Depuis</span>
                <span class="admin-ticket-view__v"><?= htmlspecialchars(astv_format_dt($tTaken), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            <?php endif; ?>
          <?php endif; ?>
          </div>
        </article>
      </aside>
    </div>
    </div><!-- /.tv2-body -->
  </section>
</div>

<?php if ($att !== []): ?>
<!-- Lightbox léger sans dépendance -->
<div class="tv2-lightbox" id="tv2-lightbox" role="dialog" aria-modal="true" aria-hidden="true">
  <button class="tv2-lightbox__close" type="button" aria-label="Fermer">&times;</button>
  <button class="tv2-lightbox__nav tv2-lightbox__nav--prev" type="button" aria-label="Précédent">‹</button>
  <button class="tv2-lightbox__nav tv2-lightbox__nav--next" type="button" aria-label="Suivant">›</button>
  <figure class="tv2-lightbox__figure">
    <img class="tv2-lightbox__img" alt="">
    <figcaption class="tv2-lightbox__cap"></figcaption>
  </figure>
</div>
<script>
(function(){
  var triggers = document.querySelectorAll('[data-lightbox-trigger]');
  if (!triggers.length) return;
  var images = Array.prototype.map.call(triggers, function(a){ return a.getAttribute('href'); });
  var box = document.getElementById('tv2-lightbox');
  var img = box.querySelector('.tv2-lightbox__img');
  var cap = box.querySelector('.tv2-lightbox__cap');
  var btnClose = box.querySelector('.tv2-lightbox__close');
  var btnPrev = box.querySelector('.tv2-lightbox__nav--prev');
  var btnNext = box.querySelector('.tv2-lightbox__nav--next');
  var current = 0;

  function show(i) {
    if (i < 0) i = images.length - 1;
    if (i >= images.length) i = 0;
    current = i;
    img.src = images[i];
    cap.textContent = 'Image ' + (i + 1) + ' / ' + images.length;
    box.classList.add('is-open');
    box.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
  }
  function close() {
    box.classList.remove('is-open');
    box.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
  }

  triggers.forEach(function(a, idx){
    a.addEventListener('click', function(e){ e.preventDefault(); show(idx); });
  });
  btnClose.addEventListener('click', close);
  btnPrev.addEventListener('click', function(){ show(current - 1); });
  btnNext.addEventListener('click', function(){ show(current + 1); });
  box.addEventListener('click', function(e){ if (e.target === box) close(); });
  document.addEventListener('keydown', function(e){
    if (!box.classList.contains('is-open')) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(current - 1);
    if (e.key === 'ArrowRight') show(current + 1);
  });
})();
</script>
<?php endif; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
