-- PharmaPro ERP — Migration 008 : Promotions, fidélité, retours, médical, banque
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pe_promotions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED DEFAULT NULL,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(150) NOT NULL,
    discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    min_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    starts_at DATETIME DEFAULT NULL,
    ends_at DATETIME DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_promo_code (tenant_id, code),
    KEY idx_pe_promo_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_loyalty_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    customer_phone VARCHAR(40) NOT NULL,
    customer_name VARCHAR(150) DEFAULT NULL,
    points_balance INT NOT NULL DEFAULT 0,
    lifetime_points INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_loyalty_phone (tenant_id, customer_phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_loyalty_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED DEFAULT NULL,
    points_delta INT NOT NULL,
    reason VARCHAR(120) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pe_loyalty_account (account_id),
    CONSTRAINT fk_pe_loyalty_account FOREIGN KEY (account_id) REFERENCES pe_loyalty_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_returns (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    return_number VARCHAR(40) NOT NULL,
    total_refund DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
    reason VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_return_number (tenant_id, return_number),
    KEY idx_pe_return_sale (sale_id),
    CONSTRAINT fk_pe_return_sale FOREIGN KEY (sale_id) REFERENCES pe_sales(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_return_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    return_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    line_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    KEY idx_pe_returnline_return (return_id),
    CONSTRAINT fk_pe_returnline_return FOREIGN KEY (return_id) REFERENCES pe_returns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_patients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    external_patient_id INT DEFAULT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_patient_external (tenant_id, external_patient_id),
    KEY idx_pe_patient_name (last_name, first_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_prescriptions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    pe_patient_id INT UNSIGNED DEFAULT NULL,
    external_consultation_id INT DEFAULT NULL,
    prescription_number VARCHAR(40) NOT NULL,
    prescriber_name VARCHAR(150) DEFAULT NULL,
    status ENUM('pending','partial','dispensed','cancelled') NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dispensed_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_prescription_number (tenant_id, prescription_number),
    KEY idx_pe_prescription_status (status),
    CONSTRAINT fk_pe_prescription_patient FOREIGN KEY (pe_patient_id) REFERENCES pe_patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_prescription_lines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    prescription_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity_prescribed INT NOT NULL DEFAULT 1,
    quantity_dispensed INT NOT NULL DEFAULT 0,
    dosage_notes VARCHAR(255) DEFAULT NULL,
    KEY idx_pe_prescline_rx (prescription_id),
    CONSTRAINT fk_pe_prescline_rx FOREIGN KEY (prescription_id) REFERENCES pe_prescriptions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_bank_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    account_number VARCHAR(40) NOT NULL,
    bank_name VARCHAR(120) NOT NULL,
    label VARCHAR(120) DEFAULT NULL,
    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_bank_account (tenant_id, account_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_bank_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    bank_account_id INT UNSIGNED NOT NULL,
    movement_date DATE NOT NULL,
    label VARCHAR(200) NOT NULL,
    debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    reference VARCHAR(80) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pe_bank_mov_account (bank_account_id, movement_date),
    CONSTRAINT fk_pe_bank_mov_account FOREIGN KEY (bank_account_id) REFERENCES pe_bank_accounts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_vat_periods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    period_label VARCHAR(40) NOT NULL,
    date_from DATE NOT NULL,
    date_to DATE NOT NULL,
    vat_collected DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_deductible DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('open','closed') NOT NULL DEFAULT 'open',
    closed_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_vat_period (tenant_id, period_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('008', 'Promotions, fidélité, retours, médical, banque PharmaPro ERP');
