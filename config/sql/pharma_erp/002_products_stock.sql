-- PharmaPro ERP — Migration 002 : Produits, lots, stock
-- Dépend de : 001_foundation.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- Catégories produits
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_product_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    parent_id INT UNSIGNED DEFAULT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(120) NOT NULL,
    description TEXT DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_category_code_tenant (tenant_id, code),
    KEY idx_pe_category_parent (parent_id),
    KEY idx_pe_category_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Laboratoires pharmaceutiques
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_laboratories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    code VARCHAR(32) NOT NULL,
    name VARCHAR(150) NOT NULL,
    country_code CHAR(2) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    website VARCHAR(200) DEFAULT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_lab_code_tenant (tenant_id, code),
    KEY idx_pe_lab_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Fournisseurs
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    code VARCHAR(32) NOT NULL,
    company_name VARCHAR(200) NOT NULL,
    contact_name VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    tax_id VARCHAR(80) DEFAULT NULL,
    payment_terms_days SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    credit_limit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    current_balance DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_supplier_code_tenant (tenant_id, code),
    KEY idx_pe_supplier_tenant (tenant_id),
    KEY idx_pe_supplier_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Produits / médicaments (ERP — distinct de medicaments HIS)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    laboratory_id INT UNSIGNED DEFAULT NULL,
    sku VARCHAR(64) NOT NULL,
    barcode_primary VARCHAR(64) DEFAULT NULL,
    name VARCHAR(200) NOT NULL,
    generic_name VARCHAR(200) DEFAULT NULL,
    dci VARCHAR(200) DEFAULT NULL,
    dosage_form ENUM('tablet','capsule','syrup','injection','cream','drops','other') NOT NULL DEFAULT 'tablet',
    strength VARCHAR(80) DEFAULT NULL,
    unit_label VARCHAR(30) NOT NULL DEFAULT 'unité',
    pack_size INT UNSIGNED NOT NULL DEFAULT 1,
    purchase_price DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    sale_price DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    wholesale_price DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    margin_rate DECIMAL(7,4) GENERATED ALWAYS AS (
        CASE WHEN purchase_price > 0
            THEN ROUND(((sale_price - purchase_price) / purchase_price) * 100, 4)
            ELSE 0
        END
    ) STORED,
    reorder_level INT UNSIGNED NOT NULL DEFAULT 0,
    reorder_qty INT UNSIGNED NOT NULL DEFAULT 0,
    requires_prescription TINYINT(1) NOT NULL DEFAULT 0,
    is_controlled TINYINT(1) NOT NULL DEFAULT 0,
    is_refrigerated TINYINT(1) NOT NULL DEFAULT 0,
    external_medicament_id INT DEFAULT NULL COMMENT 'Lien optionnel medicaments.id (HIS)',
    search_index TEXT DEFAULT NULL,
    status ENUM('active','inactive','discontinued') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME DEFAULT NULL,
    UNIQUE KEY uk_pe_product_sku_tenant (tenant_id, sku),
    KEY idx_pe_product_barcode (tenant_id, barcode_primary),
    KEY idx_pe_product_category (category_id),
    KEY idx_pe_product_laboratory (laboratory_id),
    KEY idx_pe_product_status (status),
    FULLTEXT KEY ft_pe_product_search (name, generic_name, dci, search_index),
    CONSTRAINT fk_pe_product_category FOREIGN KEY (category_id) REFERENCES pe_product_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_pe_product_laboratory FOREIGN KEY (laboratory_id) REFERENCES pe_laboratories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Codes-barres multiples
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_product_barcodes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    barcode VARCHAR(64) NOT NULL,
    barcode_type ENUM('EAN13','EAN8','UPC','CODE128','QR','INTERNAL') NOT NULL DEFAULT 'EAN13',
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_barcode_tenant (tenant_id, barcode),
    KEY idx_pe_barcode_product (product_id),
    CONSTRAINT fk_pe_barcode_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Lots (batch tracking)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_lots (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    deposit_id INT UNSIGNED NOT NULL,
    lot_number VARCHAR(80) NOT NULL,
    manufacturing_date DATE DEFAULT NULL,
    expiry_date DATE NOT NULL,
    supplier_id INT UNSIGNED DEFAULT NULL,
    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    initial_qty INT NOT NULL DEFAULT 0,
    current_qty INT NOT NULL DEFAULT 0,
    status ENUM('active','quarantine','expired','depleted') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_lot_product_deposit_number (product_id, deposit_id, lot_number),
    KEY idx_pe_lot_tenant (tenant_id),
    KEY idx_pe_lot_expiry (expiry_date),
    KEY idx_pe_lot_status (status),
    CONSTRAINT fk_pe_lot_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_lot_deposit FOREIGN KEY (deposit_id) REFERENCES pe_deposits(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_lot_supplier FOREIGN KEY (supplier_id) REFERENCES pe_suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Niveaux de stock agrégés (performance POS)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_stock_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    deposit_id INT UNSIGNED NOT NULL,
    lot_id INT UNSIGNED DEFAULT NULL,
    quantity INT NOT NULL DEFAULT 0,
    reserved_qty INT NOT NULL DEFAULT 0,
    available_qty INT GENERATED ALWAYS AS (quantity - reserved_qty) STORED,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_stock_product_deposit_lot (product_id, deposit_id, lot_id),
    KEY idx_pe_stock_tenant (tenant_id),
    KEY idx_pe_stock_product (product_id),
    KEY idx_pe_stock_deposit (deposit_id),
    CONSTRAINT fk_pe_stock_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_stock_deposit FOREIGN KEY (deposit_id) REFERENCES pe_deposits(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_stock_lot FOREIGN KEY (lot_id) REFERENCES pe_lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Mouvements de stock (historique complet)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_stock_movements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    deposit_id INT UNSIGNED NOT NULL,
    lot_id INT UNSIGNED DEFAULT NULL,
    movement_type ENUM('in','out','adjustment','transfer_in','transfer_out','sale','return','inventory') NOT NULL,
    quantity INT NOT NULL,
    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    reference_type VARCHAR(40) DEFAULT NULL,
    reference_id BIGINT UNSIGNED DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pe_movement_tenant_date (tenant_id, created_at),
    KEY idx_pe_movement_product (product_id),
    KEY idx_pe_movement_deposit (deposit_id),
    KEY idx_pe_movement_lot (lot_id),
    KEY idx_pe_movement_ref (reference_type, reference_id),
    CONSTRAINT fk_pe_movement_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_movement_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_movement_deposit FOREIGN KEY (deposit_id) REFERENCES pe_deposits(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_movement_lot FOREIGN KEY (lot_id) REFERENCES pe_lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Inventaires physiques
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_inventories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    deposit_id INT UNSIGNED NOT NULL,
    inventory_number VARCHAR(40) NOT NULL,
    status ENUM('draft','counting','validated','cancelled') NOT NULL DEFAULT 'draft',
    started_at DATETIME DEFAULT NULL,
    validated_at DATETIME DEFAULT NULL,
    validated_by INT DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_inventory_number_tenant (tenant_id, inventory_number),
    KEY idx_pe_inventory_deposit (deposit_id),
    CONSTRAINT fk_pe_inventory_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_inventory_deposit FOREIGN KEY (deposit_id) REFERENCES pe_deposits(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_inventory_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    inventory_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    lot_id INT UNSIGNED DEFAULT NULL,
    system_qty INT NOT NULL DEFAULT 0,
    counted_qty INT NOT NULL DEFAULT 0,
    variance_qty INT GENERATED ALWAYS AS (counted_qty - system_qty) STORED,
    unit_cost DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    notes VARCHAR(255) DEFAULT NULL,
    KEY idx_pe_invline_inventory (inventory_id),
    KEY idx_pe_invline_product (product_id),
    CONSTRAINT fk_pe_invline_inventory FOREIGN KEY (inventory_id) REFERENCES pe_inventories(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_invline_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE RESTRICT,
    CONSTRAINT fk_pe_invline_lot FOREIGN KEY (lot_id) REFERENCES pe_lots(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- Produits ↔ Fournisseurs (prix d'achat par fournisseur)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pe_product_suppliers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    supplier_id INT UNSIGNED NOT NULL,
    supplier_sku VARCHAR(64) DEFAULT NULL,
    purchase_price DECIMAL(18,4) NOT NULL DEFAULT 0.0000,
    lead_time_days SMALLINT UNSIGNED NOT NULL DEFAULT 7,
    is_preferred TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_product_supplier (product_id, supplier_id),
    KEY idx_pe_ps_tenant (tenant_id),
    CONSTRAINT fk_pe_ps_product FOREIGN KEY (product_id) REFERENCES pe_products(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_ps_supplier FOREIGN KEY (supplier_id) REFERENCES pe_suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('002', 'Produits, lots et stock PharmaPro ERP');
