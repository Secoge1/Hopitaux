<?php
/**
 * Migration SaaS CLI — indexes composites + backfill orphelins.
 * Usage : php config/run_saas_migration.php
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';

echo "Migration SaaS multi-tenant...\n";

try {
    TenantSchema::ensure();
    echo "[OK] Schéma tenants\n";

    TenantSchema::migrateIndexes();
    echo "[OK] Index composites (utilisateurs, parametres_systeme)\n";

    TenantSchema::backfillOrphanRows();
    echo "[OK] Backfill lignes orphelines (tenant_id NULL)\n";

    echo "\nMigration terminée.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Erreur : " . $e->getMessage() . "\n");
    exit(1);
}
