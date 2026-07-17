-- PharmaPro ERP — Migration 003 : Ventes POS & paiements
-- Dépend de : 001_foundation.sql, 002_products_stock.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pe_sales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    cash_register_id INT UNSIGNED NOT NULL,
    sale_number VARCHAR(40) NOT NULL,
    sale_type ENUM('retail','wholesale','prescription') NOT NULL DEFAULT 'retail',
    customer_name VARCHAR(150) DEFAULT NULL,
    customer_phone VARCHAR(40) DEFAULT NULL,
    subtotal_ht DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_ttc DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    change_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    profit_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','completed','cancelled','refunded') NOT NULL DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,
    cancelled_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_sale_number_tenant (tenant_id, sale_number),
    KEY idx_pe_sale_pharmacy_date (pharmacy_id, created_at),
    KEY idx_pe_sale_status (status),
    CONSTRAINT fk_pe_sale_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_sale_register FOREIGN KEY (cash_register_id) REFERENCES pe_cash_registers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_sale_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    lot_id INT UNSIGNED DEFAULT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    discount_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    line_profit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    KEY idx_pe_saleline_sale (sale_id),
    KEY idx_pe_saleline_product (product_id),
    CONSTRAINT fk_pe_saleline_sale FOREIGN KEY (sale_id) REFERENCES pe_sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_saleline_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_saleline_lot FOREIGN KEY (lot_id) REFERENCES pe_lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_sale_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    sale_id BIGINT UNSIGNED NOT NULL,
    payment_method ENUM('cash','mobile_money','bank','card','mixed','credit') NOT NULL DEFAULT 'cash',
    amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    reference VARCHAR(80) DEFAULT NULL,
    provider VARCHAR(60) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pe_payment_sale (sale_id),
    CONSTRAINT fk_pe_payment_sale FOREIGN KEY (sale_id) REFERENCES pe_sales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('003', 'Ventes POS et paiements PharmaPro ERP');
