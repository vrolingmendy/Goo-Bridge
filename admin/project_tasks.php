<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/project_mail.php';

require_admin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . url('admin/clients.php'), true, 302);
    exit;
}

$pdo = db();

$client = (function () use ($pdo, $id): ?array {
    $st = $pdo->prepare('SELECT * FROM clients WHERE id = :id LIMIT 1');
    $st->execute(['id' => $id]);
    $r = $st->fetch();

    return $r !== false ? $r : null;
})();

if ($client === null) {
    header('Location: ' . url('admin/clients.php'), true, 302);
    exit;
}

function project_tasks_format_fr_datetime(?string $sqlTs): string
{
    if ($sqlTs === null || $sqlTs === '') {
        return '—';
    }
    $ts = strtotime($sqlTs);

    return $ts !== false ? date('d/m/Y à H:i', $ts) : htmlspecialchars($sqlTs, ENT_QUOTES, 'UTF-8');
}

function project_tasks_format_fr_date(?string $sqlDate): string
{
    if ($sqlDate === null || $sqlDate === '') {
        return '—';
    }
    $ts = strtotime($sqlDate);

    return $ts !== false ? date('d/m/Y', $ts) : htmlspecialchars($sqlDate, ENT_QUOTES, 'UTF-8');
}

$flash = isset($_GET['flash']) ? (string) $_GET['flash'] : '';
$flashMsg = '';
$flashOk = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify($_POST['_csrf'] ?? null)) {
        header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=csrf'), true, 302);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'add') {
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $dueRaw = trim((string) ($_POST['due_date'] ?? ''));
            $notify = isset($_POST['notify_email']) ? 1 : 0;

            if ($title === '' || mb_strlen($title) > 255) {
                header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=bad_title'), true, 302);
                exit;
            }

            $dueValue = null;
            if ($dueRaw !== '') {
                $dt = DateTimeImmutable::createFromFormat('Y-m-d', $dueRaw);
                if ($dt === false || $dt->format('Y-m-d') !== $dueRaw) {
                    header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=bad_date'), true, 302);
                    exit;
                }
                $dueValue = $dueRaw;
            }

            $ins = $pdo->prepare(
                'INSERT INTO client_project_tasks (client_id, title, description, due_date, notify_email, created_by)
                 VALUES (:cid, :title, :desc, :due, :notify, :cby)'
            );
            $ins->execute([
                'cid' => $id,
                'title' => $title,
                'desc' => $description !== '' ? $description : null,
                'due' => $dueValue,
                'notify' => $notify,
                'cby' => current_admin_id(),
            ]);

            header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=added'), true, 302);
            exit;
        }

        if ($action === 'complete') {
            $tid = (int) ($_POST['task_id'] ?? 0);
            if ($tid <= 0) {
                header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=bad_id'), true, 302);
                exit;
            }

            $st = $pdo->prepare('SELECT * FROM client_project_tasks WHERE id = :tid AND client_id = :cid LIMIT 1');
            $st->execute(['tid' => $tid, 'cid' => $id]);
            $task = $st->fetch();
            if ($task === false) {
                header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=bad_id'), true, 302);
                exit;
            }

            if ((string) $task['status'] === 'done') {
                header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=already'), true, 302);
                exit;
            }

            $now = date('Y-m-d H:i:s');
            $up = $pdo->prepare(
                'UPDATE client_project_tasks
                 SET status = \'done\', completed_at = :ca
                 WHERE id = :tid AND client_id = :cid LIMIT 1'
            );
            $up->execute([
                'ca' => $now,
                'tid' => $tid,
                'cid' => $id,
            ]);

            $task['status'] = 'done';
            $task['completed_at'] = $now;

            $flashKey = 'completed';
            if ((int) $task['notify_email'] === 1) {
                $res = send_project_task_completion_mail(
                    [
                        'company_name' => (string) $client['company_name'],
                        'contact_name' => isset($client['contact_name']) ? (string) $client['contact_name'] : null,
                        'email' => (string) ($client['email'] ?? ''),
                        'project_type' => isset($client['project_type']) ? (string) $client['project_type'] : null,
                    ],
                    [
                        'title' => (string) $task['title'],
                        'description' => isset($task['description']) ? (string) $task['description'] : null,
                        'completed_at' => (string) $task['completed_at'],
                    ]
                );

                if ($res['ok']) {
                    $markSent = $pdo->prepare(
                        'UPDATE client_project_tasks SET notification_sent = 1, notification_sent_at = :sa WHERE id = :tid LIMIT 1'
                    );
                    $markSent->execute(['sa' => date('Y-m-d H:i:s'), 'tid' => $tid]);
                    $flashKey = 'completed_mailed';
                } else {
                    $flashKey = 'completed_no_mail';
                }
            }

            header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=' . $flashKey), true, 302);
            exit;
        }

        if ($action === 'reopen') {
            $tid = (int) ($_POST['task_id'] ?? 0);
            if ($tid > 0) {
                $up = $pdo->prepare(
                    'UPDATE client_project_tasks
                     SET status = \'pending\', completed_at = NULL
                     WHERE id = :tid AND client_id = :cid LIMIT 1'
                );
                $up->execute(['tid' => $tid, 'cid' => $id]);
            }
            header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=reopened'), true, 302);
            exit;
        }

        if ($action === 'delete') {
            $tid = (int) ($_POST['task_id'] ?? 0);
            if ($tid > 0) {
                $del = $pdo->prepare(
                    'DELETE FROM client_project_tasks WHERE id = :tid AND client_id = :cid LIMIT 1'
                );
                $del->execute(['tid' => $tid, 'cid' => $id]);
            }
            header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=deleted'), true, 302);
            exit;
        }
    } catch (Throwable $e) {
        error_log('project_tasks: ' . $e->getMessage());
        header('Location: ' . url('admin/project_tasks.php?id=' . $id . '&flash=error'), true, 302);
        exit;
    }

    header('Location: ' . url('admin/project_tasks.php?id=' . $id), true, 302);
    exit;
}

if ($flash === 'csrf') {
    $flashMsg = 'Session invalide — rechargez la page.';
} elseif ($flash === 'bad_title') {
    $flashMsg = 'Titre de la tâche manquant ou trop long.';
} elseif ($flash === 'bad_date') {
    $flashMsg = 'Date d\'échéance invalide.';
} elseif ($flash === 'bad_id') {
    $flashMsg = 'Tâche introuvable.';
} elseif ($flash === 'already') {
    $flashMsg = 'Cette tâche est déjà marquée comme effectuée.';
} elseif ($flash === 'error') {
    $flashMsg = 'Une erreur est survenue.';
} elseif ($flash === 'added') {
    $flashMsg = 'Tâche ajoutée.';
    $flashOk = true;
} elseif ($flash === 'completed') {
    $flashMsg = 'Tâche marquée comme effectuée.';
    $flashOk = true;
} elseif ($flash === 'completed_mailed') {
    $flashMsg = 'Tâche effectuée — notification envoyée au contact.';
    $flashOk = true;
} elseif ($flash === 'completed_no_mail') {
    $flashMsg = 'Tâche effectuée — l\'email n\'a pas pu être envoyé (vérifiez la config SMTP).';
} elseif ($flash === 'reopened') {
    $flashMsg = 'Tâche rouverte.';
    $flashOk = true;
} elseif ($flash === 'deleted') {
    $flashMsg = 'Tâche supprimée.';
    $flashOk = true;
}

$tasksStmt = $pdo->prepare(
    'SELECT * FROM client_project_tasks
     WHERE client_id = :cid
     ORDER BY (status = \'done\') ASC, COALESCE(due_date, \'9999-12-31\') ASC, created_at DESC, id DESC'
);
$tasksStmt->execute(['cid' => $id]);
$tasks = $tasksStmt->fetchAll();

$totalTasks = count($tasks);
$doneTasks = 0;
$pendingTasks = 0;
$overdueTasks = 0;
$todayKey = date('Y-m-d');
foreach ($tasks as $t) {
    if ((string) $t['status'] === 'done') {
        $doneTasks++;
    } else {
        $pendingTasks++;
        $due = (string) ($t['due_date'] ?? '');
        if ($due !== '' && $due < $todayKey) {
            $overdueTasks++;
        }
    }
}
$progressPct = $totalTasks > 0 ? (int) round(($doneTasks / $totalTasks) * 100) : 0;

$contactEmail = trim((string) ($client['email'] ?? ''));
$contactName = trim((string) ($client['contact_name'] ?? ''));
$companyName = (string) $client['company_name'];
$projectType = trim((string) ($client['project_type'] ?? ''));

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$pageTitle = 'Gestion de projet — ' . $companyName;
$activeNav = 'clients';

require __DIR__ . '/inc/header.php';
?>

<div class="admin-detail-page admin-project-page">
  <nav class="admin-detail-breadcrumb" aria-label="Navigation">
    <a href="<?= htmlspecialchars(url('admin/clients.php'), ENT_QUOTES, 'UTF-8') ?>">← Liste des clients</a>
    <span class="admin-detail-breadcrumb__sep" aria-hidden="true">·</span>
    <a href="<?= htmlspecialchars(url('admin/client_detail.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Fiche client</a>
  </nav>

  <?php if ($flashMsg !== ''): ?>
    <p class="admin-alert <?= $flashOk ? 'admin-alert--ok' : 'admin-alert--error' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></p>
  <?php endif; ?>

  <header class="admin-detail-hero admin-project-hero">
    <div class="admin-detail-hero__intro">
      <span class="admin-project-hero__eyebrow">Gestion de projet</span>
      <h1 class="admin-detail-hero__title"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></h1>
      <p class="admin-detail-hero__tagline"><?= $projectType !== '' ? htmlspecialchars($projectType, ENT_QUOTES, 'UTF-8') : 'Type de projet non renseigné' ?></p>
    </div>
    <div class="admin-detail-hero__toolbar">
      <div class="admin-project-hero__progress" aria-label="Avancement du projet">
        <div class="admin-project-hero__progress-top">
          <span class="admin-project-hero__progress-num"><?= $progressPct ?>%</span>
          <span class="admin-project-hero__progress-label"><?= $doneTasks ?> / <?= $totalTasks ?> tâches</span>
        </div>
        <div class="admin-project-hero__progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progressPct ?>">
          <span class="admin-project-hero__progress-fill" style="width: <?= $progressPct ?>%"></span>
        </div>
      </div>
      <div class="admin-detail-hero__actions">
        <a class="btn-primary admin-detail-hero__btn-pri" href="<?= htmlspecialchars(url('admin/client_detail.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">← Retour à la fiche</a>
      </div>
    </div>
  </header>

  <section class="admin-panel admin-detail-panel admin-project-stats">
    <ul class="admin-project-stats__list" role="list">
      <li class="admin-project-stats__item admin-project-stats__item--pending">
        <span class="admin-project-stats__num"><?= $pendingTasks ?></span>
        <span class="admin-project-stats__label">À faire</span>
      </li>
      <li class="admin-project-stats__item admin-project-stats__item--done">
        <span class="admin-project-stats__num"><?= $doneTasks ?></span>
        <span class="admin-project-stats__label">Effectuée<?= $doneTasks !== 1 ? 's' : '' ?></span>
      </li>
      <li class="admin-project-stats__item admin-project-stats__item--overdue<?= $overdueTasks > 0 ? ' is-alert' : '' ?>">
        <span class="admin-project-stats__num"><?= $overdueTasks ?></span>
        <span class="admin-project-stats__label">En retard</span>
      </li>
      <li class="admin-project-stats__item admin-project-stats__item--contact">
        <span class="admin-project-stats__label">Contact notifié</span>
        <?php if ($contactEmail !== ''): ?>
          <span class="admin-project-stats__value"><?= htmlspecialchars($contactName !== '' ? $contactName : $contactEmail, ENT_QUOTES, 'UTF-8') ?></span>
          <span class="admin-project-stats__sub"><?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></span>
        <?php else: ?>
          <span class="admin-project-stats__value admin-project-stats__value--muted">Email non renseigné</span>
          <a class="admin-project-stats__edit" href="<?= htmlspecialchars(url('admin/clients_edit.php?id=' . $id), ENT_QUOTES, 'UTF-8') ?>">Compléter la fiche →</a>
        <?php endif; ?>
      </li>
    </ul>
  </section>

  <section class="admin-panel admin-detail-panel admin-project-form-panel">
    <div class="admin-detail-panel__intro">
      <h2 class="admin-detail-panel__title">Ajouter une tâche</h2>
      <p class="admin-detail-panel__hint">Découpez le projet en étapes claires. Cochez la case pour notifier automatiquement le contact à la complétion.</p>
    </div>
    <form method="post" class="admin-form admin-project-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add">
      <div class="admin-grid-2">
        <div class="admin-field admin-project-form__title">
          <label for="task-title">Titre de la tâche *</label>
          <input type="text" id="task-title" name="title" required maxlength="255" placeholder="Ex. Intégration page panier, mise à jour CMS, refonte logo…">
        </div>
        <div class="admin-field">
          <label for="task-due">Échéance (optionnel)</label>
          <input type="date" id="task-due" name="due_date" min="<?= htmlspecialchars($today, ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
      <div class="admin-field">
        <label for="task-description">Détails / livrables attendus</label>
        <textarea id="task-description" name="description" placeholder="Précisez les éléments concernés, les contraintes, les livrables attendus…"></textarea>
      </div>
      <label class="admin-project-form__toggle">
        <input type="checkbox" name="notify_email" value="1" checked<?= $contactEmail === '' ? ' disabled' : '' ?>>
        <span class="admin-project-form__toggle-box" aria-hidden="true"></span>
        <span class="admin-project-form__toggle-text">
          <strong>Notifier le contact à la complétion</strong>
          <small><?= $contactEmail !== '' ? 'Un email de confirmation sera envoyé à ' . htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') . ' lorsque la tâche sera marquée comme effectuée.' : 'Renseignez d\'abord un email dans la fiche client pour activer la notification.' ?></small>
        </span>
      </label>
      <button type="submit" class="btn-primary admin-submit admin-project-form__submit">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M12 5v14" />
          <path d="M5 12h14" />
        </svg>
        Ajouter la tâche
      </button>
    </form>
  </section>

  <section class="admin-panel admin-detail-panel admin-project-list-panel">
    <div class="admin-detail-panel__intro">
      <h2 class="admin-detail-panel__title">Tâches du projet</h2>
      <span class="admin-detail-panel__count"><?= $totalTasks ?> entrée<?= $totalTasks !== 1 ? 's' : '' ?></span>
    </div>

    <?php if ($tasks === []): ?>
      <div class="admin-project-empty">
        <div class="admin-project-empty__icon" aria-hidden="true">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 11l3 3L22 4" />
            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11" />
          </svg>
        </div>
        <h3>Aucune tâche enregistrée</h3>
        <p>Ajoutez la première étape du projet via le formulaire ci-dessus.</p>
      </div>
    <?php else: ?>
      <ul class="admin-project-tasks" role="list">
        <?php foreach ($tasks as $t): ?>
          <?php
            $isDone = (string) $t['status'] === 'done';
            $due = (string) ($t['due_date'] ?? '');
            $isOverdue = !$isDone && $due !== '' && $due < $todayKey;
            $tClasses = 'admin-project-task';
            if ($isDone) {
                $tClasses .= ' admin-project-task--done';
            } elseif ($isOverdue) {
                $tClasses .= ' admin-project-task--overdue';
            }
          ?>
          <li class="<?= $tClasses ?>">
            <div class="admin-project-task__main">
              <div class="admin-project-task__head">
                <span class="admin-project-task__status-dot" aria-hidden="true"></span>
                <h3 class="admin-project-task__title"><?= htmlspecialchars((string) $t['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php if ($isDone): ?>
                  <span class="admin-project-task__badge admin-project-task__badge--done">Effectuée</span>
                <?php elseif ($isOverdue): ?>
                  <span class="admin-project-task__badge admin-project-task__badge--overdue">En retard</span>
                <?php else: ?>
                  <span class="admin-project-task__badge admin-project-task__badge--pending">À faire</span>
                <?php endif; ?>
              </div>

              <ul class="admin-project-task__meta" role="list">
                <?php if ($due !== ''): ?>
                  <li>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <rect x="3" y="4" width="18" height="18" rx="2" />
                      <path d="M16 2v4" />
                      <path d="M8 2v4" />
                      <path d="M3 10h18" />
                    </svg>
                    <span>Échéance · <?= project_tasks_format_fr_date($due) ?></span>
                  </li>
                <?php endif; ?>
                <?php if ($isDone): ?>
                  <li>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                    <span>Effectuée · <?= project_tasks_format_fr_datetime((string) $t['completed_at']) ?></span>
                  </li>
                  <?php if ((int) $t['notification_sent'] === 1): ?>
                    <li class="admin-project-task__meta-mail">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="2" y="4" width="20" height="16" rx="2" />
                        <path d="m22 7-10 6L2 7" />
                      </svg>
                      <span>Contact notifié · <?= project_tasks_format_fr_datetime((string) ($t['notification_sent_at'] ?? '')) ?></span>
                    </li>
                  <?php elseif ((int) $t['notify_email'] === 1): ?>
                    <li class="admin-project-task__meta-warn">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
                        <path d="M12 9v4" />
                        <path d="M12 17h.01" />
                      </svg>
                      <span>Email non envoyé (vérifiez la config SMTP)</span>
                    </li>
                  <?php endif; ?>
                <?php elseif ((int) $t['notify_email'] === 1 && $contactEmail !== ''): ?>
                  <li class="admin-project-task__meta-mail">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <rect x="2" y="4" width="20" height="16" rx="2" />
                      <path d="m22 7-10 6L2 7" />
                    </svg>
                    <span>Notification activée pour <?= htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8') ?></span>
                  </li>
                <?php endif; ?>
              </ul>

              <?php if (!empty($t['description'])): ?>
                <div class="admin-project-task__desc"><?= nl2br(htmlspecialchars((string) $t['description'], ENT_QUOTES, 'UTF-8')) ?></div>
              <?php endif; ?>
            </div>

            <div class="admin-project-task__actions">
              <?php if (!$isDone): ?>
                <form method="post" class="admin-inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="complete">
                  <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" class="admin-project-task__btn admin-project-task__btn--complete" title="Marquer effectuée<?= (int) $t['notify_email'] === 1 && $contactEmail !== '' ? ' — un email sera envoyé au contact' : '' ?>" onclick="return confirm(<?= (int) $t['notify_email'] === 1 && $contactEmail !== '' ? "'Marquer cette tâche effectuée ? Un email sera envoyé à ".htmlspecialchars(addslashes($contactEmail), ENT_QUOTES, 'UTF-8').".'" : "'Marquer cette tâche comme effectuée ?'" ?>);">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="20 6 9 17 4 12" />
                    </svg>
                    Effectuer
                  </button>
                </form>
              <?php else: ?>
                <form method="post" class="admin-inline-form">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="reopen">
                  <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                  <button type="submit" class="admin-project-task__btn admin-project-task__btn--reopen" title="Rouvrir cette tâche">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <path d="M3 12a9 9 0 1 0 3-6.7L3 8" />
                      <path d="M3 3v5h5" />
                    </svg>
                    Rouvrir
                  </button>
                </form>
              <?php endif; ?>
              <form method="post" class="admin-inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="task_id" value="<?= (int) $t['id'] ?>">
                <button type="submit" class="admin-project-task__btn admin-project-task__btn--danger" title="Supprimer cette tâche" onclick="return confirm('Supprimer cette tâche ?');">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 6h18" />
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2" />
                  </svg>
                </button>
              </form>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
