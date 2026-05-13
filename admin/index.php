<?php

declare(strict_types=1);

/**
 * Point d'entrée /admin/ : envoie vers le dashboard administrateur.
 */
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/paths.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

header('Location: ' . url('admin/dashboard.php'), true, 302);
exit;
