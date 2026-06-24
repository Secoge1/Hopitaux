<?php
/**
 * Vérification espace admin plateforme — CLI.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/saas/saas_helpers.php';
require_once $base . '/includes/saas/PlatformAdminStats.php';
require_once $base . '/config/Auth.php';

$passed = 0;
$failed = 0;
$warnings = 0;

function pok(string $m): void { global $passed; $passed++; echo "[OK] $m\n"; }
function pfail(string $m): void { global $failed; $failed++; echo "[FAIL] $m\n"; }
function pwarn(string $m): void { global $warnings; $warnings++; echo "[WARN] $m\n"; }

echo "=== Vérification Admin plateforme SeSanté ===\n\n";

TenantSchema::ensure();
$pdo = getDB();

$files = [
    'admin_platform/index.php',
    'admin_platform/tenants.php',
    'admin_platform/payments.php',
    'admin_platform/_handlers.php',
    'includes/app_platform_layout.php',
    'includes/app_platform_actions.php',
    'assets/css/app-platform.css',
];
foreach ($files as $f) {
    is_file($base . '/' . $f) ? pok("Fichier $f") : pfail("Fichier $f manquant");
}

foreach (['index.php', 'tenants.php', 'payments.php'] as $page) {
    $c = file_get_contents($base . '/admin_platform/' . $page);
    if (strpos($c, 'app_platform_require_admin') !== false) {
        pok("admin_platform/$page — garde admin");
    } else {
        pfail("admin_platform/$page — app_platform_require_admin absent");
    }
    if (strpos($c, 'app_platform_shell_start') !== false) {
        pok("admin_platform/$page — layout plateforme");
    } else {
        pfail("admin_platform/$page — shell absent");
    }
}

// Colonne is_platform_admin
$stmt = $pdo->prepare(
    "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'is_platform_admin'"
);
$stmt->execute();
$stmt->fetchColumn() ? pok('Colonne utilisateurs.is_platform_admin') : pfail('Colonne is_platform_admin manquante');

// Compte admin
$admin = $pdo->query(
    "SELECT id, nom_utilisateur, is_platform_admin, tenant_id, statut FROM utilisateurs
     WHERE nom_utilisateur = 'admin' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
if ($admin) {
    pok('Compte admin présent (id ' . $admin['id'] . ')');
    if ((int) ($admin['is_platform_admin'] ?? 0) === 1) {
        pok('admin.is_platform_admin = 1');
    } else {
        pfail('admin.is_platform_admin = 0 — exécuter php config/enable_platform_admin.php');
    }
    if (($admin['statut'] ?? '') === 'actif') {
        pok('Compte admin actif');
    } else {
        pwarn('Compte admin statut: ' . ($admin['statut'] ?? '?'));
    }
} else {
    pfail('Compte admin introuvable');
}

// PLATFORM_ADMIN config
require_once $base . '/config/config.php';
if (defined('PLATFORM_ADMIN_USERNAMES') && in_array('admin', PLATFORM_ADMIN_USERNAMES, true)) {
    pok('PLATFORM_ADMIN_USERNAMES contient admin');
} else {
    pwarn('PLATFORM_ADMIN_USERNAMES ne liste pas admin (fallback session BDD)');
}

// Whitelist SaaS
$_SERVER['SCRIPT_NAME'] = '/Hopitaux/admin_platform/index.php';
saas_is_whitelisted_page() ? pok('admin_platform exempté garde abonnement') : pfail('admin_platform non whitelisté');

// Stats dashboard
try {
    $stats = (new PlatformAdminStats())->getDashboardStats();
    $keys = ['tenants_total', 'pending_count', 'revenue_month', 'paid_orders'];
    $ok = true;
    foreach ($keys as $k) {
        if (!array_key_exists($k, $stats)) {
            $ok = false;
            break;
        }
    }
    if ($ok) {
        pok('PlatformAdminStats::getDashboardStats() — ' . $stats['tenants_total'] . ' tenants, '
            . $stats['pending_count'] . ' pending');
    } else {
        pfail('PlatformAdminStats clés manquantes');
    }
} catch (Throwable $e) {
    pfail('PlatformAdminStats: ' . $e->getMessage());
}

// Tables SaaS
foreach (['tenants', 'subscription_orders'] as $table) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    $stmt->fetchColumn() ? pok("Table $table") : pfail("Table $table manquante");
}

// Redirection legacy
if (is_file($base . '/admin_tenants.php')) {
    $legacy = file_get_contents($base . '/admin_tenants.php');
    if (strpos($legacy, 'admin_platform/index.php') !== false) {
        pok('admin_tenants.php redirige vers admin_platform/');
    } else {
        pwarn('admin_tenants.php ne redirige pas vers admin_platform/');
    }
}

// Menu sidebar
$layout = file_get_contents($base . '/includes/app_layout.php');
if (strpos($layout, 'admin_platform/index.php') !== false && strpos($layout, 'saas_is_platform_admin') !== false) {
    pok('Menu sidebar Admin plateforme conditionné');
} else {
    pfail('Menu Admin plateforme absent ou non conditionné');
}

// Sécurité POST
$handlers = file_get_contents($base . '/admin_platform/_handlers.php');
if (strpos($handlers, 'csrf') === false && strpos($handlers, 'CSRF') === false) {
    pwarn('Actions POST admin sans token CSRF (à renforcer)');
} else {
    pok('CSRF présent dans _handlers.php');
}

if (strpos($handlers, 'delete_tenant') !== false && strpos($handlers, 'tenantId <= 1') !== false) {
    pok('Protection suppression tenant principal (id<=1)');
} else {
    pwarn('Protection tenant principal à vérifier');
}

echo "\n=== Résumé ===\n";
echo "OK: $passed | FAIL: $failed | WARN: $warnings\n";
exit($failed > 0 ? 1 : 0);
