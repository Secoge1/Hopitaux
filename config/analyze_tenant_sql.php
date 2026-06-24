<?php
/**
 * Analyse un dump SQL (INSERT + lignes par table).
 * Usage : php config/analyze_tenant_sql.php "C:\path\file.sql"
 */
declare(strict_types=1);

$file = $argv[1] ?? '';
if ($file === '' || !is_readable($file)) {
    fwrite(STDERR, "Usage: php config/analyze_tenant_sql.php \"C:\\chemin\\dump.sql\"\n");
    exit(1);
}

require_once __DIR__ . '/../includes/saas/TenantSqlImporter.php';

$importer = new TenantSqlImporter(null, 1, $file);
$details = $importer->analyzeDetails();

$sizeMb = round(filesize($file) / 1024 / 1024, 2);
echo "Fichier : {$file}\n";
echo "Taille  : {$sizeMb} Mo\n";
echo "tenant_id dans le dump : " . (strpos(file_get_contents($file, false, null, 0, 500000), 'tenant_id') !== false ? 'oui' : 'non (ancien schéma mono-établissement)') . "\n\n";

if ($details === []) {
    echo "Aucun INSERT importable détecté.\n";
    exit(1);
}

$totalInserts = 0;
$totalRows = 0;
foreach ($details as $table => $d) {
    $line = str_pad($table, 32) . $d['inserts'] . ' INSERT, ' . $d['rows'] . ' ligne(s)';
    if (in_array($table, ['patients', 'medecins'], true)) {
        $actif = (int) ($d['actif'] ?? 0);
        $supprime = (int) ($d['supprime'] ?? 0);
        if ($actif > 0 || $supprime > 0) {
            $line .= " ({$actif} actif(s), {$supprime} supprimé(s))";
        }
    }
    echo $line . "\n";
    $totalInserts += $d['inserts'];
    $totalRows += $d['rows'];
}

echo "\nTotal : {$totalInserts} requête(s) INSERT, {$totalRows} ligne(s)\n";
