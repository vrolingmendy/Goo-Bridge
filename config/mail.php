<?php

declare(strict_types=1);

/**
 * Configuration mail.
 *
 * Priorité :
 * 1. Variables d'environnement du serveur (recommandé en production).
 * 2. config/mail.local.php si présent (pratique en local ou hébergement mutualisé).
 * 3. config/mail.example.php avec envoi désactivé.
 */
$env = static function (string $key, ?string $default = null): ?string {
    $value = getenv($key);

    if ($value === false || $value === '') {
        return $default;
    }

    return $value;
};

$envEnabled = filter_var($env('MAIL_ENABLED', 'false'), FILTER_VALIDATE_BOOLEAN);
$envPassword = $env('SMTP_PASSWORD');
$envUsername = $env('SMTP_USERNAME');

if ($envEnabled || $envPassword !== null || $envUsername !== null) {
    $username = $envUsername ?? '';

    return [
        'enabled' => $envEnabled,
        'host' => $env('SMTP_HOST', 'goo-bridge.com'),
        'port' => (int) $env('SMTP_PORT', '465'),
        /** ssl pour le port 465 (SMTPS), tls pour le port 587 */
        'encryption' => $env('SMTP_ENCRYPTION', 'ssl'),
        'username' => $username,
        'password' => $envPassword ?? '',
        'from_email' => $env('MAIL_FROM_EMAIL', $username !== '' ? $username : 'contact@goo-bridge.com'),
        'from_name' => $env('MAIL_FROM_NAME', 'Goo-Bridge — formulaire'),
        'smtp_hostname' => $env('SMTP_HOSTNAME', 'goo-bridge.com'),
        'to_email' => $env('MAIL_TO_EMAIL', 'contact@goo-bridge.com'),
        'to_name' => $env('MAIL_TO_NAME', 'Goo-Bridge Contact'),
    ];
}

$local = __DIR__ . '/mail.local.php';

return is_readable($local) ? require $local : require __DIR__ . '/mail.example.php';
