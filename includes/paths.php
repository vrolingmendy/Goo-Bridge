<?php

declare(strict_types=1);

if (!defined('BASE_URL')) {
    $resolved = null;
    $cf = __DIR__ . '/../config/app.php';
    if (is_file($cf)) {
        /** @var array{base_url?: string|null, public_origin?: string|null} $cfg */
        $cfg = require $cf;
        if (array_key_exists('base_url', $cfg)) {
            $resolved = $cfg['base_url'];
        }
    }

    if ($resolved === null) {
        $doc = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $root = realpath(__DIR__ . '/..');
        if ($doc && $root && str_starts_with($root, $doc)) {
            $resolved = str_replace('\\', '/', substr($root, strlen($doc)));
        } else {
            $resolved = '';
        }
    }

    if (!is_string($resolved)) {
        $resolved = '';
    }
    $resolved = trim($resolved);
    if ($resolved !== '' && $resolved[0] !== '/') {
        $resolved = '/' . $resolved;
    }

    define('BASE_URL', $resolved);
}

function url(string $path = ''): string
{
    $path = ltrim(str_replace('\\', '/', $path), '/');
    return BASE_URL === '' ? '/' . $path : BASE_URL . '/' . $path;
}

/**
 * Origine du site pour les URLs absolues (e-mails, liens signés, etc.).
 * Si `public_origin` est défini dans config/app.php, il est utilisé (ex. https://goo-bridge.com).
 * Sinon : schéma + HTTP_HOST de la requête courante.
 */
function public_site_origin(): string
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cf = __DIR__ . '/../config/app.php';
    if (is_file($cf)) {
        /** @var array{public_origin?: string|null} $cfg */
        $cfg = require $cf;
        $po = isset($cfg['public_origin']) ? trim((string) $cfg['public_origin']) : '';
        if ($po !== '') {
            $cached = rtrim($po, '/');

            return $cached;
        }
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'goo-bridge.com';
    $cached = $scheme . '://' . $host;

    return $cached;
}

/** URL absolue (http/https + hôte) pour liens partageables (ex. validation maintenance). */
function absolute_url_from_path(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', $relativePath);
    if ($relativePath === '' || $relativePath[0] !== '/') {
        $relativePath = '/' . ltrim($relativePath, '/');
    }

    return public_site_origin() . $relativePath;
}

function maintenance_sign_absolute_url(string $publicToken): string
{
    return absolute_url_from_path(url('maintenance_sign.php?t=' . rawurlencode($publicToken)));
}

function client_support_portal_absolute_url(string $ticketPortalToken): string
{
    return absolute_url_from_path(url('client_support.php?t=' . rawurlencode($ticketPortalToken)));
}
