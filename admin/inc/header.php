<?php

declare(strict_types=1);

/** @var string $pageTitle */
/** @var string $activeNav */

require_once __DIR__ . '/../../includes/paths.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= htmlspecialchars(url('favicon.svg'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(url('style.css'), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="stylesheet" href="<?= htmlspecialchars(url('admin/admin.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="admin-body">
  <header class="admin-shell-header">
    <div class="admin-shell-inner">
      <a href="<?= htmlspecialchars(url('admin/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="admin-shell-brand">Goo<span>-Bridge</span> Admin</a>
      <input type="checkbox" id="admin-shell-nav-toggle" class="admin-shell-nav-toggle">
      <label for="admin-shell-nav-toggle" class="admin-shell-nav-trigger" aria-label="Ouvrir ou fermer le menu">
        <span></span>
        <span></span>
        <span></span>
      </label>
      <nav class="admin-shell-nav" aria-label="Navigation admin">
        <?php if (function_exists('current_admin_is_super') && current_admin_is_super()): ?>
          <?php
            $navUsersActive = isset($activeNav) && in_array($activeNav, ['admins', 'admins_create', 'admins_edit'], true);
          ?>
          <a href="<?= htmlspecialchars(url('admin/admins.php'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $navUsersActive ? 'is-active' : '' ?>">Utilisateurs</a>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(url('admin/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $activeNav === 'dashboard' ? 'is-active' : '' ?>">Tableau de bord</a>
        <a href="<?= htmlspecialchars(url('admin/clients.php'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $activeNav === 'clients' ? 'is-active' : '' ?>">Clients &amp; entreprises</a>
        <a href="<?= htmlspecialchars(url('admin/support_tickets.php'), ENT_QUOTES, 'UTF-8') ?>" class="<?= $activeNav === 'tickets' ? 'is-active' : '' ?>">Tickets</a>
        <a href="<?= htmlspecialchars(url('index.php'), ENT_QUOTES, 'UTF-8') ?>">Site public</a>
        <a href="<?= htmlspecialchars(url('logout.php'), ENT_QUOTES, 'UTF-8') ?>">Déconnexion</a>
      </nav>
    </div>
  </header>
  <main class="admin-main">
