<?php

declare(strict_types=1);

if (!defined('BASE_URL')) {
    $resolved = null;
    $cf = __DIR__ . '/../config/app.php';
    if (is_file($cf)) {
        /** @var array{base_url?: string|null} $cfg */
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

/** URL absolue (http/https + hôte) pour liens partageables (ex. validation maintenance). */
function absolute_url_from_path(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', $relativePath);
    if ($relativePath === '' || $relativePath[0] !== '/') {
        $relativePath = '/' . ltrim($relativePath, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443');
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host . $relativePath;
}

function maintenance_sign_absolute_url(string $publicToken): string
{
    return absolute_url_from_path(url('maintenance_sign.php?t=' . rawurlencode($publicToken)));
}
