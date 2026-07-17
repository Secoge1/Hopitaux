-- PharmaPro ERP — Migration 005 : Comptabilité SYSCOHADA
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pe_accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    account_number VARCHAR(20) NOT NULL,
    account_label VARCHAR(200) NOT NULL,
    account_class TINYINT UNSIGNED NOT NULL,
    account_type ENUM('asset','liability','equity','expense','revenue') NOT NULL DEFAULT 'asset',
    parent_id INT UNSIGNED DEFAULT NULL,
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    allow_manual TINYINT(1) NOT NULL DEFAULT 1,
    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_account_number_tenant (tenant_id, account_number),
    KEY idx_pe_account_class (account_class)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_journals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    code VARCHAR(10) NOT NULL,
    name VARCHAR(120) NOT NULL,
    journal_type ENUM('sales','purchases','bank','cash','general','payroll') NOT NULL DEFAULT 'general',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    UNIQUE KEY uk_pe_journal_code_tenant (tenant_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_journal_entries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED DEFAULT NULL,
    journal_id INT UNSIGNED NOT NULL,
    entry_number VARCHAR(40) NOT NULL,
    entry_date DATE NOT NULL,
    label VARCHAR(255) NOT NULL,
    reference_type VARCHAR(40) DEFAULT NULL,
    reference_id BIGINT UNSIGNED DEFAULT NULL,
    total_debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','posted','cancelled') NOT NULL DEFAULT 'draft',
    posted_at DATETIME DEFAULT NULL,
    posted_by INT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_entry_number_tenant (tenant_id, entry_number),
    KEY idx_pe_entry_date (entry_date),
    KEY idx_pe_entry_ref (reference_type, reference_id),
    CONSTRAINT fk_pe_entry_journal FOREIGN KEY (journal_id) REFERENCES pe_journals(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_journal_entry_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    account_id INT UNSIGNED NOT NULL,
    line_label VARCHAR(255) DEFAULT NULL,
    debit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    KEY idx_pe_jel_entry (journal_entry_id),
    KEY idx_pe_jel_account (account_id),
    CONSTRAINT fk_pe_jel_entry FOREIGN KEY (journal_entry_id) REFERENCES pe_journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_jel_account FOREIGN KEY (account_id) REFERENCES pe_accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_accounting_links (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    source_type VARCHAR(40) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    ecriture_comptable_id INT DEFAULT NULL COMMENT 'Pont HIS ecritures_comptables',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_link_source (tenant_id, source_type, source_id),
    KEY idx_pe_link_entry (journal_entry_id),
    CONSTRAINT fk_pe_link_entry FOREIGN KEY (journal_entry_id) REFERENCES pe_journal_entries(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('005', 'Comptabilité SYSCOHADA PharmaPro ERP');
