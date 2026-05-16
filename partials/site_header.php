<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/session.php';

/**
 * Indique si on est en train d'afficher la page d'accueil (index.php).
 * Si oui : les liens internes utilisent des ancres "#section".
 * Sinon : les liens pointent vers "index.php#section" pour fonctionner depuis n'importe où.
 *
 * La variable `$siteHeaderOnIndex` peut être définie AVANT l'inclusion pour forcer une valeur.
 */
$siteHeaderOnIndex = isset($siteHeaderOnIndex)
    ? (bool) $siteHeaderOnIndex
    : (bool) (($_SERVER['SCRIPT_NAME'] ?? '') !== ''
        && preg_match('#/index\.php$#', (string) $_SERVER['SCRIPT_NAME']));

$siteHeaderLink = static function (string $hash) use ($siteHeaderOnIndex): string {
    $hash = ltrim($hash, '#');
    return $siteHeaderOnIndex
        ? '#' . $hash
        : htmlspecialchars(url('index.php#' . $hash), ENT_QUOTES, 'UTF-8');
};
?>
<header id="navbar">
  <input type="checkbox" id="nav-menu-toggle" class="nav-menu-checkbox">
  <nav class="nav-container">
    <a href="<?= $siteHeaderLink('accueil') ?>" class="nav-logo" aria-label="Goo-Bridge accueil">
      <svg width="26" height="26" viewBox="0 0 28 28" fill="none" aria-hidden="true">
        <path d="M14 2L3 8V20L14 26L25 20V8L14 2Z" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
        <path d="M3 8L14 14L25 8" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
        <path d="M14 14V26" stroke="#16a34a" stroke-width="2" />
      </svg>
      <span>Goo<span class="logo-accent">Bridge</span></span>
    </a>
    <ul class="nav-links" role="list">
      <li><a href="<?= $siteHeaderLink('accueil') ?>">Accueil</a></li>
      <li><a href="<?= $siteHeaderLink('services') ?>">Services</a></li>
      <li><a href="<?= $siteHeaderLink('processus') ?>">Processus</a></li>
      <li><a href="<?= $siteHeaderLink('stack') ?>">Stack</a></li>
      <li><a href="<?= $siteHeaderLink('realisations') ?>">Réalisations</a></li>
    </ul>
    <?php if (admin_logged_in()): ?>
      <a href="<?= htmlspecialchars(url('admin/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-nav-login">Login</a>
    <?php else: ?>
      <a href="<?= htmlspecialchars(url('login.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-nav-login">Connexion</a>
    <?php endif; ?>
    <a href="<?= htmlspecialchars(url('support.php'), ENT_QUOTES, 'UTF-8') ?>" class="btn-nav-support">Support</a>
    <a href="<?= $siteHeaderLink('contact') ?>" class="btn-cta" id="nav-cta">Consultation gratuite</a>
    <label for="nav-menu-toggle" class="mobile-menu-btn" aria-label="Ouvrir ou fermer le menu">
      <span></span><span></span><span></span>
    </label>
  </nav>
  <label for="nav-menu-toggle" class="mobile-nav-backdrop" aria-hidden="true"></label>
  <div class="mobile-nav" id="mobileNav">
    <div class="mobile-nav__head">
      <a href="<?= $siteHeaderLink('accueil') ?>" class="mobile-nav__brand" aria-label="Goo-Bridge accueil">
        <svg width="24" height="24" viewBox="0 0 28 28" fill="none" aria-hidden="true">
          <path d="M14 2L3 8V20L14 26L25 20V8L14 2Z" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
          <path d="M3 8L14 14L25 8" stroke="#16a34a" stroke-width="2" stroke-linejoin="round" />
          <path d="M14 14V26" stroke="#16a34a" stroke-width="2" />
        </svg>
        <span>Goo<span class="logo-accent">Bridge</span></span>
      </a>
      <label for="nav-menu-toggle" class="mobile-nav__close" aria-label="Fermer le menu">×</label>
    </div>
    <p class="mobile-nav__lead">Navigation rapide</p>
    <a href="<?= $siteHeaderLink('accueil') ?>">Accueil</a>
    <a href="<?= $siteHeaderLink('services') ?>">Services</a>
    <a href="<?= $siteHeaderLink('processus') ?>">Processus</a>
    <a href="<?= $siteHeaderLink('stack') ?>">Stack</a>
    <a href="<?= $siteHeaderLink('realisations') ?>">Réalisations</a>
    <a href="<?= htmlspecialchars(url('support.php'), ENT_QUOTES, 'UTF-8') ?>">Support</a>
    <a href="<?= $siteHeaderLink('contact') ?>">Contact</a>
    <?php if (admin_logged_in()): ?>
      <a href="<?= htmlspecialchars(url('admin/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav__action">Tableau de bord</a>
      <a href="<?= htmlspecialchars(url('logout.php'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav__ghost">Déconnexion</a>
    <?php else: ?>
      <a href="<?= htmlspecialchars(url('login.php'), ENT_QUOTES, 'UTF-8') ?>" class="mobile-nav__action">Connexion</a>
    <?php endif; ?>
  </div>
</header>
