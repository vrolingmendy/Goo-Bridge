<?php

declare(strict_types=1);

/**
 * À exécuter une fois après avoir créé la base `goo_bridge` dans phpMyAdmin.
 * Crée les tables et le compte administrateur initial (sans formulaire d'inscription).
 * Supprimez ou renommez ce fichier après installation en production.
 */

require_once __DIR__ . '/includes/db.php';

header('Content-Type: text/html; charset=UTF-8');

$adminEmail = getenv('INSTALL_ADMIN_EMAIL') ?: 'vrolingmendy0@gmail.com';
$adminPasswordPlain = getenv('INSTALL_ADMIN_PASSWORD') ?: bin2hex(random_bytes(9));

$messages = [];
$ok = true;

try {
    $pdo = db();

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  first_name VARCHAR(128) NULL DEFAULT NULL,
  last_name VARCHAR(128) NULL DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  is_super_admin TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $colFirstName = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'first_name'"
    );
    $colFirstName->execute();
    if ((int) $colFirstName->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN first_name VARCHAR(128) NULL DEFAULT NULL AFTER email'
        );
    }

    $colLastName = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'last_name'"
    );
    $colLastName->execute();
    if ((int) $colLastName->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN last_name VARCHAR(128) NULL DEFAULT NULL AFTER first_name'
        );
    }

    $colSuperAdmin = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'is_super_admin'"
    );
    $colSuperAdmin->execute();
    if ((int) $colSuperAdmin->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN is_super_admin TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER password_hash'
        );
    }

    $colFailed = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'failed_login_attempts'"
    );
    $colFailed->execute();
    if ((int) $colFailed->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0 AFTER is_super_admin'
        );
    }

    $colLocked = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'admins' AND COLUMN_NAME = 'locked_until'"
    );
    $colLocked->execute();
    if ((int) $colLocked->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE admins ADD COLUMN locked_until DATETIME NULL DEFAULT NULL AFTER failed_login_attempts'
        );
    }

    $superCount = (int) $pdo->query('SELECT COUNT(*) FROM admins WHERE is_super_admin = 1')->fetchColumn();
    if ($superCount === 0) {
        $minId = (int) $pdo->query('SELECT MIN(id) FROM admins')->fetchColumn();
        if ($minId > 0) {
            $pdo->prepare('UPDATE admins SET is_super_admin = 1 WHERE id = :id LIMIT 1')->execute(['id' => $minId]);
            $messages[] = 'Compte super-administrateur : le compte le plus ancien (id ' . $minId . ') peut créer d’autres administrateurs.';
        }
    }

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(255) NOT NULL,
  logo_path VARCHAR(512) DEFAULT NULL,
  contact_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  website_url VARCHAR(512) DEFAULT NULL,
  project_type VARCHAR(128) DEFAULT NULL,
  maintenances_per_year INT UNSIGNED NOT NULL DEFAULT 0,
  project_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  hosting_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  maintenance_annual_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  billing_currency ENUM('EUR','XOF') NOT NULL DEFAULT 'EUR',
  notes TEXT,
  status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_clients_company (company_name),
  KEY idx_clients_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $columnStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'clients'
         AND COLUMN_NAME = 'maintenances_per_year'"
    );
    $columnStmt->execute();
    if ((int) $columnStmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE clients ADD maintenances_per_year INT UNSIGNED NOT NULL DEFAULT 0 AFTER project_type');
    }

    $priceCols = [
        ['project_price', 'maintenances_per_year'],
        ['hosting_price', 'project_price'],
        ['maintenance_annual_price', 'hosting_price'],
    ];
    foreach ($priceCols as [$col, $after]) {
        $pc = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'clients' AND COLUMN_NAME = :c"
        );
        $pc->execute(['c' => $col]);
        if ((int) $pc->fetchColumn() === 0) {
            $pdo->exec(
                'ALTER TABLE clients ADD COLUMN ' . $col
                . ' DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER ' . $after
            );
        }
    }

    $bc = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'billing_currency'"
    );
    $bc->execute();
    if ((int) $bc->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE clients ADD COLUMN billing_currency ENUM(\'EUR\',\'XOF\') NOT NULL DEFAULT \'EUR\' AFTER maintenance_annual_price'
        );
    }

    $logoCol = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'logo_path'"
    );
    $logoCol->execute();
    if ((int) $logoCol->fetchColumn() === 0) {
        $pdo->exec(
            'ALTER TABLE clients ADD COLUMN logo_path VARCHAR(512) NULL DEFAULT NULL AFTER company_name'
        );
    }

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS client_maintenances (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  performed_at DATE NOT NULL,
  summary VARCHAR(255) DEFAULT NULL,
  notes TEXT,
  public_token VARCHAR(64) DEFAULT NULL,
  validated_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY idx_cm_public_token (public_token),
  KEY idx_cm_client_performed (client_id, performed_at),
  CONSTRAINT fk_cm_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $colTok = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'client_maintenances' AND COLUMN_NAME = 'public_token'"
    );
    $colTok->execute();
    if ((int) $colTok->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE client_maintenances ADD public_token VARCHAR(64) NULL DEFAULT NULL AFTER notes');
    }

    $colVal = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'client_maintenances' AND COLUMN_NAME = 'validated_at'"
    );
    $colVal->execute();
    if ((int) $colVal->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE client_maintenances ADD validated_at DATETIME NULL DEFAULT NULL AFTER public_token');
    }

    $needTokIds = $pdo->query(
        "SELECT id FROM client_maintenances WHERE public_token IS NULL OR public_token = ''"
    )->fetchAll(PDO::FETCH_COLUMN);
    if ($needTokIds !== []) {
        $upTok = $pdo->prepare('UPDATE client_maintenances SET public_token = :tok WHERE id = :id LIMIT 1');
        foreach ($needTokIds as $mid) {
            $upTok->execute(['tok' => bin2hex(random_bytes(32)), 'id' => (int) $mid]);
        }
    }

    $idxStmt = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'client_maintenances' AND INDEX_NAME = 'idx_cm_public_token'"
    );
    $idxStmt->execute();
    if ((int) $idxStmt->fetchColumn() === 0) {
        $pdo->exec('CREATE UNIQUE INDEX idx_cm_public_token ON client_maintenances (public_token)');
    }

    $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS client_project_tasks (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id INT UNSIGNED NOT NULL,
  title VARCHAR(255) NOT NULL,
  description TEXT,
  status ENUM('pending','done') NOT NULL DEFAULT 'pending',
  due_date DATE NULL DEFAULT NULL,
  notify_email TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  notification_sent TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  notification_sent_at DATETIME NULL DEFAULT NULL,
  completed_at DATETIME NULL DEFAULT NULL,
  created_by INT UNSIGNED NULL DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_cpt_client_status (client_id, status),
  KEY idx_cpt_client_due (client_id, due_date),
  CONSTRAINT fk_cpt_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

    $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash($adminPasswordPlain, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            'INSERT INTO admins (email, first_name, last_name, password_hash, is_super_admin) VALUES (:email, NULL, NULL, :hash, 1)'
        );
        $stmt->execute(['email' => $adminEmail, 'hash' => $hash]);
        $messages[] = 'Compte super-administrateur créé : ' . htmlspecialchars($adminEmail, ENT_QUOTES, 'UTF-8');
        $messages[] = 'Mot de passe initial à changer immédiatement : <code>' . htmlspecialchars($adminPasswordPlain, ENT_QUOTES, 'UTF-8') . '</code>';
    } else {
        $messages[] = 'Des administrateurs existent déjà — aucun nouveau compte créé.';
    }

    $messages[] = 'Tables vérifiées / créées avec succès.';
} catch (Throwable $e) {
    $ok = false;
    $messages[] = 'Erreur : ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $messages[] = 'Vérifiez que la base <code>goo_bridge</code> existe et les identifiants dans <code>config/database.php</code>.';
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Installation Goo-Bridge</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 48px auto; padding: 0 20px; line-height: 1.5; }
    .ok { color: #15803d; } .err { color: #b91c1c; }
    code { background: #f4f4f5; padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>
  <h1>Installation base de données</h1>
  <?php foreach ($messages as $m): ?>
    <p class="<?= $ok ? 'ok' : 'err' ?>"><?= $m ?></p>
  <?php endforeach; ?>
  <?php if ($ok): ?>
    <p><a href="login.php">Aller à la page de connexion</a></p>
    <p><small>Pensez à supprimer <code>install.php</code> après coup.</small></p>
  <?php endif; ?>
</body>
</html>
