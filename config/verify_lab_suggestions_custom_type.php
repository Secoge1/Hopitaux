<?php
/**
 * Vérifie que l'API suggestions accepte un type personnalisé (hors catalogue codé en dur).
 * Usage : php config/verify_lab_suggestions_custom_type.php [code]
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantContext.php';
require_once $base . '/models/TarifAnalyseLaboratoire.php';

$code = $argv[1] ?? 'serologie_test_' . date('His');
$ok = 0;
$fail = 0;

function vok(string $m): void { global $ok; $ok++; echo "OK  $m\n"; }
function vfail(string $m): void { global $fail; $fail++; echo "FAIL  $m\n"; }

echo "=== Vérification suggestions type personnalisé ===\n\n";

$tenantId = (int) (getDB()->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 1);
$_SESSION['tenant_id'] = $tenantId;
TenantContext::bindFromSession();

$model = new TarifAnalyseLaboratoire();
$existing = $model->getByCode($code);
if (!$existing) {
    $model->create([
        'code' => $code,
        'libelle' => 'Sérologie test auto',
        'prix' => 7500,
        'description' => 'Bilan sérologique pour vérification API',
        'ordre' => 999,
        'statut' => 'actif',
    ]);
    vok("Type test créé : {$code}");
} else {
    vok("Type test existant : {$code}");
}

$tarif = $model->getByCode($code);
if (!$tarif) {
    vfail('Tarif introuvable après création');
    exit(1);
}

$data = TarifAnalyseLaboratoire::buildSuggestionsBase($tarif);
!empty($data['suggestions']) ? vok('Fallback suggestions généré') : vfail('Fallback vide');
($data['title'] === 'Sérologie test auto' || $data['title'] === ($tarif['libelle'] ?? ''))
    ? vok('Titre = libellé configuré')
    : vfail('Titre incorrect : ' . ($data['title'] ?? ''));

$hardcodedOnly = ['sang', 'urine', 'imagerie'];
if (!in_array($code, $hardcodedOnly, true)) {
    vok('Type hors catalogue codé en dur');
}

// Nettoyage
$row = $model->getByCode($code);
if ($row && $model->countUsages($code) === 0) {
    $model->delete((int) $row['id']);
    vok('Type test supprimé');
}

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
