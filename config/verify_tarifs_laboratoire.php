<?php
/**
 * Vérification : tarifs / types d'analyses laboratoire configurables.
 * Usage : php config/verify_tarifs_laboratoire.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/saas/TenantContext.php';
require_once $base . '/models/TarifAnalyseLaboratoire.php';
require_once $base . '/models/Analyse.php';

$ok = 0;
$fail = 0;

function vok(string $msg): void
{
    global $ok;
    $ok++;
    echo "OK  $msg\n";
}

function vfail(string $msg): void
{
    global $fail;
    $fail++;
    echo "FAIL  $msg\n";
}

echo "=== Vérification tarifs laboratoire ===\n\n";

TenantSchema::ensure();
$pdo = getDB();

$tableExists = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE()
     AND table_name = 'tarifs_analyses_laboratoire' LIMIT 1"
)->fetchColumn();
$tableExists ? vok('Table tarifs_analyses_laboratoire') : vfail('Table tarifs_analyses_laboratoire manquante');

$tenantCol = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE()
     AND table_name = 'tarifs_analyses_laboratoire' AND column_name = 'tenant_id' LIMIT 1"
)->fetchColumn();
$tenantCol ? vok('Colonne tenant_id') : vfail('Colonne tenant_id manquante');

$tenantId = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($tenantId < 1) {
    $tenantId = 1;
}
$_SESSION['tenant_id'] = $tenantId;
TenantContext::bindFromSession();

$tarifModel = new TarifAnalyseLaboratoire();
$tarifs = $tarifModel->getAll();
count($tarifs) >= 8
    ? vok('Au moins 8 types initialisés (' . count($tarifs) . ')')
    : vfail('Moins de 8 types après seed (' . count($tarifs) . ')');

$analyseModel = new Analyse();
$types = $analyseModel->getTypesAnalyses();
$prixMap = $analyseModel->getPrixParType();

count($types) >= 8 ? vok('Analyse::getTypesAnalyses() — ' . count($types) . ' entrées') : vfail('getTypesAnalyses() incomplet');
count($prixMap) >= 8 ? vok('Analyse::getPrixParType() — ' . count($prixMap) . ' prix') : vfail('getPrixParType() incomplet');

$defaults = TarifAnalyseLaboratoire::DEFAULT_TYPES;
$mismatch = 0;
foreach (array_keys($defaults) as $code) {
    if (!isset($types[$code])) {
        vfail("Type par défaut « {$code} » absent de getTypesAnalyses()");
        $mismatch++;
        continue;
    }
    $expected = (float) $defaults[$code]['prix'];
    $actual = (float) ($prixMap[$code] ?? -1);
    if (abs($expected - $actual) > 0.01) {
        vfail("Prix « {$code} » : attendu {$expected}, obtenu {$actual}");
        $mismatch++;
    }
}
if ($mismatch === 0) {
    vok('Cohérence types/prix par défaut (8 codes)');
}

$testCode = 'sang';
$viaTarif = TarifAnalyseLaboratoire::getPrixForCode($testCode);
$viaTypes = (float) ($prixMap[$testCode] ?? 0);
abs($viaTarif - $viaTypes) < 0.01
    ? vok("getPrixForCode('{$testCode}') = {$viaTarif}")
    : vfail("getPrixForCode incohérent pour {$testCode}");

$row = $tarifModel->getByCode('urine');
$row && (float) $row['prix'] === 3000.0
    ? vok('getByCode(urine) prix 3000')
    : vfail('getByCode(urine) incorrect');

$normalized = TarifAnalyseLaboratoire::normalizeCode('  Bio-Chimie Test ');
$normalized === 'bio_chimie_test'
    ? vok('normalizeCode()')
    : vfail("normalizeCode() → « {$normalized} »");

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
