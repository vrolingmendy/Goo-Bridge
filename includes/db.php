<?php

declare(strict_types=1);

require_once __DIR__ . '/paths.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $c = require __DIR__ . '/../config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $c['host'],
        $c['dbname'],
        $c['charset']
    );
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    ensure_database_schema($pdo);

    return $pdo;
}

function ensure_database_schema(PDO $pdo): void
{
    $adminsExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins'"
    );
    $adminsExists->execute();
    if ((int) $adminsExists->fetchColumn() === 0) {
        return;
    }

    $firstNameCol = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'first_name'"
    );
    $firstNameCol->execute();
    if ((int) $firstNameCol->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN first_name VARCHAR(128) NULL DEFAULT NULL AFTER email'
        );
    }

    $lastNameCol = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'last_name'"
    );
    $lastNameCol->execute();
    if ((int) $lastNameCol->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN last_name VARCHAR(128) NULL DEFAULT NULL AFTER first_name'
        );
    }

    $superColumnExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'is_super_admin'"
    );
    $superColumnExists->execute();
    if ((int) $superColumnExists->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN is_super_admin TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash'
        );
    }

    $failedCol = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'failed_login_attempts'"
    );
    $failedCol->execute();
    if ((int) $failedCol->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_super_admin'
        );
    }

    $lockedCol = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'locked_until'"
    );
    $lockedCol->execute();
    if ((int) $lockedCol->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN locked_until DATETIME NULL DEFAULT NULL AFTER failed_login_attempts'
        );
    }

    $superCount = (int) $pdo->query('SELECT COUNT(*) FROM admins WHERE is_super_admin = 1')->fetchColumn();
    if ($superCount > 0) {
        return;
    }

    $firstAdminId = (int) $pdo->query('SELECT MIN(id) FROM admins')->fetchColumn();
    if ($firstAdminId > 0) {
        $stmt = $pdo->prepare('UPDATE admins SET is_super_admin = 1 WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $firstAdminId]);
    }
}

/**
 * Message utilisateur pour les échecs PDO (connexion vs schéma obsolète).
 */
function database_user_error_message(Throwable $e): string
{
    if (!$e instanceof PDOException) {
        return 'Une erreur technique est survenue. Réessayez dans quelques instants.';
    }

    $msg = $e->getMessage();
    if (stripos($msg, 'Unknown column') !== false
        || stripos($msg, '42S22') !== false
        || stripos($msg, '42S02') !== false
        || (stripos($msg, "doesn't exist") !== false && stripos($msg, 'Table') !== false)) {
        return 'La structure de la base de données est incomplète ou obsolète. Ouvrez install.php dans votre navigateur pour mettre à jour les tables.';
    }

    return 'Impossible de se connecter à la base de données. Vérifiez que MySQL est démarré, les paramètres dans config/database.php, et exécutez install.php si la base ou les tables n’existent pas encore.';
}
