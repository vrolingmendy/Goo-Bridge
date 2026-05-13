-- Base : créez-la dans phpMyAdmin avec le nom `goo_bridge`, puis importez ce fichier
-- ou utilisez install.php depuis le navigateur.

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(255) NOT NULL,
  contact_name VARCHAR(255) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(64) DEFAULT NULL,
  website_url VARCHAR(512) DEFAULT NULL,
  project_type VARCHAR(128) DEFAULT NULL,
  maintenances_per_year INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT,
  status ENUM('active','paused','completed') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_clients_company (company_name),
  KEY idx_clients_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Le mot de passe doit être hashé en PHP : utilisez install.php pour créer le premier admin.
