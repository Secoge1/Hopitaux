<?php
/**
 * Schéma multi-tenant SaaS — création / migration idempotente.
 */

require_once __DIR__ . '/../../config/db.php';

class TenantSchema
{
    private static bool $ensured = false;

    /** Tables métier recevant tenant_id pour isolation des données. */
    private const TENANT_SCOPED_TABLES = [
        'utilisateurs',
        'patients',
        'consultations',
        'rendez_vous',
        'paiements',
        'medecins',
        'analyses',
        'notifications',
        'parametres_systeme',
        'personnel',
        'medicaments',
        'budgets',
        'assurances',
        'dossiers',
        'documents_patients',
        'comptes_comptables',
        'ecritures_comptables',
        'equipements',
        'interventions_maintenance',
        'sejours_hospitalisation',
        'categories_hospitalisation',
        'tarifs_consultation',
        'soins_consultation',
        'tarifs_analyses_laboratoire',
        'annonces',
        'messages_internes',
        'system_logs',
        'connexions',
    ];

    /** Tables enfants liées à une table parent déjà scopée par tenant_id. */
    private const TENANT_CHILD_TABLES = [
        'contrats_assurance',
        'remboursements',
        'consultation_soins',
        'consultation_hospitalisation',
        'tickets_consultation',
        'horaires_personnel',
        'conges_personnel',
        'mouvements_stock_pharmacie',
    ];

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        $pdo = getDB();
        self::createTenantsTable($pdo);
        self::createSubscriptionOrdersTable($pdo);
        self::createSubscriptionInvoicesTable($pdo);
        require_once __DIR__ . '/../../models/TarifAnalyseLaboratoire.php';
        TarifAnalyseLaboratoire::ensureTable($pdo);
        self::ensureTenantCredentialColumns($pdo);
        self::addTenantIdColumns($pdo);
        self::addTenantIdColumns($pdo, self::TENANT_CHILD_TABLES);
        self::ensureBusinessColumns($pdo);
        require_once __DIR__ . '/PlatformTenantFeatures.php';
        PlatformTenantFeatures::ensureTable();
        self::ensurePermissionTables($pdo);
        self::ensureUtilisateurRoleEnum($pdo);
        self::ensureLicenseTypeEnum($pdo);
        self::ensureDefaultTenant($pdo);
    }

    /**
     * Unification complète après validation d'abonnement ou migration manuelle.
     */
    public static function finalizeIsolation(): void
    {
        self::ensure();
        self::migrateIndexes();
        self::backfillChildTenantIds(getDB());
        self::backfillOrphanRows();
    }

    public static function migrateIndexes(): void
    {
        $pdo = getDB();
        self::migrateUtilisateurUniqueIndexes($pdo);
        self::migrateParametresUniqueIndex($pdo);
        self::migrateBusinessUniqueIndexes($pdo);
    }

    /**
     * Index UNIQUE métier : (colonne, tenant_id) au lieu d'une unicité globale.
     */
    private static function migrateBusinessUniqueIndexes(PDO $pdo): void
    {
        $targets = [
            ['medecins', 'numero_ordre', 'uk_medecin_ordre_tenant'],
            ['patients', 'numero_dossier', 'uk_patient_dossier_tenant'],
            ['paiements', 'numero_facture', 'uk_paiement_facture_tenant'],
            ['comptes_comptables', 'numero_compte', 'uk_compte_numero_tenant'],
            ['contrats_assurance', 'numero_contrat', 'uk_contrat_numero_tenant'],
            ['ecritures_comptables', 'numero_ecriture', 'uk_ecriture_numero_tenant'],
            ['personnel', 'numero_employe', 'uk_personnel_employe_tenant'],
            ['equipements', 'numero_serie', 'uk_equipement_serie_tenant'],
            ['tarifs_analyses_laboratoire', 'code', 'uk_tarif_analyse_code_tenant'],
        ];
        foreach ($targets as [$table, $column, $newKey]) {
            self::migrateCompositeUniqueIndex($pdo, $table, $column, $newKey);
        }
    }

    private static function migrateCompositeUniqueIndex(PDO $pdo, string $table, string $column, string $newKeyName): void
    {
        if (!self::tableExists($pdo, $table) || !self::columnExists($pdo, $table, 'tenant_id')) {
            return;
        }
        if (!self::columnExists($pdo, $table, $column)) {
            return;
        }
        try {
            $indexes = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Non_unique = 0")->fetchAll(PDO::FETCH_ASSOC);
            $byKey = [];
            foreach ($indexes as $idx) {
                $byKey[$idx['Key_name']][] = $idx;
            }
            foreach ($byKey as $keyName => $cols) {
                if ($keyName === 'PRIMARY' || $keyName === $newKeyName) {
                    continue;
                }
                if (count($cols) === 1 && ($cols[0]['Column_name'] ?? '') === $column) {
                    $pdo->exec("ALTER TABLE `{$table}` DROP INDEX `{$keyName}`");
                }
            }
            $has = $pdo->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $pdo->quote($newKeyName))->fetch();
            if (!$has) {
                $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$newKeyName}` (`{$column}`, `tenant_id`)");
            }
        } catch (PDOException $e) {
            error_log("TenantSchema migrateCompositeUniqueIndex {$table}.{$column}: " . $e->getMessage());
        }
    }

    private static function createTenantsTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tenants (
                id INT NOT NULL AUTO_INCREMENT,
                tenant_key VARCHAR(64) NOT NULL,
                company_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                license_type ENUM('starter','annual','lifetime') NOT NULL DEFAULT 'annual',
                max_users INT UNSIGNED NOT NULL DEFAULT 15,
                expires_at DATE DEFAULT NULL,
                status ENUM('active','expired','suspended','cancelled') NOT NULL DEFAULT 'active',
                is_demo TINYINT(1) NOT NULL DEFAULT 0,
                auto_renew TINYINT(1) NOT NULL DEFAULT 1,
                last_renewal_reminder_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_tenant_key (tenant_key),
                KEY idx_status (status),
                KEY idx_expires (expires_at),
                KEY idx_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private static function createSubscriptionOrdersTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subscription_orders (
                id INT NOT NULL AUTO_INCREMENT,
                ref_command VARCHAR(64) NOT NULL,
                order_type ENUM('new','renewal','upgrade') NOT NULL DEFAULT 'new',
                license_type ENUM('starter','annual','lifetime') NOT NULL DEFAULT 'annual',
                amount_xof INT UNSIGNED NOT NULL,
                currency VARCHAR(8) NOT NULL DEFAULT 'XOF',
                payment_status ENUM('pending','paid','cancelled','failed') NOT NULL DEFAULT 'pending',
                payment_provider VARCHAR(32) NOT NULL DEFAULT 'mobile_money',
                company_name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(50) DEFAULT NULL,
                nom_utilisateur VARCHAR(100) DEFAULT NULL,
                password_hash VARCHAR(255) DEFAULT NULL,
                password_initial VARCHAR(255) DEFAULT NULL,
                nom_complet VARCHAR(255) DEFAULT NULL,
                tenant_id INT DEFAULT NULL,
                user_id INT DEFAULT NULL,
                ipn_payload LONGTEXT DEFAULT NULL,
                paid_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ref_command (ref_command),
                KEY idx_payment_status (payment_status),
                KEY idx_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private static function createSubscriptionInvoicesTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS subscription_invoices (
                id INT NOT NULL AUTO_INCREMENT,
                subscription_order_id INT NOT NULL,
                invoice_number VARCHAR(32) NOT NULL,
                amount_xof INT UNSIGNED NOT NULL,
                currency VARCHAR(8) NOT NULL DEFAULT 'XOF',
                buyer_company VARCHAR(255) NOT NULL,
                buyer_email VARCHAR(255) NOT NULL,
                buyer_phone VARCHAR(50) DEFAULT NULL,
                license_type ENUM('starter','annual','lifetime') NOT NULL DEFAULT 'annual',
                order_type ENUM('new','renewal','upgrade') NOT NULL DEFAULT 'new',
                ref_command VARCHAR(64) NOT NULL,
                tenant_id INT DEFAULT NULL,
                line_description VARCHAR(500) NOT NULL,
                seller_name VARCHAR(255) NOT NULL,
                seller_company VARCHAR(255) DEFAULT NULL,
                payment_method VARCHAR(64) DEFAULT 'mobile_money',
                issued_at DATETIME NOT NULL,
                status ENUM('issued','cancelled') NOT NULL DEFAULT 'issued',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_invoice_number (invoice_number),
                UNIQUE KEY uk_subscription_order (subscription_order_id),
                KEY idx_tenant (tenant_id),
                KEY idx_issued_at (issued_at),
                CONSTRAINT fk_sub_invoice_order FOREIGN KEY (subscription_order_id)
                    REFERENCES subscription_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Colonnes métier utilisées par le code mais absentes des anciens schémas.
     */
    private static function ensurePermissionTables(PDO $pdo): void
    {
        require_once __DIR__ . '/../tenant_permissions.php';
        TenantPermissions::ensureTables();
    }

    private static function ensureBusinessColumns(PDO $pdo): void
    {
        $columns = [
            ['analyses', 'fichier_image', 'VARCHAR(255) DEFAULT NULL AFTER resultats'],
            ['medecins', 'utilisateur_id', 'INT DEFAULT NULL AFTER id'],
            ['medecins', 'type_profil', "VARCHAR(32) NOT NULL DEFAULT 'medecin' AFTER utilisateur_id"],
            ['medecins', 'personnel_id', 'INT DEFAULT NULL AFTER type_profil'],
            ['personnel', 'utilisateur_id', 'INT DEFAULT NULL AFTER id'],
            ['patients', 'medecin_referent_id', 'INT DEFAULT NULL AFTER tenant_id'],
            ['analyses', 'technicien_id', 'INT DEFAULT NULL AFTER medecin_id'],
            ['consultation_soins', 'personnel_id', 'INT DEFAULT NULL AFTER soin_id'],
            ['paiements', 'analyse_id', 'INT DEFAULT NULL AFTER consultation_id'],
            ['paiements', 'ecriture_comptable_id', 'INT DEFAULT NULL AFTER analyse_id'],
            ['subscription_orders', 'product_line', "VARCHAR(32) NOT NULL DEFAULT 'clinical' AFTER order_type"],
        ];
        foreach ($columns as [$table, $column, $definition]) {
            if (!self::tableExists($pdo, $table) || self::columnExists($pdo, $table, $column)) {
                continue;
            }
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            } catch (PDOException $e) {
                error_log("TenantSchema ensureBusinessColumns {$table}.{$column}: " . $e->getMessage());
            }
        }
    }

    private static function ensureUtilisateurRoleEnum(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'utilisateurs') || !self::columnExists($pdo, 'utilisateurs', 'role')) {
            return;
        }
        $roles = "'admin','medecin','sage_femme','secretaire','infirmier','comptable','pharmacien','laborantin','major','technicien'";
        try {
            $pdo->exec(
                "ALTER TABLE utilisateurs MODIFY COLUMN role ENUM({$roles}) NOT NULL DEFAULT 'secretaire'"
            );
        } catch (PDOException $e) {
            error_log('TenantSchema ensureUtilisateurRoleEnum: ' . $e->getMessage());
        }
    }

    private static function ensureLicenseTypeEnum(PDO $pdo): void
    {
        $licenseTypes = "'starter','annual','lifetime'";
        $targets = [
            ['tenants', 'annual'],
            ['subscription_orders', 'annual'],
            ['subscription_invoices', 'annual'],
        ];
        foreach ($targets as [$table, $default]) {
            if (!self::tableExists($pdo, $table) || !self::columnExists($pdo, $table, 'license_type')) {
                continue;
            }
            try {
                $pdo->exec(
                    "ALTER TABLE `{$table}` MODIFY COLUMN license_type ENUM({$licenseTypes}) NOT NULL DEFAULT '{$default}'"
                );
            } catch (PDOException $e) {
                error_log("TenantSchema ensureLicenseTypeEnum {$table}: " . $e->getMessage());
            }
        }
    }

    private static function ensureTenantCredentialColumns(PDO $pdo): void
    {
        if (self::tableExists($pdo, 'subscription_orders') && !self::columnExists($pdo, 'subscription_orders', 'password_initial')) {
            try {
                $pdo->exec('ALTER TABLE subscription_orders ADD COLUMN password_initial VARCHAR(255) DEFAULT NULL AFTER password_hash');
            } catch (PDOException $e) {
                error_log('TenantSchema password_initial: ' . $e->getMessage());
            }
        }
        if (self::tableExists($pdo, 'tenants') && !self::columnExists($pdo, 'tenants', 'admin_login_password')) {
            try {
                $pdo->exec('ALTER TABLE tenants ADD COLUMN admin_login_password VARCHAR(255) DEFAULT NULL AFTER email');
            } catch (PDOException $e) {
                error_log('TenantSchema admin_login_password: ' . $e->getMessage());
            }
        }
    }

    private static function backfillChildTenantIds(PDO $pdo): void
    {
        $links = [
            ['contrats_assurance', 'assurances', 'assurance_id', 'id'],
            ['remboursements', 'contrats_assurance', 'contrat_id', 'id'],
            ['consultation_soins', 'consultations', 'consultation_id', 'id'],
            ['consultation_hospitalisation', 'consultations', 'consultation_id', 'id'],
            ['tickets_consultation', 'consultations', 'consultation_id', 'id'],
            ['horaires_personnel', 'personnel', 'personnel_id', 'id'],
            ['conges_personnel', 'personnel', 'personnel_id', 'id'],
            ['mouvements_stock_pharmacie', 'medicaments', 'medicament_id', 'id'],
            ['connexions', 'utilisateurs', 'utilisateur_id', 'id'],
            ['system_logs', 'utilisateurs', 'user_id', 'id'],
        ];
        foreach ($links as [$child, $parent, $childFk, $parentPk]) {
            if (!self::tableExists($pdo, $child) || !self::columnExists($pdo, $child, 'tenant_id')) {
                continue;
            }
            if (!self::tableExists($pdo, $parent) || !self::columnExists($pdo, $parent, 'tenant_id')) {
                continue;
            }
            try {
                $pdo->exec("
                    UPDATE `{$child}` c
                    INNER JOIN `{$parent}` p ON c.`{$childFk}` = p.`{$parentPk}`
                    SET c.tenant_id = p.tenant_id
                    WHERE c.tenant_id IS NULL AND p.tenant_id IS NOT NULL
                ");
            } catch (PDOException $e) {
                error_log("TenantSchema backfillChild {$child}: " . $e->getMessage());
            }
        }
    }

    private static function addTenantIdColumns(PDO $pdo, ?array $tables = null): void
    {
        foreach ($tables ?? self::TENANT_SCOPED_TABLES as $table) {
            if (!self::tableExists($pdo, $table)) {
                continue;
            }
            if (!self::columnExists($pdo, $table, 'tenant_id')) {
                try {
                    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN tenant_id INT DEFAULT NULL AFTER id");
                    $pdo->exec("ALTER TABLE `{$table}` ADD KEY idx_{$table}_tenant (tenant_id)");
                } catch (PDOException $e) {
                    error_log("TenantSchema: tenant_id sur {$table}: " . $e->getMessage());
                }
            }
        }

        if (self::tableExists($pdo, 'utilisateurs') && !self::columnExists($pdo, 'utilisateurs', 'is_platform_admin')) {
            try {
                $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN is_platform_admin TINYINT(1) NOT NULL DEFAULT 0");
            } catch (PDOException $e) {
                error_log('TenantSchema: is_platform_admin: ' . $e->getMessage());
            }
        }
    }

    private static function migrateUtilisateurUniqueIndexes(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'utilisateurs') || !self::columnExists($pdo, 'utilisateurs', 'tenant_id')) {
            return;
        }
        try {
            $indexes = $pdo->query("SHOW INDEX FROM utilisateurs WHERE Non_unique = 0 AND Key_name != 'PRIMARY'")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($indexes as $idx) {
                $key = $idx['Key_name'];
                if (in_array($key, ['nom_utilisateur', 'email'], true)) {
                    $pdo->exec("ALTER TABLE utilisateurs DROP INDEX `{$key}`");
                }
            }
            $hasComposite = $pdo->query("SHOW INDEX FROM utilisateurs WHERE Key_name = 'uk_user_tenant'")->fetch();
            if (!$hasComposite) {
                $pdo->exec('ALTER TABLE utilisateurs ADD UNIQUE KEY uk_user_tenant (nom_utilisateur, tenant_id)');
            }
            $hasEmailComposite = $pdo->query("SHOW INDEX FROM utilisateurs WHERE Key_name = 'uk_email_tenant'")->fetch();
            if (!$hasEmailComposite) {
                $pdo->exec('ALTER TABLE utilisateurs ADD UNIQUE KEY uk_email_tenant (email, tenant_id)');
            }
        } catch (PDOException $e) {
            error_log('TenantSchema migrateUtilisateurUniqueIndexes: ' . $e->getMessage());
        }
    }

    private static function migrateParametresUniqueIndex(PDO $pdo): void
    {
        if (!self::tableExists($pdo, 'parametres_systeme') || !self::columnExists($pdo, 'parametres_systeme', 'tenant_id')) {
            return;
        }
        try {
            $hasCleUnique = $pdo->query("SHOW INDEX FROM parametres_systeme WHERE Key_name = 'cle' AND Non_unique = 0")->fetch();
            if ($hasCleUnique) {
                $pdo->exec('ALTER TABLE parametres_systeme DROP INDEX `cle`');
            }
            $hasComposite = $pdo->query("SHOW INDEX FROM parametres_systeme WHERE Key_name = 'uk_cle_tenant'")->fetch();
            if (!$hasComposite) {
                $pdo->exec('ALTER TABLE parametres_systeme ADD UNIQUE KEY uk_cle_tenant (cle, tenant_id)');
            }
        } catch (PDOException $e) {
            error_log('TenantSchema migrateParametresUniqueIndex: ' . $e->getMessage());
        }
    }

    /**
     * Rattache les lignes orphelines (tenant_id NULL) — à appeler via migrate_saas_multitenant.php.
     */
    public static function backfillOrphanRows(?int $tenantId = null): void
    {
        $pdo = getDB();
        if ($tenantId === null) {
            $tenantId = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
        }
        if ($tenantId < 1) {
            return;
        }
        self::backfillTenantIds($pdo, $tenantId);
        self::backfillChildTenantIds($pdo);
    }

    private static function ensureDefaultTenant(PDO $pdo): void
    {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $nom = 'Établissement par défaut';
        try {
            $stmt = $pdo->query("SELECT valeur FROM parametres_systeme WHERE cle = 'nom_etablissement' LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if ($row && !empty($row['valeur'])) {
                $nom = $row['valeur'];
            }
        } catch (PDOException $e) {
            // ignore
        }

        $key = 'EFS-DEFAULT-' . strtoupper(bin2hex(random_bytes(2)));
        $expires = date('Y-m-d', strtotime('+10 years'));

        $stmt = $pdo->prepare("
            INSERT INTO tenants (tenant_key, company_name, email, license_type, max_users, expires_at, status, is_demo)
            VALUES (?, ?, 'admin@efficasante.local', 'lifetime', 50, NULL, 'active', 0)
        ");
        $stmt->execute([$key, $nom]);

        self::backfillTenantIds($pdo, 1);
    }

    private static function backfillTenantIds(PDO $pdo, int $tenantId): void
    {
        foreach (self::TENANT_SCOPED_TABLES as $table) {
            if (!self::tableExists($pdo, $table) || !self::columnExists($pdo, $table, 'tenant_id')) {
                continue;
            }
            try {
                $pdo->exec("UPDATE `{$table}` SET tenant_id = {$tenantId} WHERE tenant_id IS NULL");
            } catch (PDOException $e) {
                error_log("TenantSchema backfill {$table}: " . $e->getMessage());
            }
        }
    }

    private static function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return string[] */
    public static function getScopedTables(): array
    {
        return array_merge(self::TENANT_SCOPED_TABLES, self::TENANT_CHILD_TABLES);
    }
}
