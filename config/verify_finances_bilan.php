<?php
/**
 * Vérification page bilan finances + Finances::getBilan().
 * Usage : php config/verify_finances_bilan.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = dirname(__DIR__);
$ok = 0;
$fail = 0;

function fbcheck(bool $cond, string $label): void
{
    global $ok, $fail;
    echo ($cond ? 'OK  ' : 'FAIL ') . "$label\n";
    $cond ? $ok++ : $fail++;
}

echo "=== Vérification bilan finances ===\n\n";

$files = [
    'finances/bilan.php',
    'models/Finances.php',
    'assets/css/app-finances.css',
    'finances/index.php',
    'finances/comptes.php',
    'finances/budgets.php',
];

foreach ($files as $rel) {
    fbcheck(is_file($base . '/' . $rel), "Fichier $rel");
}

$index = file_get_contents($base . '/finances/index.php') ?: '';
$comptes = file_get_contents($base . '/finances/comptes.php') ?: '';
$budgets = file_get_contents($base . '/finances/budgets.php') ?: '';
$bilanPhp = file_get_contents($base . '/finances/bilan.php') ?: '';
$model = file_get_contents($base . '/models/Finances.php') ?: '';
$css = file_get_contents($base . '/assets/css/app-finances.css') ?: '';

fbcheck(strpos($index, 'finances/bilan.php') !== false, 'Lien bilan dans index.php');
fbcheck(strpos($comptes, 'finances/bilan.php') !== false, 'Lien bilan dans comptes.php');
fbcheck(strpos($budgets, 'finances/bilan.php') !== false, 'Lien bilan dans budgets.php');
fbcheck(strpos($bilanPhp, "app_module_context('finances')") !== false, 'Garde module finances sur bilan.php');
fbcheck(strpos($bilanPhp, 'getBilan') !== false, 'Appel Finances::getBilan()');
fbcheck(strpos($model, 'function getBilan') !== false, 'Méthode getBilan dans le modèle');
fbcheck(strpos($css, 'fin-bilan-line') !== false, 'Styles CSS bilan');

require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/saas/TenantContext.php';
require_once $base . '/models/Finances.php';

TenantSchema::ensure();
$pdo = getDB();
$tenantId = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
$_SESSION['tenant_id'] = $tenantId;
TenantContext::setTenantId($tenantId);

$fin = new Finances();
$dateDebut = date('Y-01-01');
$dateFin = date('Y-m-d');

try {
    $bilan = $fin->getBilan($dateDebut, $dateFin);
    fbcheck(is_array($bilan), 'getBilan() retourne un tableau');
    foreach (['actifs', 'passifs', 'produits', 'charges', 'resultat'] as $key) {
        fbcheck(array_key_exists($key, $bilan), "Clé bilan « $key » présente");
        fbcheck(is_numeric($bilan[$key]), "Valeur « $key » numérique");
    }
    fbcheck(
        abs((float) $bilan['resultat'] - ((float) $bilan['produits'] - (float) $bilan['charges'])) < 0.01,
        'Résultat = produits - charges'
    );
} catch (Throwable $e) {
    fbcheck(false, 'getBilan() exécutable : ' . $e->getMessage());
}

require_once $base . '/includes/roles.php';
foreach (['admin', 'comptable', 'secretaire'] as $role) {
    fbcheck(in_array($role, app_module_roles('finances'), true), "Rôle $role autorisé sur finances");
}
fbcheck(!in_array('medecin', app_module_roles('finances'), true), 'Médecin exclu du module finances');

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
