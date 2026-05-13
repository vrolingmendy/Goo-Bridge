<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/auth.php';

logout_admin();

header('Location: ' . url('index.php'), true, 302);
exit;
