<?php
/**
 * Vérification cohérence des rôles métier — CLI.
 * Usage : php config/verify_roles.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
require_once $base . '/includes/roles.php';
require_once $base . '/includes/module_guard.php';
require_once $base . '/includes/staff_link.php';

$passed = 0;
$failed = 0;

function rok(string $msg): void {
    global $passed;
    $passed++;
    echo "[OK] $msg\n";
}

function rfail(string $msg): void {
    global $failed;
    $failed++;
    echo "[FAIL] $msg\n";
}

echo "=== Vérification rôles SeSanté ===\n\n";

foreach (app_role_keys() as $role) {
    $mods = app_modules_for_role($role);
    if ($role === 'admin' || count($mods) > 0) {
        rok("Rôle « {$role} » — " . count($mods) . " module(s)");
    } else {
        rfail("Rôle « {$role} » — aucun module assigné");
    }
}

$uiFiles = [
    'parametres/utilisateurs.php' => 'app_roles_select_options',
    'includes/app_layout.php'     => 'app_module_roles',
    'includes/app_home_modules.php' => 'app_module_roles',
    'communication/nouveau_message.php' => 'APP_ROLE_LABELS',
    'finances/imprimer_ecriture.php' => 'module_require_roles',
];
foreach ($uiFiles as $file => $needle) {
    $path = $base . '/' . $file;
    if (!is_file($path)) {
        rfail("$file — fichier absent");
        continue;
    }
    if (strpos(file_get_contents($path), $needle) !== false) {
        rok("$file — intégration rôles");
    } else {
        rfail("$file — $needle manquant");
    }
}

$legacyGuard = $base . '/utilisateurs/_legacy_guard.php';
if (is_file($legacyGuard)) {
    rok('utilisateurs/_legacy_guard.php — redirection legacy');
} else {
    rfail('utilisateurs/_legacy_guard.php manquant');
}

$labels = APP_ROLE_LABELS;
$expected = ['admin', 'medecin', 'sage_femme', 'infirmier', 'secretaire', 'comptable', 'pharmacien', 'laborantin', 'major', 'technicien'];
foreach ($expected as $role) {
    if (isset($labels[$role])) {
        rok("APP_ROLE_LABELS['$role'] défini");
    } else {
        rfail("APP_ROLE_LABELS['$role'] manquant");
    }
}

if (in_array('laborantin', app_module_roles('laboratoire'), true)) {
    rok('Laborantin autorisé sur laboratoire');
} else {
    rfail('Laborantin absent du module laboratoire');
}

if (in_array('major', app_module_roles('laboratoire'), true)) {
    rok('Major autorisé sur laboratoire');
} else {
    rfail('Major absent du module laboratoire');
}

if (in_array('comptable', app_module_roles('paiements'), true)) {
    rok('Comptable autorisé sur paiements');
} else {
    rfail('Comptable absent du module paiements');
}

if (in_array('secretaire', app_module_roles('patients'), true)) {
    rok('Secrétaire autorisé sur patients (accueil, assignation, tickets)');
} else {
    rfail('Secrétaire absent du module patients');
}

if (app_role_has_medecin_scope('sage_femme') && StaffLink::linkTypeForRole('sage_femme') === 'medecin') {
    rok('Sage-femme — filtrage patients via fiche médecin (comme médecin)');
} else {
    rfail('Sage-femme — rattachement ou filtrage médecin incomplet');
}

echo "\n--- Multi-tenant (tenant_id) ---\n";

$tenantFiles = [
    'models/Utilisateur.php'           => ['tenantFilter', 'tenant_id manquant, création refusée'],
    'includes/app_parametres_layout.php' => ['getTenantId()', 'saas_is_platform_admin'],
    'models/Communication.php'         => 'TenantScope::bindInsert',
    'includes/init.php'                => 'saas_require_tenant_context',
    'config/Auth.php'                  => "['tenant_id']",
    'includes/pwa.php'                 => 'efficasante_web_base_path',
];
foreach ($tenantFiles as $file => $needles) {
    $path = $base . '/' . $file;
    if (!is_file($path)) {
        rfail("$file — absent (tenant)");
        continue;
    }
    $content = file_get_contents($path);
    $needles = (array) $needles;
    $ok = true;
    foreach ($needles as $needle) {
        if (strpos($content, $needle) === false) {
            $ok = false;
            break;
        }
    }
    if ($ok) {
        rok("$file — isolation / contexte tenant");
    } else {
        rfail("$file — garde tenant incomplète");
    }
}

require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
TenantSchema::ensure();
$pdo = getDB();

$stmt = $pdo->prepare(
    'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
);
$stmt->execute(['utilisateurs', 'tenant_id']);
if ($stmt->fetchColumn()) {
    rok('Table utilisateurs — colonne tenant_id présente');
} else {
    rfail('Table utilisateurs — colonne tenant_id absente');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$defaultTenant = (int) $pdo->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn();
if ($defaultTenant > 0) {
    $_SESSION['tenant_id'] = $defaultTenant;
    $_SESSION['user_connected'] = true;
    unset($_SESSION['is_platform_admin']);

    require_once $base . '/config/database.php';
    require_once $base . '/models/Utilisateur.php';
    $db = (new Database())->getConnection();
    $model = new Utilisateur($db);
    $countScoped = count($model->getAll());
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE tenant_id = ?');
    $stmt->execute([$defaultTenant]);
    $countDb = (int) $stmt->fetchColumn();
    if ($countScoped === $countDb) {
        rok("Utilisateur::getAll() filtré tenant #$defaultTenant ($countScoped)");
    } else {
        rfail("Utilisateur::getAll()=$countScoped vs BDD tenant=$countDb");
    }

    $other = $pdo->query(
        'SELECT id, tenant_id FROM utilisateurs WHERE tenant_id IS NOT NULL AND tenant_id != '
        . $defaultTenant . ' LIMIT 1'
    )->fetch(PDO::FETCH_ASSOC);
    if ($other) {
        $foreign = $model->getById((int) $other['id']);
        if ($foreign === false || $foreign === null) {
            rok('Utilisateur::getById() bloque un utilisateur d\'un autre tenant');
        } else {
            rfail('Fuite tenant — getById utilisateur étranger #' . $other['id']);
        }
    } else {
        echo "[WARN] Pas de second tenant pour test fuite utilisateurs\n";
    }
} else {
    rfail('Aucun tenant en base pour tests utilisateurs');
}

echo "\n=== Résumé ===\n";
echo "OK: $passed | FAIL: $failed\n";
exit($failed > 0 ? 1 : 0);
