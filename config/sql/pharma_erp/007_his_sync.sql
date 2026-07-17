-- PharmaPro ERP — Migration 007 : Pont synchronisation HIS Finances
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS pe_his_ecriture_map (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    journal_entry_id BIGINT UNSIGNED NOT NULL,
    ecriture_comptable_id INT NOT NULL,
    split_index TINYINT UNSIGNED NOT NULL DEFAULT 0,
    amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pe_his_map_entry (journal_entry_id),
    KEY idx_pe_his_map_ecriture (ecriture_comptable_id),
    UNIQUE KEY uk_pe_his_map_split (journal_entry_id, split_index),
    CONSTRAINT fk_pe_his_map_entry FOREIGN KEY (journal_entry_id) REFERENCES pe_journal_entries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('007', 'Pont comptable HIS Finances PharmaPro ERP');
