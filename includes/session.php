<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    // Durée de vie côté serveur et cookie (30 jours) — rester connecté sans expiration courte.
    $lifetime = 60 * 60 * 24 * 30;
    ini_set('session.gc_maxlifetime', (string) $lifetime);

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => $lifetime,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
