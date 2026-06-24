<?php
/**
 * Script de vérification SaaS — CLI uniquement.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/SubscriptionPlan.php';
require_once $base . '/includes/saas/SubscriptionService.php';
require_once $base . '/includes/saas/SubscriptionCheckout.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/saas/TenantContext.php';
require_once $base . '/includes/saas/saas_helpers.php';

$passed = 0;
$failed = 0;
$warnings = 0;

function ok(string $msg): void {
    global $passed;
    $passed++;
    echo "[OK] $msg\n";
}

function fail(string $msg): void {
    global $failed;
    $failed++;
    echo "[FAIL] $msg\n";
}

function warn(string $msg): void {
    global $warnings;
    $warnings++;
    echo "[WARN] $msg\n";
}

echo "=== Vérification SaaS Efficasante ===\n\n";

// 1. Schéma DB
try {
    TenantSchema::ensure();
    ok('TenantSchema::ensure() exécuté sans erreur');
} catch (Throwable $e) {
    fail('TenantSchema::ensure(): ' . $e->getMessage());
}

$pdo = getDB();

$tables = ['tenants', 'subscription_orders'];
foreach ($tables as $table) {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    if ($stmt->fetchColumn()) {
        ok("Table `$table` existe");
    } else {
        fail("Table `$table` manquante");
    }
}

foreach (TenantSchema::getScopedTables() as $table) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    if (!$stmt->fetchColumn()) {
        warn("Table métier `$table` absente (ignorée)");
        continue;
    }
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'tenant_id'"
    );
    $stmt->execute([$table]);
    if ($stmt->fetchColumn()) {
        ok("Colonne tenant_id sur `$table`");
    } else {
        fail("Colonne tenant_id manquante sur `$table`");
    }
}

// 2. Tenant par défaut
$tenantCount = (int) $pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
if ($tenantCount >= 1) {
    ok("Au moins 1 tenant présent ($tenantCount)");
} else {
    fail('Aucun tenant en base');
}

$defaultTenant = $pdo->query('SELECT * FROM tenants ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($defaultTenant) {
    ok('Tenant par défaut: ' . $defaultTenant['company_name'] . ' (#' . $defaultTenant['id'] . ')');
}

// 3. Backfill tenant_id
if ($defaultTenant) {
    $tid = (int) $defaultTenant['id'];
    $stmt = $pdo->query("SELECT COUNT(*) FROM utilisateurs WHERE tenant_id IS NULL");
    $nullUsers = (int) $stmt->fetchColumn();
    if ($nullUsers === 0) {
        ok('Tous les utilisateurs ont un tenant_id');
    } else {
        warn("$nullUsers utilisateur(s) sans tenant_id");
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM utilisateurs WHERE tenant_id = ?");
    $stmt->execute([$tid]);
    ok('Utilisateurs rattachés au tenant #' . $tid . ': ' . $stmt->fetchColumn());
}

// 4. Plans tarifaires
$starter = SubscriptionPlan::get(SubscriptionPlan::STARTER);
$annual = SubscriptionPlan::get(SubscriptionPlan::ANNUAL);
$lifetime = SubscriptionPlan::get(SubscriptionPlan::LIFETIME);

if ((int) $starter['price_xof'] === 70000 && (int) $starter['max_users'] === 5) {
    ok('Abonnement Essentiel = 70 000 FCFA / 5 utilisateurs');
} else {
    fail('Plan Essentiel incorrect');
}

if ((int) $annual['price_xof'] === 100000 && (int) $annual['max_users'] === 15) {
    ok('Abonnement Pro = 100 000 FCFA / 15 utilisateurs');
} else {
    fail('Plan Pro incorrect: ' . $annual['price_xof']);
}

if ((int) $lifetime['price_xof'] === 550000) {
    ok('Prix licence à vie = 550 000 FCFA');
} else {
    fail('Prix à vie incorrect: ' . $lifetime['price_xof']);
}

$amountStarter = SubscriptionCheckout::calculateAmount(SubscriptionPlan::STARTER);
$amountAnnual = SubscriptionCheckout::calculateAmount(SubscriptionPlan::ANNUAL);
$amountLifetime = SubscriptionCheckout::calculateAmount(SubscriptionPlan::LIFETIME);
if ($amountStarter === 70000 && $amountAnnual === 100000 && $amountLifetime === 550000) {
    ok('SubscriptionCheckout::calculateAmount() correct');
} else {
    fail("calculateAmount: starter=$amountStarter, annual=$amountAnnual, lifetime=$amountLifetime");
}

// 5. Création commande test (rollback)
$pdo->beginTransaction();
try {
    $checkout = new SubscriptionCheckout();
    $result = $checkout->createOrder([
        'license_type' => 'annual',
        'company_name' => 'TEST Verification SaaS',
        'email' => 'test-verify-' . time() . '@efficasante.local',
        'phone' => '+22300000000',
        'nom_utilisateur' => 'test_verify_' . random_int(100, 999),
        'password' => 'test123456',
        'nom_complet' => 'Test Verify',
    ]);

    if (!empty($result['success']) && !empty($result['ref_command'])) {
        ok('createOrder() — ref: ' . $result['ref_command'] . ', montant: ' . $result['amount']);
        $order = $checkout->getOrderByRef($result['ref_command']);
        if ($order && $order['payment_status'] === 'pending') {
            ok('Commande en statut pending');
        } else {
            fail('Statut commande incorrect');
        }
    } else {
        fail('createOrder() échoué: ' . ($result['message'] ?? 'inconnu'));
    }
    $pdo->rollBack();
    ok('Transaction test annulée (pas de données résiduelles)');
} catch (Throwable $e) {
    $pdo->rollBack();
    fail('createOrder test: ' . $e->getMessage());
}

// 6. Activation — test manuel recommandé (admin_tenants.php) si verrous MySQL persistants
warn('Test markPaidFromIpn() non exécuté automatiquement. Validez via admin_tenants.php après paiement test.');

// 7. SubscriptionService
$svc = SubscriptionService::getInstance();
$svc->bindTenant($defaultTenant ? (int) $defaultTenant['id'] : 1);
$check = $svc->checkTenantStatus();
if ($check['valid']) {
    ok('checkTenantStatus() tenant par défaut valide');
} else {
    warn('Tenant par défaut: ' . $check['message']);
}

// 8. Auth — chargement classe
require_once $base . '/config/Auth.php';
$auth = Auth::getInstance();
if (method_exists($auth, 'getTenantId') && method_exists($auth, 'ensureActiveTenant')) {
    ok('Auth étendu (getTenantId, ensureActiveTenant)');
} else {
    fail('Méthodes Auth manquantes');
}

// 9. Fichiers pages
$pages = ['tarifs.php', 'subscribe.php', 'payment_instructions.php', 'renew.php', 'admin_tenants.php'];
foreach ($pages as $page) {
    if (is_file($base . '/' . $page)) {
        ok("Page $page présente");
    } else {
        fail("Page $page manquante");
    }
}

// 10. CSS
if (is_file($base . '/assets/css/subscription.css')) {
    ok('CSS subscription.css présent');
} else {
    fail('CSS subscription.css manquant');
}

echo "\n=== Résumé ===\n";
echo "OK: $passed | FAIL: $failed | WARN: $warnings\n";
exit($failed > 0 ? 1 : 0);
