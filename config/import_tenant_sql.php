<?php
/**
 * Importe un dump SQL d'une clinique vers UN tenant sans modifier les autres.
 *
 * Le dump doit provenir d'une installation Efficasante / Se.Santé (même schéma).
 * Seules les requêtes INSERT sont traitées ; CREATE/DROP/TRUNCATE du fichier sont ignorés.
 *
 * Usage :
 *   php config/import_tenant_sql.php --tenant-id=5 --file=C:\backups\clinique.sql --dry-run
 *   php config/import_tenant_sql.php --tenant-id=5 --file=C:\backups\clinique.sql --confirm
 *
 * Workflow complet :
 *   1. php config/provision_tenant.php --company="..." --email=... --username=... --password=...
 *   2. php config/import_tenant_sql.php --tenant-id=<id> --file=dump.sql --dry-run
 *   3. php config/import_tenant_sql.php --tenant-id=<id> --file=dump.sql --confirm
 *   4. php config/backfill_medecins_type_profil.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';
require_once __DIR__ . '/../includes/saas/TenantSqlImporter.php';

function itArg(array $argv, string $name, ?string $default = null): ?string
{
    $prefix = '--' . $name . '=';
    foreach ($argv as $arg) {
        if (strpos($arg, $prefix) === 0) {
            return substr($arg, strlen($prefix));
        }
    }
    return $default;
}

$tenantId = (int) itArg($argv, 'tenant-id', '0');
$file = trim((string) itArg($argv, 'file', ''));
$dryRun = in_array('--dry-run', $argv, true);
$confirm = in_array('--confirm', $argv, true);
$analyze = in_array('--analyze', $argv, true);

if ($tenantId < 1 || $file === '') {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php config/import_tenant_sql.php --tenant-id=5 --file=C:\\dump.sql [--dry-run|--analyze|--confirm]\n");
    exit(1);
}

if (!$dryRun && !$confirm && !$analyze) {
    fwrite(STDERR, "Ajoutez --dry-run pour simuler, --analyze pour compter les INSERT, ou --confirm pour importer.\n");
    exit(1);
}

if (!is_file($file)) {
    fwrite(STDERR, "Fichier introuvable : {$file}\n");
    exit(1);
}

TenantSchema::ensure();
$pdo = getDB();

$tenant = $pdo->prepare('SELECT id, company_name, tenant_key FROM tenants WHERE id = ?');
$tenant->execute([$tenantId]);
$tenantRow = $tenant->fetch(PDO::FETCH_ASSOC);
if (!$tenantRow) {
    fwrite(STDERR, "Tenant #{$tenantId} introuvable. Créez-le d'abord avec config/provision_tenant.php\n");
    exit(1);
}

echo "Import SQL → tenant #{$tenantId} ({$tenantRow['company_name']})\n";
echo "Fichier : {$file}\n";
echo "Les autres établissements ne seront pas modifiés.\n\n";

try {
    $importer = new TenantSqlImporter($pdo, $tenantId, $file);

    if ($analyze || $dryRun) {
        $details = $importer->analyzeDetails();
        if ($details === []) {
            echo "Aucune table importable détectée dans le dump.\n";
            exit(1);
        }
        echo "Tables détectées dans le dump :\n";
        $totalInserts = 0;
        $totalRows = 0;
        foreach ($details as $table => $d) {
            $line = sprintf('  - %-28s %d INSERT, %d ligne(s)', $table, $d['inserts'], $d['rows']);
            if (in_array($table, ['patients', 'medecins'], true)) {
                $actif = (int) ($d['actif'] ?? 0);
                $supprime = (int) ($d['supprime'] ?? 0);
                if ($actif > 0 || $supprime > 0) {
                    $line .= sprintf(' (%d actif(s), %d supprimé(s))', $actif, $supprime);
                }
            }
            echo $line . "\n";
            $totalInserts += $d['inserts'];
            $totalRows += $d['rows'];
        }
        echo "\nTotal : {$totalInserts} requête(s) INSERT, {$totalRows} ligne(s)\n";
        if ($analyze) {
            exit(0);
        }
        echo "\nSimulation (dry-run) — aucune écriture.\n";
        exit(0);
    }

    echo "Import en cours...\n";
    $ok = $importer->run(false);
    foreach ($importer->getLog() as $line) {
        echo $line . PHP_EOL;
    }

    $stats = $importer->getStats();
    if ($stats !== []) {
        echo "\nRésumé import :\n";
        $sum = 0;
        foreach ($stats as $table => $n) {
            echo "  {$table} : {$n}\n";
            $sum += $n;
        }
        echo "Total lignes importées : {$sum}\n";
    }

    echo "\nPost-traitement recommandé :\n";
    echo "  php config/backfill_medecins_type_profil.php\n";
    echo "  php config/backfill_patient_referent.php\n";

    exit($ok ? 0 : 1);
} catch (Throwable $e) {
    fwrite(STDERR, 'ERREUR : ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
