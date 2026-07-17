-- PharmaPro ERP — Migration 001 : Fondations organisation + audit
-- Prérequis : tables tenants, utilisateurs (Efficasante SaaS)
-- Exécution idempotente via PharmaErpSchema::ensure()

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Schéma versioning
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_schema_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(32) NOT NULL,
    description VARCHAR(255) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_migration_version (version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Officines / sites (multi-pharmacies par tenant)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_pharmacies (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(150) NOT NULL,
    legal_name VARCHAR(200) DEFAULT NULL,
    license_number VARCHAR(80) DEFAULT NULL,
    tax_id VARCHAR(80) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    address_line1 VARCHAR(200) DEFAULT NULL,
    address_line2 VARCHAR(200) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    country_code CHAR(2) NOT NULL DEFAULT 'ML',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Bamako',
    currency_code CHAR(3) NOT NULL DEFAULT 'XOF',
    logo_path VARCHAR(255) DEFAULT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_pharmacy_code_tenant (tenant_id, code),
    KEY idx_pe_pharmacy_tenant (tenant_id),
    KEY idx_pe_pharmacy_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Dépôts / entrepôts
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_deposits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    deposit_type ENUM('store','warehouse','cold_chain','quarantine') NOT NULL DEFAULT 'store',
    is_sales_source TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_deposit_code_pharmacy (pharmacy_id, code),
    KEY idx_pe_deposit_tenant (tenant_id),
    KEY idx_pe_deposit_pharmacy (pharmacy_id),
    CONSTRAINT fk_pe_deposit_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Caisses POS
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_cash_registers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    register_type ENUM('pos','backoffice','mobile') NOT NULL DEFAULT 'pos',
    opening_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('open','closed','inactive') NOT NULL DEFAULT 'closed',
    last_opened_at DATETIME DEFAULT NULL,
    last_closed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_register_code_pharmacy (pharmacy_id, code),
    KEY idx_pe_register_tenant (tenant_id),
    KEY idx_pe_register_pharmacy (pharmacy_id),
    CONSTRAINT fk_pe_register_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Paramètres par officine (clé/valeur typée)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    setting_key VARCHAR(80) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    value_type ENUM('string','int','float','bool','json') NOT NULL DEFAULT 'string',
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_setting_pharmacy_key (pharmacy_id, setting_key),
    KEY idx_pe_setting_tenant (tenant_id),
    CONSTRAINT fk_pe_setting_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Numérotation documents (factures, tickets, BC…)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_document_sequences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    document_type VARCHAR(40) NOT NULL,
    prefix VARCHAR(20) NOT NULL DEFAULT '',
    suffix VARCHAR(20) NOT NULL DEFAULT '',
    next_number INT UNSIGNED NOT NULL DEFAULT 1,
    pad_length TINYINT UNSIGNED NOT NULL DEFAULT 6,
    fiscal_year SMALLINT UNSIGNED DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_sequence_pharmacy_type_year (pharmacy_id, document_type, fiscal_year),
    KEY idx_pe_sequence_tenant (tenant_id),
    CONSTRAINT fk_pe_sequence_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Journal d'audit PharmaPro
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED DEFAULT NULL,
    user_id INT DEFAULT NULL,
    action VARCHAR(80) NOT NULL,
    entity_type VARCHAR(60) NOT NULL,
    entity_id BIGINT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    payload_json JSON DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pe_audit_tenant_date (tenant_id, created_at),
    KEY idx_pe_audit_entity (entity_type, entity_id),
    KEY idx_pe_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('001', 'Fondations organisation PharmaPro ERP');
