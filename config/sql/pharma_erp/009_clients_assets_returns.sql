-- PharmaPro ERP — Migration 009 : Clients CRM, immobilisations, retours vente enrichis
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pe_customers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    code VARCHAR(32) NOT NULL,
    first_name VARCHAR(100) DEFAULT NULL,
    last_name VARCHAR(100) DEFAULT NULL,
    company_name VARCHAR(150) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(150) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    city VARCHAR(80) DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    loyalty_account_id INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_customer_code (tenant_id, code),
    KEY idx_pe_customer_phone (tenant_id, phone),
    KEY idx_pe_customer_name (tenant_id, last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_fixed_assets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED DEFAULT NULL,
    asset_code VARCHAR(40) NOT NULL,
    label VARCHAR(200) NOT NULL,
    category VARCHAR(80) DEFAULT NULL,
    acquisition_date DATE NOT NULL,
    acquisition_cost DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    useful_life_months INT NOT NULL DEFAULT 60,
    residual_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    depreciation_method ENUM('linear') NOT NULL DEFAULT 'linear',
    accumulated_depreciation DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    net_book_value DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','disposed','inactive') NOT NULL DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_asset_code (tenant_id, asset_code),
    KEY idx_pe_asset_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE pe_sale_lines ADD COLUMN returned_quantity INT NOT NULL DEFAULT 0 AFTER line_profit;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('009', 'Clients CRM, immobilisations, retours vente PharmaPro ERP');
