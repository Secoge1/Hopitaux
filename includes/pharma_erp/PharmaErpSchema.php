<?php
/**
 * Schéma PharmaPro ERP — migrations SQL idempotentes.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../saas/TenantContext.php';

class PharmaErpSchema
{
    private static bool $ensured = false;

    /** @var list<string> */
    private const MIGRATION_FILES = [
        '001_foundation.sql',
        '002_products_stock.sql',
        '003_sales_pos.sql',
        '004_purchases.sql',
        '005_accounting_syscohada.sql',
        '006_hr.sql',
        '007_his_sync.sql',
        '008_commercial_medical.sql',
        '009_clients_assets_returns.sql',
    ];

    public static function ensure(): void
    {
        if (self::$ensured) {
            return;
        }
        self::$ensured = true;

        $pdo = getDB();
        self::ensureMigrationTable($pdo);

        $baseDir = dirname(__DIR__, 2) . '/config/sql/pharma_erp';
        foreach (self::MIGRATION_FILES as $file) {
            $path = $baseDir . '/' . $file;
            if (!is_file($path)) {
                continue;
            }
            $version = pathinfo($file, PATHINFO_FILENAME);
            $versionKey = explode('_', $version)[0];
            if (self::isMigrationApplied($pdo, $versionKey)) {
                continue;
            }
            self::runSqlFile($pdo, $path);
        }
    }

    private static function ensureMigrationTable(PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS pe_schema_migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                version VARCHAR(32) NOT NULL,
                description VARCHAR(255) NOT NULL,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_pe_migration_version (version)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private static function isMigrationApplied(PDO $pdo, string $version): bool
    {
        $stmt = $pdo->prepare('SELECT 1 FROM pe_schema_migrations WHERE version = ? LIMIT 1');
        $stmt->execute([$version]);
        return (bool) $stmt->fetchColumn();
    }

    private static function runSqlFile(PDO $pdo, string $path): void
    {
        $sql = file_get_contents($path);
        if ($sql === false || trim($sql) === '') {
            return;
        }

        $sql = preg_replace('/^--.*$/m', '', $sql);
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        foreach ($statements as $statement) {
            if ($statement === '' || stripos($statement, 'SET ') === 0) {
                if (stripos($statement, 'SET ') === 0) {
                    $pdo->exec($statement);
                }
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                if (self::isIgnorableError($e)) {
                    continue;
                }
                throw $e;
            }
        }
    }

    private static function isIgnorableError(PDOException $e): bool
    {
        $code = (string) $e->getCode();
        $msg = $e->getMessage();
        if ($code === '42S01') {
            return true;
        }
        if (str_contains($msg, 'Duplicate key name') || str_contains($msg, 'already exists')) {
            return true;
        }
        return false;
    }

    /**
     * Provisionne une officine par défaut pour le tenant (si aucune).
     */
    public static function provisionDefaultPharmacy(?int $tenantId = null): ?int
    {
        self::ensure();

        if ($tenantId === null) {
            TenantContext::bindFromSession();
            $tenantId = TenantContext::getTenantId();
        }
        if (!$tenantId) {
            return null;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare(
            'SELECT id FROM pe_pharmacies WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY is_default DESC, id ASC LIMIT 1'
        );
        $stmt->execute([(int) $tenantId]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pe_pharmacies (tenant_id, code, name, is_default, status)
                VALUES (?, 'MAIN', 'Officine principale', 1, 'active')
            ");
            $stmt->execute([(int) $tenantId]);
            $pharmacyId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO pe_deposits (tenant_id, pharmacy_id, code, name, is_default, is_sales_source, status)
                VALUES (?, ?, 'STORE', 'Magasin principal', 1, 1, 'active')
            ");
            $stmt->execute([(int) $tenantId, $pharmacyId]);

            $stmt = $pdo->prepare("
                INSERT INTO pe_cash_registers (tenant_id, pharmacy_id, code, name, is_default, status)
                VALUES (?, ?, 'POS01', 'Caisse principale', 1, 'closed')
            ");
            $stmt->execute([(int) $tenantId, $pharmacyId]);

            $stmt = $pdo->prepare("
                INSERT INTO pe_document_sequences (tenant_id, pharmacy_id, document_type, prefix, next_number)
                VALUES (?, ?, 'sale', 'V', 1),
                       (?, ?, 'inventory', 'INV', 1),
                       (?, ?, 'purchase_order', 'BC', 1),
                       (?, ?, 'goods_receipt', 'BR', 1)
            ");
            $stmt->execute([
                (int) $tenantId, $pharmacyId,
                (int) $tenantId, $pharmacyId,
                (int) $tenantId, $pharmacyId,
                (int) $tenantId, $pharmacyId,
            ]);

            require_once __DIR__ . '/../../models/pharma_erp/PeAccountingEngine.php';
            PeAccountingEngine::ensureSeed((int) $tenantId);

            $pdo->commit();
            return $pharmacyId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
