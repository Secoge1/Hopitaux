-- PharmaPro ERP — Migration 006 : Ressources humaines
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pe_employees (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    personnel_id INT DEFAULT NULL COMMENT 'Lien personnel.id (HIS)',
    employee_code VARCHAR(32) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    job_title VARCHAR(120) DEFAULT NULL,
    department VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(40) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    hire_date DATE DEFAULT NULL,
    salary_base DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    bank_account VARCHAR(80) DEFAULT NULL,
    status ENUM('active','inactive','terminated') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_employee_code_tenant (tenant_id, employee_code),
    KEY idx_pe_employee_personnel (personnel_id),
    KEY idx_pe_employee_pharmacy (pharmacy_id),
    CONSTRAINT fk_pe_employee_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_payroll_runs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    pharmacy_id INT UNSIGNED NOT NULL,
    run_number VARCHAR(40) NOT NULL,
    period_label VARCHAR(40) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    total_gross DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_deductions DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    total_net DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    status ENUM('draft','validated','paid','cancelled') NOT NULL DEFAULT 'draft',
    validated_at DATETIME DEFAULT NULL,
    paid_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_pe_payroll_number_tenant (tenant_id, run_number),
    KEY idx_pe_payroll_pharmacy (pharmacy_id),
    CONSTRAINT fk_pe_payroll_pharmacy FOREIGN KEY (pharmacy_id) REFERENCES pe_pharmacies(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_payroll_lines (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    payroll_run_id INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    employee_name VARCHAR(200) NOT NULL,
    base_salary DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    bonus DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    deductions DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    net_salary DECIMAL(18,2) NOT NULL DEFAULT 0.00,
    notes VARCHAR(255) DEFAULT NULL,
    KEY idx_pe_pl_run (payroll_run_id),
    KEY idx_pe_pl_employee (employee_id),
    CONSTRAINT fk_pe_pl_run FOREIGN KEY (payroll_run_id) REFERENCES pe_payroll_runs(id) ON DELETE CASCADE,
    CONSTRAINT fk_pe_pl_employee FOREIGN KEY (employee_id) REFERENCES pe_employees(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pe_leave_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    leave_type ENUM('annual','sick','maternity','unpaid','other') NOT NULL DEFAULT 'annual',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_count INT NOT NULL DEFAULT 1,
    reason TEXT DEFAULT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    reviewed_by INT DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pe_leave_employee (employee_id),
    KEY idx_pe_leave_status (status),
    CONSTRAINT fk_pe_leave_employee FOREIGN KEY (employee_id) REFERENCES pe_employees(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO pe_schema_migrations (version, description)
VALUES ('006', 'Ressources humaines PharmaPro ERP');
