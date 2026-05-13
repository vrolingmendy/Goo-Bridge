<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/paths.php';

/** Normalise l’email (trim + casse) pour éviter les échecs de connexion à cause d’espaces ou majuscules. */
function normalize_admin_email(string $email): string
{
    $email = trim($email);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($email, 'UTF-8');
    }

    return strtolower($email);
}

function admin_logged_in(): bool
{
    return isset($_SESSION['admin_id']);
}

function current_admin_id(): ?int
{
    if (!isset($_SESSION['admin_id'])) {
        return null;
    }

    return (int) $_SESSION['admin_id'];
}

function current_admin_email(): ?string
{
    return isset($_SESSION['admin_email']) ? (string) $_SESSION['admin_email'] : null;
}

function current_admin_is_super(): bool
{
    return !empty($_SESSION['admin_is_super']);
}

/** Complète la session (anciennes connexions sans indicateur super-admin). */
function sync_admin_session_role(): void
{
    if (!isset($_SESSION['admin_id'])) {
        return;
    }
    if (!empty($_SESSION['admin_role_synced'])) {
        return;
    }
    require_once __DIR__ . '/db.php';
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT COALESCE(is_super_admin, 0) FROM admins WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $_SESSION['admin_id']]);
        $raw = $stmt->fetchColumn();
        $_SESSION['admin_is_super'] = ((int) $raw) === 1;
    } catch (Throwable $e) {
        $_SESSION['admin_is_super'] = false;
    }
    $_SESSION['admin_role_synced'] = true;
}

function login_admin(int $id, string $email, bool $isSuperAdmin = false): void
{
    $_SESSION['admin_id'] = $id;
    $_SESSION['admin_email'] = $email;
    $_SESSION['admin_is_super'] = $isSuperAdmin;
    $_SESSION['admin_role_synced'] = true;
    session_regenerate_id(true);
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function require_admin(): void
{
    if (!admin_logged_in()) {
        header('Location: ' . url('login.php'), true, 302);
        exit;
    }
    sync_admin_session_role();
}

function require_super_admin(): void
{
    require_admin();
    if (!current_admin_is_super()) {
        header('Location: ' . url('admin/dashboard.php'), true, 302);
        exit;
    }
}
