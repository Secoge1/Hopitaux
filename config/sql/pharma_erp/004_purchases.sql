-- PharmaPro ERP — Migration 004 : Achats & réceptions
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pe_purchase_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    order_number VARCHAR(40) NOT NULL,
    status ENUM('draft','ordered','partial','received','cancelled') NOT NULL DEFAULT 'draft',
    order_date DATE NOT NULL,
    expected_date DATE DEFAULT NULL,
    subtotal_ht DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_ttc DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_po_number_tenant (tenant_id, order_number),
    KEY idx_pe_po_supplier (supplier_id),
    KEY idx_pe_po_status (status),
    CONSTRAINT fk_pe_po_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_po_supplier FOREIGN KEY (supplier_id) REFERENCES pe_suppliers(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_purchase_order_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    purchase_order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    product_name VARCHAR(200) NOT NULL,
    quantity_ordered INT NOT NULL DEFAULT 1,
    quantity_received INT NOT NULL DEFAULT 0,
    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    KEY idx_pe_pol_order (purchase_order_id),
    CONSTRAINT fk_pe_pol_order FOREIGN KEY (purchase_order_id) REFERENCES pe_purchase_orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_pol_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_goods_receipts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    deposit_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    purchase_order_id INT UNSIGNED DEFAULT NULL,
    receipt_number VARCHAR(40) NOT NULL,
    receipt_date DATE NOT NULL,
    subtotal_ht DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_ttc DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','validated','cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT DEFAULT NULL,
    validated_by INT DEFAULT NULL,
    validated_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_gr_number_tenant (tenant_id, receipt_number),
    KEY idx_pe_gr_supplier (supplier_id),
    CONSTRAINT fk_pe_gr_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_gr_deposit FOREIGN KEY (deposit_id) REFERENCES pe_deposits(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_gr_supplier FOREIGN KEY (supplier_id) REFERENCES pe_suppliers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_gr_po FOREIGN KEY (purchase_order_id) REFERENCES pe_purchase_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_goods_receipt_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    goods_receipt_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    lot_number VARCHAR(80) NOT NULL,
    expiry_date DATE NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    KEY idx_pe_grl_receipt (goods_receipt_id),
    CONSTRAINT fk_pe_grl_receipt FOREIGN KEY (goods_receipt_id) REFERENCES pe_goods_receipts(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_grl_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_supplier_invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    goods_receipt_id INT UNSIGNED DEFAULT NULL,
    invoice_number VARCHAR(60) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    amount_ht DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    vat_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    amount_ttc DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending','partial','paid','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_sinv_number_tenant (tenant_id, invoice_number),
    KEY idx_pe_sinv_supplier (supplier_id),
    CONSTRAINT fk_pe_sinv_supplier FOREIGN KEY (supplier_id) REFERENCES pe_suppliers(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_sinv_gr FOREIGN KEY (goods_receipt_id) REFERENCES pe_goods_receipts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('004', 'Achats, commandes et réceptions PharmaPro ERP');
