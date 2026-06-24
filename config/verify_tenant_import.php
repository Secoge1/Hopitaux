<?php
/**
 * Vérification import tenant SQL + interface Paramètres.
 * Usage : php config/verify_tenant_import.php
 */
declare(strict_types=1);

$base = dirname(__DIR__);
$passed = 0;
$failed = 0;

function vp(string $label, bool $ok): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "[OK] {$label}\n";
    } else {
        $failed++;
        echo "[FAIL] {$label}\n";
    }
}

echo "=== Vérification import tenant ===\n\n";

$files = [
    'includes/saas/TenantSqlImporter.php',
    'includes/tenant_data_import.php',
    'parametres/import_donnees.php',
    'config/import_tenant_sql.php',
    'config/provision_tenant.php',
    'config/analyze_tenant_sql.php',
];

foreach ($files as $f) {
    $path = $base . '/' . $f;
    vp("Fichier {$f}", is_file($path));
    if (is_file($path)) {
        exec('php -l ' . escapeshellarg($path) . ' 2>&1', $out, $code);
        vp("Syntaxe {$f}", $code === 0);
    }
}

require_once $base . '/includes/saas/TenantSqlImporter.php';
require_once $base . '/includes/tenant_data_import.php';

$layout = file_get_contents($base . '/includes/app_parametres_layout.php') ?: '';
vp('Nav Paramètres : Import de données', strpos($layout, 'import_donnees') !== false);

$index = file_get_contents($base . '/parametres/index.php') ?: '';
vp('Raccourci index Paramètres', strpos($index, 'import_donnees.php') !== false);

$importerSrc = file_get_contents($base . '/includes/saas/TenantSqlImporter.php') ?: '';
vp('Pas de backfillOrphanRows dans l\'import (sécurité multi-tenant)', strpos($importerSrc, 'backfillOrphanRows') === false);
vp('FK consultation_hospitalisation.categorie_hospitalisation_id', strpos($importerSrc, 'categorie_hospitalisation_id') !== false);

$backup = $base . '/backups/cp2640311p29_efficasante (1).sql';
if (is_file($backup)) {
    $fh = fopen($backup, 'rb');
    $buf = '';
    while ($fh && ($line = fgets($fh)) !== false) {
        $t = trim($line);
        if ($t === '' || strpos($t, '--') === 0 || strpos($t, '/*') === 0) {
            continue;
        }
        $buf .= $line;
        if (substr(rtrim($line), -1) === ';') {
            if (preg_match('/^INSERT\s+(?:IGNORE\s+INTO|INTO)\s+[`"]?(\w+)[`"]?/i', trim($buf), $m)) {
                $counts[strtolower($m[1])] = ($counts[strtolower($m[1])] ?? 0) + 1;
            }
            $buf = '';
        }
    }
    if ($fh) {
        fclose($fh);
    }
    vp('Backup sample : patients détectés', isset($counts['patients']) && $counts['patients'] >= 1);
    vp('Backup sample : medecins détectés', isset($counts['medecins']) && $counts['medecins'] >= 1);
    vp('Backup sample : consultations détectées', isset($counts['consultations']) && $counts['consultations'] >= 1);
} else {
    echo "[SKIP] Backup de test absent\n";
}

TenantDataImportWeb::ensureTenantUploadDir(99);
$dir = TenantDataImportWeb::tenantUploadDir(99);
$testFile = $dir . '/test_owned.sql';
file_put_contents($testFile, '-- test');
vp('assertPathOwnedByTenant accepte fichier du tenant', TenantDataImportWeb::assertPathOwnedByTenant(99, $testFile));
$outside = $base . '/backups/.htaccess';
if (is_file($outside)) {
    vp('assertPathOwnedByTenant rejette fichier hors dossier', !TenantDataImportWeb::assertPathOwnedByTenant(99, $outside));
}
@unlink($testFile);
@rmdir($dir);

try {
    require_once $base . '/config/db.php';
    $pdo = getDBSoft();
    if ($pdo instanceof PDO) {
        vp('Connexion BDD', true);
        require_once $base . '/includes/saas/TenantSchema.php';
        TenantSchema::ensure();
        if (is_file($backup)) {
            $probe = new TenantSqlImporter($pdo, 1, $backup);
            $analysis = $probe->analyze();
            vp('TenantSqlImporter::analyze()', $analysis !== [] && isset($analysis['patients']));

            $details = $probe->analyzeDetails();
            vp('analyzeDetails() : patients avec lignes', isset($details['patients']['rows']) && $details['patients']['rows'] >= 100);
            vp('analyzeDetails() : medecins actifs détectés', isset($details['medecins']['actif']) && $details['medecins']['actif'] >= 1);
            vp('Méthode analyzeDetails() présente', method_exists($probe, 'analyzeDetails'));
        }
    } else {
        echo "[SKIP] BDD indisponible (tests analyze/import BDD)\n";
    }
} catch (Throwable $e) {
    vp('Connexion BDD', false);
    echo "       " . $e->getMessage() . "\n";
}

echo "\n";
if ($failed === 0) {
    echo "Toutes les vérifications sont passées ({$passed} OK).\n";
    exit(0);
}
echo "{$failed} échec(s), {$passed} OK.\n";
exit(1);
