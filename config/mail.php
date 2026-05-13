<?php

declare(strict_types=1);

/**
 * Charge mail.local.php si présent, sinon valeurs d’exemple (envoi désactivé).
 */
$local = __DIR__ . '/mail.local.php';

return is_readable($local) ? require $local : require __DIR__ . '/mail.example.php';
