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

    $clientsTblExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients'"
    );
    $clientsTblExists->execute();
    if ((int) $clientsTblExists->fetchColumn() > 0) {
        $clientPriceCol = static function (PDO $pdoConn, string $column, string $afterColumn): void {
            $chk = $pdoConn->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = :col"
            );
            $chk->execute(['col' => $column]);
            if ((int) $chk->fetchColumn() === 0) {
                $pdoConn->exec(
                    'ALTER TABLE clients ADD COLUMN ' . $column
                    . ' DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER ' . $afterColumn
                );
            }
        };
        $clientPriceCol($pdo, 'project_price', 'maintenances_per_year');
        $clientPriceCol($pdo, 'hosting_price', 'project_price');
        $clientPriceCol($pdo, 'maintenance_annual_price', 'hosting_price');

        $chkBillingCur = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'billing_currency'"
        );
        $chkBillingCur->execute();
        if ((int) $chkBillingCur->fetchColumn() === 0) {
            $pdo->exec(
                'ALTER TABLE clients ADD COLUMN billing_currency ENUM(\'EUR\',\'XOF\') NOT NULL DEFAULT \'EUR\' AFTER maintenance_annual_price'
            );
        }

        $chkLogo = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'logo_path'"
        );
        $chkLogo->execute();
        if ((int) $chkLogo->fetchColumn() === 0) {
            $pdo->exec(
                'ALTER TABLE clients ADD COLUMN logo_path VARCHAR(512) NULL DEFAULT NULL AFTER company_name'
            );
        }

        $chkTicketPortal = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients' AND COLUMN_NAME = 'ticket_portal_token'"
        );
        $chkTicketPortal->execute();
        if ((int) $chkTicketPortal->fetchColumn() === 0) {
            $pdo->exec(
                'ALTER TABLE clients ADD COLUMN ticket_portal_token VARCHAR(64) NULL DEFAULT NULL AFTER logo_path'
            );
        }
    }

    $cstTblExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_support_tickets'"
    );
    $cstTblExists->execute();
    if ((int) $cstTblExists->fetchColumn() === 0) {
        $clientsExistsForTickets = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients'"
        );
        $clientsExistsForTickets->execute();
        if ((int) $clientsExistsForTickets->fetchColumn() > 0) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS client_support_tickets (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    client_id INT UNSIGNED NOT NULL,
                    category ENUM(\'correction\',\'update\',\'help\',\'other\') NOT NULL DEFAULT \'other\',
                    priority ENUM(\'low\',\'normal\',\'high\',\'critical\') NOT NULL DEFAULT \'normal\',
                    subject VARCHAR(255) NOT NULL,
                    message TEXT NOT NULL,
                    requester_name VARCHAR(255) NULL DEFAULT NULL,
                    requester_email VARCHAR(255) NULL DEFAULT NULL,
                    requester_phone VARCHAR(64) NULL DEFAULT NULL,
                    attachments_json TEXT NULL DEFAULT NULL,
                    status ENUM(\'open\',\'closed\') NOT NULL DEFAULT \'open\',
                    assigned_admin_id INT UNSIGNED NULL DEFAULT NULL,
                    taken_at DATETIME NULL DEFAULT NULL,
                    closed_at DATETIME NULL DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_cst_client_status (client_id, status),
                    KEY idx_cst_client_created (client_id, created_at),
                    KEY idx_cst_client_priority (client_id, priority),
                    KEY idx_cst_assigned_admin (assigned_admin_id),
                    CONSTRAINT fk_cst_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
    } else {
        $addColIfMissing = static function (PDO $pdoConn, string $column, string $sql): void {
            $chk = $pdoConn->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_support_tickets' AND COLUMN_NAME = :col"
            );
            $chk->execute(['col' => $column]);
            if ((int) $chk->fetchColumn() === 0) {
                $pdoConn->exec('ALTER TABLE client_support_tickets ' . $sql);
            }
        };
        $addColIfMissing(
            $pdo,
            'priority',
            "ADD COLUMN priority ENUM('low','normal','high','critical') NOT NULL DEFAULT 'normal' AFTER category"
        );
        $addColIfMissing(
            $pdo,
            'requester_phone',
            'ADD COLUMN requester_phone VARCHAR(64) NULL DEFAULT NULL AFTER requester_email'
        );
        $addColIfMissing(
            $pdo,
            'attachments_json',
            'ADD COLUMN attachments_json TEXT NULL DEFAULT NULL AFTER requester_phone'
        );
        $addColIfMissing(
            $pdo,
            'assigned_admin_id',
            'ADD COLUMN assigned_admin_id INT UNSIGNED NULL DEFAULT NULL AFTER status'
        );
        $addColIfMissing(
            $pdo,
            'taken_at',
            'ADD COLUMN taken_at DATETIME NULL DEFAULT NULL AFTER assigned_admin_id'
        );
        try {
            $idxAssigned = $pdo->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'client_support_tickets' AND INDEX_NAME = 'idx_cst_assigned_admin'"
            );
            $idxAssigned->execute();
            if ((int) $idxAssigned->fetchColumn() === 0) {
                $pdo->exec('CREATE INDEX idx_cst_assigned_admin ON client_support_tickets (assigned_admin_id)');
            }
        } catch (PDOException $e) {
            // ignoré
        }
    }

    // Lien optionnel d'une maintenance vers le ticket d'origine
    try {
        $cmExists = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_maintenances'"
        );
        $cmExists->execute();
        if ((int) $cmExists->fetchColumn() > 0) {
            $chkCmTicket = $pdo->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_maintenances' AND COLUMN_NAME = 'ticket_id'"
            );
            $chkCmTicket->execute();
            if ((int) $chkCmTicket->fetchColumn() === 0) {
                $pdo->exec(
                    'ALTER TABLE client_maintenances ADD COLUMN ticket_id INT UNSIGNED NULL DEFAULT NULL AFTER client_id'
                );
            }
            $idxCmTicket = $pdo->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'client_maintenances' AND INDEX_NAME = 'idx_cm_ticket'"
            );
            $idxCmTicket->execute();
            if ((int) $idxCmTicket->fetchColumn() === 0) {
                try {
                    $pdo->exec('CREATE INDEX idx_cm_ticket ON client_maintenances (ticket_id)');
                } catch (PDOException $e) {
                    // ignoré
                }
            }
        }
    } catch (PDOException $e) {
        // ignoré
    }

    // Table d'historique des interventions (notes) sur un ticket
    $cstNotesExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_support_ticket_notes'"
    );
    $cstNotesExists->execute();
    if ((int) $cstNotesExists->fetchColumn() === 0) {
        try {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS client_support_ticket_notes (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    ticket_id INT UNSIGNED NOT NULL,
                    admin_id INT UNSIGNED NULL DEFAULT NULL,
                    body TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_cstn_ticket_created (ticket_id, created_at),
                    CONSTRAINT fk_cstn_ticket FOREIGN KEY (ticket_id) REFERENCES client_support_tickets(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (PDOException $e) {
            // ignoré (table support n'existe peut-être pas encore lors d'un setup initial vide)
        }
    }

    $clientsTblExists->execute();
    if ((int) $clientsTblExists->fetchColumn() > 0) {
        $needTicketPortalTok = $pdo->query(
            "SELECT id FROM clients WHERE ticket_portal_token IS NULL OR ticket_portal_token = ''"
        )->fetchAll(PDO::FETCH_COLUMN);
        if ($needTicketPortalTok !== []) {
            $upPortalTok = $pdo->prepare(
                'UPDATE clients SET ticket_portal_token = :tok WHERE id = :id LIMIT 1'
            );
            foreach ($needTicketPortalTok as $cid) {
                $upPortalTok->execute(['tok' => bin2hex(random_bytes(32)), 'id' => (int) $cid]);
            }
        }

        $idxPortalTok = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
             AND TABLE_NAME = 'clients' AND INDEX_NAME = 'idx_clients_ticket_portal_token'"
        );
        $idxPortalTok->execute();
        if ((int) $idxPortalTok->fetchColumn() === 0) {
            try {
                $pdo->exec(
                    'CREATE UNIQUE INDEX idx_clients_ticket_portal_token ON clients (ticket_portal_token)'
                );
            } catch (PDOException $e) {
                // Index peut déjà exister sous un autre nom ou contrainte locale — ignoré.
            }
        }
    }

    $tasksExists = $pdo->prepare(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'client_project_tasks'"
    );
    $tasksExists->execute();
    if ((int) $tasksExists->fetchColumn() === 0) {
        $clientsExists = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clients'"
        );
        $clientsExists->execute();
        if ((int) $clientsExists->fetchColumn() > 0) {
            $pdo->exec(
                'CREATE TABLE IF NOT EXISTS client_project_tasks (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    client_id INT UNSIGNED NOT NULL,
                    title VARCHAR(255) NOT NULL,
                    description TEXT,
                    status ENUM(\'pending\',\'done\') NOT NULL DEFAULT \'pending\',
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
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }
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
