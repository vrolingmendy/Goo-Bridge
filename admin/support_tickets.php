<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/support_ticket.php';

require_admin();

$pageTitle = 'Tickets de support — Goo-Bridge Admin';
$activeNav = 'tickets';

$pdo = db();

/* === Action POST : changement de statut ouvert/fermé ===================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        header('Location: ' . url('admin/support_tickets.php?flash=csrf'), true, 302);
        exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $ticketId = (int) ($_POST['ticket_id'] ?? 0);
    if ($ticketId > 0 && in_array($action, ['close', 'reopen'], true)) {
        require_once __DIR__ . '/../includes/support_ticket_mail.php';
        try {
            // État précédent + données pour notification
            $prev = $pdo->prepare(
                'SELECT t.*, c.id AS c_id, c.company_name, c.email AS client_email,
                        c.contact_name, c.phone AS client_phone, c.ticket_portal_token
                 FROM client_support_tickets t
                 LEFT JOIN clients c ON c.id = t.client_id
                 WHERE t.id = :id LIMIT 1'
            );
            $prev->execute(['id' => $ticketId]);
            $prevRow = $prev->fetch();
            $wasStatus = (string) ($prevRow['status'] ?? '');

            if ($action === 'close') {
                $up = $pdo->prepare(
                    "UPDATE client_support_tickets SET status='closed', closed_at=NOW() WHERE id=:id LIMIT 1"
                );
            } else {
                $up = $pdo->prepare(
                    "UPDATE client_support_tickets SET status='open', closed_at=NULL WHERE id=:id LIMIT 1"
                );
            }
            $up->execute(['id' => $ticketId]);

            // Notifications client
            if ($prevRow) {
                $client = [
                    'id' => (int) ($prevRow['c_id'] ?? 0),
                    'company_name' => (string) ($prevRow['company_name'] ?? ''),
                    'email' => (string) ($prevRow['client_email'] ?? ''),
                    'contact_name' => (string) ($prevRow['contact_name'] ?? ''),
                    'phone' => (string) ($prevRow['client_phone'] ?? ''),
                    'ticket_portal_token' => (string) ($prevRow['ticket_portal_token'] ?? ''),
                ];
                $ticket = [
                    'id' => (int) $prevRow['id'],
                    'client_id' => (int) $prevRow['client_id'],
                    'category' => (string) $prevRow['category'],
                    'priority' => (string) $prevRow['priority'],
                    'subject' => (string) $prevRow['subject'],
                    'message' => (string) $prevRow['message'],
                    'requester_name' => (string) ($prevRow['requester_name'] ?? ''),
                    'requester_email' => (string) ($prevRow['requester_email'] ?? ''),
                ];
                if ($action === 'close' && $wasStatus !== 'closed') {
                    send_support_ticket_closed_client_mail($client, $ticket);
                } elseif ($action === 'reopen' && $wasStatus === 'closed') {
                    send_support_ticket_reopened_client_mail($client, $ticket);
                }
            }

            $back = (string) ($_POST['return_to'] ?? '');
            header('Location: ' . ($back !== '' ? $back : url('admin/support_tickets.php?flash=updated')), true, 303);
            exit;
        } catch (Throwable $e) {
            header('Location: ' . url('admin/support_tickets.php?flash=error'), true, 302);
            exit;
        }
    }
    header('Location: ' . url('admin/support_tickets.php'), true, 302);
    exit;
}

/* === Filtres ============================================================ */
// Par défaut on affiche les tickets en cours (les résolus sont dans leur propre onglet).
$filterStatus = (string) ($_GET['status'] ?? 'open');
if (!in_array($filterStatus, ['all', 'open', 'closed'], true)) {
    $filterStatus = 'open';
}
$filterPriority = (string) ($_GET['priority'] ?? 'all');
$validPriorities = array_keys(support_ticket_priorities());
if ($filterPriority !== 'all' && !in_array($filterPriority, $validPriorities, true)) {
    $filterPriority = 'all';
}
$filterCategory = (string) ($_GET['category'] ?? 'all');
$validCategories = array_keys(support_ticket_categories());
if ($filterCategory !== 'all' && !in_array($filterCategory, $validCategories, true)) {
    $filterCategory = 'all';
}
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 120) {
    $q = mb_substr($q, 0, 120);
}

/* === Lecture des tickets =============================================== */
$tickets = [];
$countAll = 0;
$countOpen = 0;
$countClosed = 0;
$countCritical = 0;

try {
    $countAll = (int) $pdo->query('SELECT COUNT(*) FROM client_support_tickets')->fetchColumn();
    $countOpen = (int) $pdo->query("SELECT COUNT(*) FROM client_support_tickets WHERE status='open'")->fetchColumn();
    $countClosed = (int) $pdo->query("SELECT COUNT(*) FROM client_support_tickets WHERE status='closed'")->fetchColumn();
    $countCritical = (int) $pdo->query("SELECT COUNT(*) FROM client_support_tickets WHERE status='open' AND priority='critical'")->fetchColumn();

    $sql = 'SELECT t.id, t.client_id, t.category, t.priority, t.subject, t.message,
                   t.status, t.created_at, t.closed_at, t.taken_at, t.assigned_admin_id,
                   t.requester_name, t.requester_email, t.requester_phone, t.attachments_json,
                   c.company_name, c.contact_name AS client_contact, c.email AS client_email, c.phone AS client_phone,
                   a.email AS assigned_email
            FROM client_support_tickets t
            LEFT JOIN clients c ON c.id = t.client_id
            LEFT JOIN admins a ON a.id = t.assigned_admin_id
            WHERE 1=1';
    $params = [];
    if ($filterStatus !== 'all') {
        $sql .= ' AND t.status = :st';
        $params['st'] = $filterStatus;
    }
    if ($filterPriority !== 'all') {
        $sql .= ' AND t.priority = :pr';
        $params['pr'] = $filterPriority;
    }
    if ($filterCategory !== 'all') {
        $sql .= ' AND t.category = :ca';
        $params['ca'] = $filterCategory;
    }
    if ($q !== '') {
        $sql .= ' AND (t.subject LIKE :qLike OR t.message LIKE :qLike2 OR c.company_name LIKE :qLike3 OR t.requester_name LIKE :qLike4 OR t.requester_email LIKE :qLike5)';
        $like = '%' . $q . '%';
        $params['qLike'] = $like;
        $params['qLike2'] = $like;
        $params['qLike3'] = $like;
        $params['qLike4'] = $like;
        $params['qLike5'] = $like;
    }
    // Priorités : critique > haute > normale > faible
    $sql .= " ORDER BY (t.status = 'open') DESC,
                       FIELD(t.priority,'critical','high','normal','low'),
                       t.created_at DESC
              LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll();
} catch (Throwable $e) {
    $tickets = [];
}

$flash = (string) ($_GET['flash'] ?? '');
$flashMsg = '';
$flashKind = 'ok';
if ($flash === 'updated') {
    $flashMsg = 'Ticket mis à jour.';
} elseif ($flash === 'csrf') {
    $flashMsg = 'Session invalide. Rechargez la page.';
    $flashKind = 'err';
} elseif ($flash === 'error') {
    $flashMsg = 'Erreur lors de la mise à jour du ticket.';
    $flashKind = 'err';
}

$catMeta = support_ticket_categories_meta();
$priMeta = support_ticket_priorities_meta();

function asu_format_dt(?string $iso): string
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

function asu_filter_qs(array $overrides): string
{
    $base = [
        'status' => $_GET['status'] ?? null,
        'priority' => $_GET['priority'] ?? null,
        'category' => $_GET['category'] ?? null,
        'q' => $_GET['q'] ?? null,
    ];
    $merged = array_merge($base, $overrides);
    $parts = [];
    foreach ($merged as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        // Pour status on garde toujours la valeur (y compris "all") car la valeur par défaut serveur est "open"
        if ($k !== 'status' && $v === 'all') {
            continue;
        }
        $parts[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }
    return $parts === [] ? '' : ('?' . implode('&', $parts));
}

require __DIR__ . '/inc/header.php';
?>

<div class="admin-tickets-page">
  <section class="admin-board admin-tickets-board">
    <header class="admin-board__head">
      <div class="admin-board__head-text">
        <span class="admin-board__eyebrow">Support</span>
        <h2>
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4v-2z"/><path d="M9 5v14"/></svg>
          Tickets de support
        </h2>
      </div>
    </header>

    <nav class="admin-tickets-tabs" aria-label="Filtrer par statut" role="tablist">
      <a class="admin-tickets-tabs__tab <?= $filterStatus === 'open' ? 'is-active' : '' ?>"
         role="tab" aria-selected="<?= $filterStatus === 'open' ? 'true' : 'false' ?>"
         href="<?= htmlspecialchars(url('admin/support_tickets.php' . asu_filter_qs(['status' => 'open'])), ENT_QUOTES, 'UTF-8') ?>">
        <span class="admin-tickets-tabs__dot admin-tickets-tabs__dot--open" aria-hidden="true"></span>
        En cours
        <span class="admin-tickets-tabs__count"><?= (int) $countOpen ?></span>
      </a>
      <a class="admin-tickets-tabs__tab <?= $filterStatus === 'closed' ? 'is-active' : '' ?>"
         role="tab" aria-selected="<?= $filterStatus === 'closed' ? 'true' : 'false' ?>"
         href="<?= htmlspecialchars(url('admin/support_tickets.php' . asu_filter_qs(['status' => 'closed'])), ENT_QUOTES, 'UTF-8') ?>">
        <span class="admin-tickets-tabs__dot admin-tickets-tabs__dot--closed" aria-hidden="true"></span>
        Résolus
        <span class="admin-tickets-tabs__count"><?= (int) $countClosed ?></span>
      </a>
      <a class="admin-tickets-tabs__tab <?= $filterStatus === 'all' ? 'is-active' : '' ?>"
         role="tab" aria-selected="<?= $filterStatus === 'all' ? 'true' : 'false' ?>"
         href="<?= htmlspecialchars(url('admin/support_tickets.php' . asu_filter_qs(['status' => 'all'])), ENT_QUOTES, 'UTF-8') ?>">
        Tous
        <span class="admin-tickets-tabs__count"><?= (int) $countAll ?></span>
      </a>
      <?php if ($countCritical > 0 && $filterStatus !== 'closed'): ?>
        <span class="admin-tickets-tabs__pill" title="Tickets critiques en cours">
          <span class="admin-tickets-tabs__dot admin-tickets-tabs__dot--critical" aria-hidden="true"></span>
          <?= (int) $countCritical ?> critique<?= $countCritical > 1 ? 's' : '' ?>
        </span>
      <?php endif; ?>
    </nav>

    <?php if ($flashMsg !== ''): ?>
      <p class="admin-alert admin-alert--<?= $flashKind === 'err' ? 'error' : 'ok' ?> admin-tickets-flash"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="get" action="<?= htmlspecialchars(url('admin/support_tickets.php'), ENT_QUOTES, 'UTF-8') ?>" class="admin-tickets-filters" role="search">
      <div class="admin-tickets-filters__search">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
        <input type="search" name="q" value="<?= htmlspecialchars($q, ENT_QUOTES, 'UTF-8') ?>" placeholder="Rechercher : sujet, entreprise, demandeur…" maxlength="120">
      </div>
      <div class="admin-tickets-filters__group">
        <input type="hidden" name="status" value="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>">
        <label>
          <span class="admin-tickets-filters__lab">Urgence</span>
          <select name="priority">
            <option value="all" <?= $filterPriority === 'all' ? 'selected' : '' ?>>Toutes</option>
            <?php foreach (support_ticket_priorities() as $slug => $label): ?>
              <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" <?= $filterPriority === $slug ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>
          <span class="admin-tickets-filters__lab">Type</span>
          <select name="category">
            <option value="all" <?= $filterCategory === 'all' ? 'selected' : '' ?>>Tous</option>
            <?php foreach (support_ticket_categories() as $slug => $label): ?>
              <option value="<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') ?>" <?= $filterCategory === $slug ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <button type="submit" class="btn-primary admin-tickets-filters__submit">Filtrer</button>
        <?php if ($q !== '' || $filterPriority !== 'all' || $filterCategory !== 'all'): ?>
          <a class="admin-tickets-filters__reset" href="<?= htmlspecialchars(url('admin/support_tickets.php' . ($filterStatus !== 'open' ? '?status=' . rawurlencode($filterStatus) : '')), ENT_QUOTES, 'UTF-8') ?>">Réinitialiser</a>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($tickets === []): ?>
      <div class="admin-board__empty">
        <div class="admin-board__empty-icon" aria-hidden="true">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <?php if ($filterStatus === 'closed'): ?>
          <h3>Aucun ticket résolu</h3>
          <p>Les tickets fermés apparaîtront ici une fois marqués comme résolus.</p>
        <?php elseif ($filterStatus === 'open'): ?>
          <h3>Aucun ticket en cours</h3>
          <p>Tout est traité ! Aucun ticket ouvert pour le moment.</p>
        <?php else: ?>
          <h3>Aucun ticket trouvé</h3>
          <p>Aucun ticket ne correspond aux filtres actifs.</p>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <ul class="admin-tickets-list" role="list">
        <?php foreach ($tickets as $t):
          $tid = (int) $t['id'];
          $cid = (int) $t['client_id'];
          $tStatus = (string) ($t['status'] ?? 'open');
          $tPri = (string) ($t['priority'] ?? 'normal');
          $tCat = (string) ($t['category'] ?? 'other');
          $priColor = $priMeta[$tPri]['color'] ?? '#64748b';
          $priLabel = $priMeta[$tPri]['label'] ?? ucfirst($tPri);
          $catLabel = support_ticket_category_label($tCat);
          $catIcon = $catMeta[$tCat]['icon'] ?? '';
          $tSubject = (string) ($t['subject'] ?? '');
          $tMessage = (string) ($t['message'] ?? '');
          $tCreated = (string) ($t['created_at'] ?? '');
          $tClosed = (string) ($t['closed_at'] ?? '');
          $isAssigned = !empty($t['assigned_admin_id']);
          $assignedEmail = (string) ($t['assigned_email'] ?? '');
          $company = (string) ($t['company_name'] ?? '—');
          $reqName = (string) ($t['requester_name'] ?? '');
          $reqEmail = (string) ($t['requester_email'] ?? '');
          $reqPhone = (string) ($t['requester_phone'] ?? '');
          $clientEmail = (string) ($t['client_email'] ?? '');
          $clientPhone = (string) ($t['client_phone'] ?? '');
          $att = support_ticket_decode_attachments($t['attachments_json'] ?? null);
          $clientUrl = url('admin/client_detail.php?id=' . $cid);
          $ticketUrl = url('admin/support_ticket_view.php?id=' . $tid);
          $returnQs = asu_filter_qs([]);
          $returnUrl = url('admin/support_tickets.php' . $returnQs);
        ?>
          <li class="admin-ticket admin-ticket--<?= $tStatus === 'closed' ? 'closed' : 'open' ?>"
              style="--ticket-pri: <?= htmlspecialchars($priColor, ENT_QUOTES, 'UTF-8') ?>;">
            <div class="admin-ticket__rail" aria-hidden="true"></div>
            <div class="admin-ticket__body">
              <header class="admin-ticket__head">
                <div class="admin-ticket__id-title">
                  <span class="admin-ticket__id">#<?= $tid ?></span>
                  <h3 class="admin-ticket__title">
                    <a class="admin-ticket__title-link" href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($tSubject, ENT_QUOTES, 'UTF-8') ?></a>
                  </h3>
                </div>
                <div class="admin-ticket__badges">
                  <span class="admin-ticket__pri" style="background: <?= htmlspecialchars($priColor, ENT_QUOTES, 'UTF-8') ?>;">
                    <?= htmlspecialchars($priLabel, ENT_QUOTES, 'UTF-8') ?>
                  </span>
                  <span class="admin-ticket__status admin-ticket__status--<?= $tStatus === 'closed' ? 'closed' : ($isAssigned ? 'progress' : 'open') ?>">
                    <?= $tStatus === 'closed' ? 'Résolu' : ($isAssigned ? 'En traitement' : 'Nouveau') ?>
                  </span>
                </div>
              </header>

              <div class="admin-ticket__meta">
                <a class="admin-ticket__company" href="<?= htmlspecialchars($clientUrl, ENT_QUOTES, 'UTF-8') ?>">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 21h18"/><path d="M5 21V7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v14"/><path d="M9 9h.01"/><path d="M9 13h.01"/><path d="M9 17h.01"/><path d="M15 9h.01"/><path d="M15 13h.01"/><path d="M15 17h.01"/></svg>
                  <strong><?= htmlspecialchars($company, ENT_QUOTES, 'UTF-8') ?></strong>
                </a>
                <span class="admin-ticket__cat">
                  <?= $catIcon ?>
                  <?= htmlspecialchars($catLabel, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($tCat === 'update'): ?>
                  <span class="admin-ticket__cat admin-ticket__cat--maint" title="Comptabilisé comme une intervention de maintenance">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-3.2 3.2-2.6-2.6 3.2-3.2z"/></svg>
                    Maintenance
                  </span>
                <?php endif; ?>
                <?php if ($att !== []): ?>
                  <span class="admin-ticket__cat admin-ticket__cat--att" title="Captures d'écran jointes par le client">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                    <?= count($att) ?> image<?= count($att) > 1 ? 's' : '' ?>
                  </span>
                <?php endif; ?>
                <span class="admin-ticket__date">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                  <?= htmlspecialchars(asu_format_dt($tCreated), ENT_QUOTES, 'UTF-8') ?>
                </span>
                <?php if ($tStatus === 'closed' && $tClosed !== ''): ?>
                  <span class="admin-ticket__date">Clôturé le <?= htmlspecialchars(asu_format_dt($tClosed), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
              </div>

              <p class="admin-ticket__msg"><?= nl2br(htmlspecialchars($tMessage, ENT_QUOTES, 'UTF-8')) ?></p>


              <footer class="admin-ticket__foot">
                <div class="admin-ticket__contacts">
                  <?php
                    $contactBits = [];
                    if ($reqName !== '') {
                        $contactBits[] = '<span class="admin-ticket__contact"><strong>' . htmlspecialchars($reqName, ENT_QUOTES, 'UTF-8') . '</strong></span>';
                    }
                    $mailToUse = $reqEmail !== '' ? $reqEmail : $clientEmail;
                    if ($mailToUse !== '') {
                        $contactBits[] = '<a class="admin-ticket__contact admin-ticket__contact--mail" href="mailto:'
                            . htmlspecialchars($mailToUse, ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars($mailToUse, ENT_QUOTES, 'UTF-8') . '</a>';
                    }
                    $phoneToUse = $reqPhone !== '' ? $reqPhone : $clientPhone;
                    if ($phoneToUse !== '') {
                        $telHref = 'tel:' . preg_replace('/[^\d+]/', '', $phoneToUse);
                        $contactBits[] = '<a class="admin-ticket__contact admin-ticket__contact--tel" href="' . htmlspecialchars($telHref, ENT_QUOTES, 'UTF-8') . '">'
                            . htmlspecialchars($phoneToUse, ENT_QUOTES, 'UTF-8') . '</a>';
                    }
                    echo implode(' · ', $contactBits);
                  ?>
                </div>
                <div class="admin-ticket__actions">
                  <a class="admin-ticket__btn admin-ticket__btn--pri" href="<?= htmlspecialchars($ticketUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    Ouvrir le ticket
                  </a>
                  <?php if ($isAssigned && $assignedEmail !== ''): ?>
                    <span class="admin-ticket__assignee" title="Pris en charge par">
                      <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                      <?= htmlspecialchars($assignedEmail, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  <?php endif; ?>
                  <form method="post" action="<?= htmlspecialchars(url('admin/support_tickets.php'), ENT_QUOTES, 'UTF-8') ?>" class="admin-ticket__form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ticket_id" value="<?= $tid ?>">
                    <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($tStatus === 'open'): ?>
                      <button type="submit" name="action" value="close" class="admin-ticket__btn admin-ticket__btn--done">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        Marquer résolu
                      </button>
                    <?php else: ?>
                      <button type="submit" name="action" value="reopen" class="admin-ticket__btn admin-ticket__btn--reopen">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg>
                        Rouvrir
                      </button>
                    <?php endif; ?>
                  </form>
                </div>
              </footer>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
