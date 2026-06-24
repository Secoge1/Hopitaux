<?php
/**
 * Vérification isolation multi-tenant + sécurité modules — CLI.
 *
 * Usage : php config/verify_tenant_modules.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/saas/TenantScope.php';
require_once $base . '/includes/module_guard.php';

$passed = 0;
$failed = 0;
$warnings = 0;

function tok(string $msg): void {
    global $passed;
    $passed++;
    echo "[OK] $msg\n";
}

function tfail(string $msg): void {
    global $failed;
    $failed++;
    echo "[FAIL] $msg\n";
}

function twarn(string $msg): void {
    global $warnings;
    $warnings++;
    echo "[WARN] $msg\n";
}

echo "=== Vérification modules multi-tenant SeSanté ===\n\n";

// 1. Fichiers infrastructure
$infra = [
    'includes/saas/TenantScope.php',
    'includes/module_guard.php',
];
foreach ($infra as $file) {
    if (is_file($base . '/' . $file)) {
        tok("Fichier $file présent");
    } else {
        tfail("Fichier $file manquant");
    }
}

// 2. Modèles utilisant TenantScope
$models = [
    'Patient.php', 'Consultation.php', 'Medecin.php', 'RendezVous.php',
    'Paiement.php', 'Analyse.php', 'Personnel.php', 'Assurance.php', 'Medicament.php',
    'Finances.php', 'Dossier.php', 'Communication.php', 'Maintenance.php',
    'SejourHospitalisation.php', 'SoinsConsultation.php', 'TarifConsultation.php',
    'CategorieHospitalisation.php', 'Utilisateur.php',
];
foreach ($models as $model) {
    $path = $base . '/models/' . $model;
    if (!is_file($path)) {
        twarn("Modèle $model absent");
        continue;
    }
    $content = file_get_contents($path);
    if (strpos($content, 'TenantScope') !== false) {
        tok("Modèle $model — filtre tenant intégré");
    } else {
        tfail("Modèle $model — TenantScope non utilisé");
    }
}

// 3. APIs sécurisées (init + module_guard ou module_api_guard)
$apis = [
    'patients/api_suggestions.php'       => 'patients',
    'patients/api_risk_analysis.php'     => 'patients',
    'patients/ajax_supprimer.php'        => 'patients',
    'consultations/api_suggestions.php'  => 'consultations',
    'consultations/api_diagnostic.php'   => 'consultations',
    'rendez-vous/api_suggestions.php'    => 'rdv',
    'rendez-vous/api_rdv.php'            => 'rdv',
    'laboratoire/api_suggestions.php'    => 'laboratoire',
    'paiements/api_consultations.php'    => 'paiements',
];
foreach ($apis as $api => $module) {
    $path = $base . '/' . $api;
    if (!is_file($path)) {
        twarn("API $api absente");
        continue;
    }
    $content = file_get_contents($path);
    $hasInit = strpos($content, 'init.php') !== false;
    $hasGuard = strpos($content, 'module_guard.php') !== false || strpos($content, 'module_api_guard') !== false;
    $hasCorsWildcard = strpos($content, 'Access-Control-Allow-Origin: *') !== false;

    if ($hasInit && $hasGuard) {
        tok("API $api — auth + garde module");
    } else {
        tfail("API $api — init/guard manquant (init=" . ($hasInit ? 'oui' : 'non') . ', guard=' . ($hasGuard ? 'oui' : 'non') . ')');
    }
    if ($hasCorsWildcard) {
        tfail("API $api — CORS ouvert (*)");
    }
}

// 4. Pages index modules — garde rôles (module_require_roles ou app_module_context)
$indexes = [
    'patients/index.php'      => ['module_require_roles', 'app_module_context'],
    'medecins/index.php'      => ['module_require_roles', 'app_module_context'],
    'consultations/index.php' => ['module_require_roles', 'app_module_context'],
    'paiements/index.php'     => ['module_require_roles', 'app_module_context'],
    'personnel/index.php'     => ['module_require_roles', 'app_module_context'],
    'pharmacie/index.php'     => ['module_require_roles', 'app_module_context'],
    'rendez-vous/index.php'   => ['module_require_roles', 'app_module_context'],
    'laboratoire/index.php'   => ['module_require_roles', 'app_module_context'],
    'finances/index.php'      => ['module_require_roles', 'app_module_context'],
    'assurances/index.php'    => ['module_require_roles', 'app_module_context'],
    'communication/index.php' => ['module_require_roles', 'app_module_context'],
    'maintenance/index.php'   => ['module_require_roles', 'app_module_context'],
    'dossiers/index.php'      => ['module_require_roles', 'app_module_context'],
];
foreach ($indexes as $page => $needles) {
    $path = $base . '/' . $page;
    if (!is_file($path)) {
        twarn("Page $page absente");
        continue;
    }
    $content = file_get_contents($path);
    $hasGuard = false;
    foreach ($needles as $needle) {
        if (strpos($content, $needle) !== false) {
            $hasGuard = true;
            break;
        }
    }
    $hasShell = strpos($content, 'app_module_layout.php') !== false && strpos($content, 'app_module_page_start') !== false;
    if ($hasGuard && $hasShell) {
        tok("Page $page — shell SeSanté + contrôle rôles");
    } elseif ($hasGuard) {
        twarn("Page $page — rôles OK mais shell app_module absent");
    } else {
        tfail("Page $page — garde rôles / shell manquant");
    }
}

// 5. Schéma + données orphelines tenant_id
TenantSchema::ensure();
$pdo = getDB();

foreach (TenantSchema::getScopedTables() as $table) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    if (!$stmt->fetchColumn()) {
        continue;
    }
    $nullCount = (int) $pdo->query("SELECT COUNT(*) FROM `$table` WHERE tenant_id IS NULL")->fetchColumn();
    if ($nullCount === 0) {
        tok("Table `$table` — aucune ligne sans tenant_id");
    } elseif ($table === 'parametres_systeme') {
        tok("Table `$table` — $nullCount paramètre(s) global(aux) (tenant_id NULL = défauts plateforme)");
    } else {
        twarn("Table `$table` — $nullCount ligne(s) sans tenant_id");
    }
}

// SystemParameters tenant-aware
$spContent = file_get_contents($base . '/config/SystemParameters.php');
if (strpos($spContent, 'resolveTenantId') !== false && strpos($spContent, 'tenant_id IS NULL') !== false) {
    tok('SystemParameters — chargement par tenant + défauts globaux');
} else {
    tfail('SystemParameters — isolation tenant non détectée');
}

if (strpos(file_get_contents($base . '/includes/saas/saas_helpers.php'), 'saas_require_tenant_context') !== false) {
    tok('saas_require_tenant_context — enforcement session');
} else {
    tfail('saas_require_tenant_context manquant');
}

// 6. Test fonctionnel TenantScope (simulation session tenant #1)
$defaultTenant = $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($defaultTenant) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['tenant_id'] = (int) $defaultTenant;

    require_once $base . '/models/Patient.php';
    $patientModel = new Patient();
    $countScoped = $patientModel->getCount();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM patients WHERE tenant_id = ? AND (statut IS NULL OR statut <> \'supprime\')');
    $stmt->execute([(int) $defaultTenant]);
    $countDb = (int) $stmt->fetchColumn();

    if ($countScoped === $countDb) {
        tok("Patient::getCount() cohérent avec tenant #$defaultTenant ($countScoped)");
    } else {
        tfail("Patient::getCount()=$countScoped vs BDD tenant=$countDb");
    }

    // Vérifier qu'un ID d'un autre tenant (si existe) n'est pas accessible
    $other = $pdo->query(
        'SELECT id, tenant_id FROM patients WHERE tenant_id IS NOT NULL AND tenant_id != ' . (int) $defaultTenant . ' LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    if ($other) {
        $foreign = $patientModel->getById((int) $other['id']);
        if ($foreign === false || $foreign === null) {
            tok('Patient::getById() bloque un patient d\'un autre tenant');
        } else {
            tfail('Fuite tenant — getById() a retourné un patient étranger #' . $other['id']);
        }
    } else {
        twarn('Pas de second tenant pour test de fuite croisée (ignoré)');
    }
} else {
    tfail('Aucun tenant en base pour tests fonctionnels');
}

// 7. MODULE_ROLES aligné avec app_nav_items
$expectedModules = ['patients', 'medecins', 'rdv', 'consultations', 'laboratoire', 'paiements'];
foreach ($expectedModules as $key) {
    if (isset(MODULE_ROLES[$key]) && count(MODULE_ROLES[$key]) > 0) {
        tok("MODULE_ROLES['$key'] défini");
    } else {
        tfail("MODULE_ROLES['$key'] manquant");
    }
}

echo "\n=== Résumé ===\n";
echo "OK: $passed | FAIL: $failed | WARN: $warnings\n";
exit($failed > 0 ? 1 : 0);
