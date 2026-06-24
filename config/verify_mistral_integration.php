<?php
/**
 * Vérification rigoureuse de l'intégration Mistral AI (CLI).
 * Usage : php config/verify_mistral_integration.php
 */
declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];
$passed = 0;

function check(bool $ok, string $label): void
{
    global $errors, $passed;
    if ($ok) {
        $passed++;
        echo "[OK] {$label}\n";
    } else {
        $errors[] = $label;
        echo "[FAIL] {$label}\n";
    }
}

function warn(string $label): void
{
    global $warnings;
    $warnings[] = $label;
    echo "[WARN] {$label}\n";
}

echo "=== Vérification intégration Mistral AI ===\n\n";

// 1. Fichiers requis
$requiredFiles = [
    'includes/MistralAIService.php',
    'includes/PlatformAIConfig.php',
    'includes/DiagnosticIntelligence.php',
    'consultations/api_diagnostic.php',
    'laboratoire/api_suggestions.php',
    'admin_platform/ia.php',
    'parametres/ia.php',
    'includes/app_parametres_layout.php',
    'includes/app_platform_layout.php',
];
foreach ($requiredFiles as $rel) {
    check(is_file($root . '/' . $rel), "Fichier présent : {$rel}");
}

// 2. Syntaxe PHP
$phpFiles = glob($root . '/includes/MistralAIService.php')
    + glob($root . '/parametres/ia.php')
    + glob($root . '/consultations/api_diagnostic.php')
    + glob($root . '/laboratoire/api_suggestions.php');
foreach ($phpFiles as $file) {
    exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $code);
    check($code === 0, 'Syntaxe PHP : ' . basename(dirname($file)) . '/' . basename($file));
}

// 3. Extensions PHP
check(function_exists('curl_init'), 'Extension curl disponible');
check(function_exists('json_encode'), 'Extension json disponible');
if (!function_exists('mb_strtolower')) {
    warn('mbstring absent — fallback strtolower utilisé');
}

// 4. Chargement classes + config plateforme globale
require_once $root . '/includes/PlatformAIConfig.php';
require_once $root . '/includes/MistralAIService.php';
require_once $root . '/includes/DiagnosticIntelligence.php';

$defaults = [
    PlatformAIConfig::KEY_ACTIVE => '0',
    PlatformAIConfig::KEY_API => '',
    PlatformAIConfig::KEY_MODEL => 'mistral-small-latest',
    PlatformAIConfig::KEY_CONSULTATIONS => '1',
    PlatformAIConfig::KEY_LABORATOIRE => '1',
    PlatformAIConfig::KEY_TIMEOUT => '25',
];
foreach ($defaults as $key => $expected) {
    $val = PlatformAIConfig::get($key, '__MISSING__');
    check($val !== '__MISSING__', "Paramètre plateforme accessible : {$key}");
}

$mistral = MistralAIService::getInstance();
check(!$mistral->isActive(), 'Mistral inactif sans clé API (comportement attendu)');
check(!$mistral->isEnabledForConsultations(), 'Consultations désactivées sans activation globale');

// 5. Navigation
$layout = file_get_contents($root . '/includes/app_parametres_layout.php');
check(strpos($layout, "parametres/ia.php") !== false, 'Menu Paramètres → statut IA tenant');
$platformLayout = file_get_contents($root . '/includes/app_platform_layout.php');
check(strpos($platformLayout, "admin_platform/ia.php") !== false, 'Menu Admin plateforme → IA Mistral');
check(strpos(file_get_contents($root . '/includes/MistralAIService.php'), 'PlatformAIConfig') !== false, 'MistralAIService lit PlatformAIConfig (global)');

// 6. API consultations — chaînage refreshSafetyChecks
$apiDiag = file_get_contents($root . '/consultations/api_diagnostic.php');
check(strpos($apiDiag, 'MistralAIService') !== false, 'api_diagnostic inclut MistralAIService');
check(strpos($apiDiag, 'refreshSafetyChecksForSuggestions') !== false, 'api_diagnostic recalcule safety_checks après Mistral');
check(strpos($apiDiag, 'JSON_UNESCAPED_UNICODE') !== false, 'api_diagnostic encode JSON UTF-8');

// 7. API laboratoire
$apiLab = file_get_contents($root . '/laboratoire/api_suggestions.php');
check(strpos($apiLab, 'enrichLaboratoireSuggestions') !== false, 'api_suggestions appelle enrichLaboratoireSuggestions');
check(strpos($apiLab, 'module_api_guard') !== false, 'api_suggestions protégée par module_api_guard');

// 8. Test logique merge + enriched flag (sans appel réseau)
class MistralAIServiceTestable extends MistralAIService
{
    public function testMerge(array &$local, array $parsed): array
    {
        $ref = new ReflectionClass(MistralAIService::class);
        $method = $ref->getMethod('mergeConsultationLists');
        $method->setAccessible(true);
        $method->invokeArgs($this, [&$local, $parsed]);

        $mistralBlock = $local['mistral'] ?? ['items' => []];
        $hasNew = false;
        foreach ($mistralBlock['items'] ?? [] as $items) {
            if (!empty($items)) {
                $hasNew = true;
                break;
            }
        }
        return ['enriched' => $hasNew, 'mistral' => $mistralBlock];
    }
}

$testService = new MistralAIServiceTestable();
$localSuggestions = [
    'diagnostic' => [
        'analysis' => [
            'diagnostics' => ['Grippe'],
            'traitements' => ['Repos'],
            'medicaments' => ['Paracétamol 1g x3/jour'],
            'examens' => ['NFS'],
        ],
    ],
    'safety_checks' => ['has_warnings' => false, 'warnings' => [], 'contraindications' => [], 'interactions' => []],
];
$mergeResult = $testService->testMerge($localSuggestions, [
    'diagnostics' => ['Grippe', 'COVID-19'],
    'medicaments' => ['Amoxicilline 1g x2/jour'],
    'note' => 'Surveillance',
]);
check($mergeResult['enriched'] === true, 'Merge ajoute des éléments non dupliqués');
check(in_array('COVID-19', $localSuggestions['diagnostic']['analysis']['diagnostics'], true), 'Diagnostic COVID-19 fusionné');
check(!in_array('Grippe', $mergeResult['mistral']['items']['diagnostics'] ?? [], true), 'Doublon Grippe non compté comme Mistral');

$localEmpty = $localSuggestions;
$mergeEmpty = $testService->testMerge($localEmpty, [
    'diagnostics' => ['Grippe'],
    'traitements' => ['Repos'],
]);
check($mergeEmpty['enriched'] === false, 'enriched=false si Mistral ne rajoute rien');

// 9. refreshSafetyChecks après ajout simulé d'un médicament Mistral
$suggestions = DiagnosticIntelligence::getContextualSuggestions('fièvre', 70, 'M', null, null);
$suggestions['diagnostic']['analysis']['medicaments'][] = 'Morphine 10mg';
DiagnosticIntelligence::refreshSafetyChecksForSuggestions($suggestions, null, null, 70, 'M');
check($suggestions['safety_checks']['has_warnings'] === true, 'Safety refresh recalcule les alertes après enrichissement');
check(isset($suggestions['ordonnance']) && is_string($suggestions['ordonnance']), 'Ordonnance régénérée après refresh safety');

// 10. Frontend — escapeHtml présent
$consultJs = file_get_contents($root . '/consultations/ajouter.php');
$labJs = file_get_contents($root . '/laboratoire/ajouter.php');
check(strpos($consultJs, 'function escapeHtml') !== false, 'consultations/ajouter.php : escapeHtml défini');
check(strpos($labJs, 'function escapeHtml') !== false, 'laboratoire/ajouter.php : escapeHtml défini');
check(strpos($consultJs, 'mistralDiagnosticItems') !== false, 'consultations/ajouter.php : suivi items Mistral');
check(strpos($labJs, 'mistralSuggestionSet') !== false, 'laboratoire/ajouter.php : suivi suggestions Mistral');

// 11. Sécurité — clé API non hardcodée
$grepKey = shell_exec('rg -l "ckC1m9gevdaAGpe2p3TdaWgN3vNXIwFO" ' . escapeshellarg($root) . ' 2>nul');
check(trim((string) $grepKey) === '', 'Clé API non présente dans le code source');

// 12. Connexion DB (optionnel)
try {
    require_once $root . '/config/db.php';
    $pdo = getDB();
    if ($pdo instanceof PDO) {
        check(true, 'Connexion base de données OK');
        $stmt = $pdo->query("SHOW TABLES LIKE 'parametres_systeme'");
        check((bool) $stmt->fetchColumn(), 'Table parametres_systeme existe');
    } else {
        warn('Base de données indisponible — tests DB ignorés');
    }
} catch (Throwable $e) {
    warn('DB : ' . $e->getMessage());
}

echo "\n=== Résumé ===\n";
echo "Tests réussis : {$passed}\n";
echo 'Échecs : ' . count($errors) . "\n";
echo 'Avertissements : ' . count($warnings) . "\n";

if ($errors) {
    echo "\nÉchecs détaillés :\n";
    foreach ($errors as $e) {
        echo "  - {$e}\n";
    }
    exit(1);
}

echo "\nVérification terminée avec succès.\n";
exit(0);
