-- TP-Radar 5.0.1: Schema fuer eine NEUE, LEERE MySQL/MariaDB-Datenbank.
-- Vorher eine eigene Datenbank erstellen und in phpMyAdmin auswaehlen.
-- Dieses Skript enthaelt weder DROP noch USE und keine realen Zugangsdaten.
-- Bei bereits bestehenden TP-Radar-Tabellen NICHT importieren; dafuer ist eine Migration noetig.

CREATE TABLE products (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(120) NOT NULL,
 ean VARCHAR(14) NULL UNIQUE,
 rolls SMALLINT UNSIGNED NOT NULL,
 sheets SMALLINT UNSIGNED NOT NULL,
 layers TINYINT UNSIGNED NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE stores (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL,
 normalized_name VARCHAR(80) NOT NULL UNIQUE,
 is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;
CREATE TABLE prices (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 product_id BIGINT UNSIGNED NOT NULL,
 store_id BIGINT UNSIGNED NOT NULL,
 price_cents INT UNSIGNED NOT NULL,
 observed_on DATE NOT NULL,
 status ENUM('confirmed','pending','rejected') NOT NULL DEFAULT 'pending',
 reason VARCHAR(100) NULL,
 reporter_hash CHAR(64) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_product_store (product_id,store_id,observed_on),
 INDEX idx_status_date(status,observed_on),
 CONSTRAINT fk_price_product FOREIGN KEY(product_id) REFERENCES products(id),
 CONSTRAINT fk_price_store FOREIGN KEY(store_id) REFERENCES stores(id)
) ENGINE=InnoDB;
CREATE TABLE confirmations (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 price_id BIGINT UNSIGNED NOT NULL,
 reporter_hash CHAR(64) NOT NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY one_confirm_per_reporter(price_id,reporter_hash),
 CONSTRAINT fk_confirmation_price FOREIGN KEY(price_id) REFERENCES prices(id)
) ENGINE=InnoDB;
CREATE TABLE request_limits (
 actor_hash CHAR(64) NOT NULL,
 bucket VARCHAR(40) NOT NULL,
 window_start DATETIME NOT NULL,
 requests INT UNSIGNED NOT NULL DEFAULT 1,
 PRIMARY KEY(actor_hash,bucket)
) ENGINE=InnoDB;

-- Admin-Tabellen fuer TP-Radar V5.0 (Neuinstallation)
CREATE TABLE admin_login_attempts (
 actor_hash CHAR(64) PRIMARY KEY,
 window_start DATETIME NOT NULL,
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 blocked_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE admin_security_state (
 id TINYINT UNSIGNED PRIMARY KEY,
 last_totp_step BIGINT NOT NULL DEFAULT -1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
INSERT INTO admin_security_state(id,last_totp_step) VALUES(1,-1);
CREATE TABLE admin_audit_log (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 action VARCHAR(64) NOT NULL,
 entity_type VARCHAR(40) NOT NULL,
 entity_id BIGINT UNSIGNED NOT NULL,
 before_json LONGTEXT NULL,
 after_json LONGTEXT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_audit_created(created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Kontrollausgabe: exakt acht TP-Radar-Tabellen und ein TOTP-Seed-Record.
SELECT 'TP-Radar schema ready' AS info;
SELECT id, last_totp_step FROM admin_security_state WHERE id = 1;
