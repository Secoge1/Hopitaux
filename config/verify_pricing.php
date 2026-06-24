<?php
/**
 * Vérification rigoureuse des tarifs d'abonnement SaaS.
 * Usage : php config/verify_pricing.php
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/saas/SubscriptionPlan.php';
require_once __DIR__ . '/../includes/saas/SubscriptionCheckout.php';
require_once __DIR__ . '/../includes/saas/TenantSchema.php';

TenantSchema::ensure();
$pdo = getDB();

$ok = 0;
$fail = 0;
$warn = 0;

function vok(string $msg): void
{
    global $ok;
    echo "[OK] {$msg}\n";
    $ok++;
}

function vfail(string $msg): void
{
    global $fail;
    echo "[FAIL] {$msg}\n";
    $fail++;
}

function vwarn(string $msg): void
{
    global $warn;
    echo "[WARN] {$msg}\n";
    $warn++;
}

function vcheck(bool $cond, string $msg): void
{
    if ($cond) {
        vok($msg);
    } else {
        vfail($msg);
    }
}

echo "=== Vérification rigoureuse des tarifs ===\n\n";

// 1. Définition des plans
$expected = [
    SubscriptionPlan::STARTER => ['price' => 70000, 'users' => 5, 'renewal' => 70000],
    SubscriptionPlan::ANNUAL => ['price' => 100000, 'users' => 15, 'renewal' => 100000],
    SubscriptionPlan::LIFETIME => ['price' => 550000, 'users' => 50, 'renewal' => null],
];

$plans = SubscriptionPlan::getCommercialPlans();
vcheck(count($plans) === 3, '3 formules commerciales exposées');

foreach ($expected as $slug => $exp) {
    $plan = SubscriptionPlan::get($slug);
    vcheck((int) $plan['price_xof'] === $exp['price'], "Prix {$slug} = " . number_format($exp['price'], 0, ',', ' ') . ' FCFA');
    vcheck((int) $plan['max_users'] === $exp['users'], "Max utilisateurs {$slug} = {$exp['users']}");
    vcheck(
        SubscriptionCheckout::calculateAmount($slug) === $exp['price'],
        "SubscriptionCheckout::calculateAmount({$slug}) nouvelle commande"
    );
    if ($exp['renewal'] !== null) {
        vcheck(
            SubscriptionCheckout::calculateAmount($slug, 'renewal') === $exp['renewal'],
            "SubscriptionCheckout::calculateAmount({$slug}) renouvellement"
        );
    }
}

// 2. Aliases et plan par défaut
$aliases = [
    'essentiel' => SubscriptionPlan::STARTER,
    'starter' => SubscriptionPlan::STARTER,
    'pro' => SubscriptionPlan::ANNUAL,
    'abonnement' => SubscriptionPlan::ANNUAL,
    'annuel' => SubscriptionPlan::ANNUAL,
    'vie' => SubscriptionPlan::LIFETIME,
    'lifetime' => SubscriptionPlan::LIFETIME,
    'inconnu' => SubscriptionPlan::ANNUAL,
];
foreach ($aliases as $input => $expectedSlug) {
    vcheck(
        SubscriptionPlan::normalizeSlug($input) === $expectedSlug,
        "normalizeSlug('{$input}') → {$expectedSlug}"
    );
}
vcheck(SubscriptionPlan::normalizeSlug(null) === SubscriptionPlan::ANNUAL, 'normalizeSlug(null) → annual (défaut)');

// 3. Hiérarchie des upgrades
vcheck(SubscriptionPlan::planRank(SubscriptionPlan::STARTER) < SubscriptionPlan::planRank(SubscriptionPlan::ANNUAL), 'Upgrade starter → annual possible');
vcheck(SubscriptionPlan::planRank(SubscriptionPlan::ANNUAL) < SubscriptionPlan::planRank(SubscriptionPlan::LIFETIME), 'Upgrade annual → lifetime possible');
vcheck(SubscriptionPlan::planRank(SubscriptionPlan::STARTER) < SubscriptionPlan::planRank(SubscriptionPlan::LIFETIME), 'Upgrade starter → lifetime possible');

// 4. Features marketing dynamiques
$starterFeatures = implode(' | ', array_column(SubscriptionPlan::getPlanMarketingFeatures(SubscriptionPlan::STARTER), 'text'));
$annualFeatures = implode(' | ', array_column(SubscriptionPlan::getPlanMarketingFeatures(SubscriptionPlan::ANNUAL), 'text'));
$lifetimeFeatures = implode(' | ', array_column(SubscriptionPlan::getPlanMarketingFeatures(SubscriptionPlan::LIFETIME), 'text'));

vcheck(strpos($starterFeatures, '5 utilisateurs') !== false, 'Marketing Essentiel mentionne 5 utilisateurs');
vcheck(strpos($starterFeatures, '70 000') !== false, 'Marketing Essentiel mentionne 70 000 FCFA');
vcheck(strpos($annualFeatures, '15 utilisateurs') !== false, 'Marketing Pro mentionne 15 utilisateurs');
vcheck(strpos($annualFeatures, '100 000') !== false, 'Marketing Pro mentionne 100 000 FCFA');
vcheck(strpos($lifetimeFeatures, '550 000') === false, 'Marketing à vie n\'a pas de prix en dur (OK si users seulement)');
vcheck(strpos($lifetimeFeatures, '50 utilisateurs') !== false, 'Marketing à vie mentionne 50 utilisateurs');

// 5. Schéma BDD — ENUM license_type
foreach (['tenants', 'subscription_orders', 'subscription_invoices'] as $table) {
    $col = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'license_type'")->fetch(PDO::FETCH_ASSOC);
    if (!$col) {
        vfail("Colonne license_type absente sur {$table}");
        continue;
    }
    foreach (['starter', 'annual', 'lifetime'] as $value) {
        vcheck(strpos($col['Type'], $value) !== false, "ENUM {$table}.license_type contient '{$value}'");
    }
}

// 6. createOrder par plan (transaction annulée)
foreach (array_keys($expected) as $slug) {
    $pdo->beginTransaction();
    try {
        $checkout = new SubscriptionCheckout();
        $result = $checkout->createOrder([
            'license_type' => $slug,
            'company_name' => 'Verify Pricing ' . $slug,
            'email' => 'verify-pricing-' . $slug . '@test.local',
            'nom_utilisateur' => 'verify_pricing_' . $slug,
            'password' => 'Test1234',
        ]);
        vcheck(
            !empty($result['success']) && (int) ($result['amount'] ?? 0) === $expected[$slug]['price'],
            "createOrder({$slug}) enregistre le bon montant"
        );
        $pdo->rollBack();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        vfail("createOrder({$slug}) exception : " . $e->getMessage());
    }
}

// 7. Anciens prix codés en dur dans les pages publiques
$publicFiles = [
    __DIR__ . '/../home.php',
    __DIR__ . '/../renew.php',
    __DIR__ . '/../tarifs.php',
    __DIR__ . '/../subscribe.php',
    __DIR__ . '/../documentation.php',
    __DIR__ . '/../payment_instructions.php',
    __DIR__ . '/../login.php',
    __DIR__ . '/../includes/public_layout.php',
];
$oldPricePattern = '/375000|375\s000|formatPrice\s*\(\s*70000\s*\)|formatPrice\s*\(\s*375000\s*\)/';
foreach ($publicFiles as $file) {
    $content = file_get_contents($file);
    if (preg_match($oldPricePattern, $content)) {
        vfail('Ancien prix codé en dur dans ' . basename($file));
    } else {
        vok('Pas d\'ancien prix en dur dans ' . basename($file));
    }
}

// 8. Textes marketing obsolètes (avertissements)
$stalePatterns = [
    __DIR__ . '/../home.php' => '/Abonnement annuel ou licence à vie/',
    __DIR__ . '/../login.php' => '/abonnement annuel ou licence à vie/',
];
foreach ($stalePatterns as $file => $pattern) {
    if (preg_match($pattern, file_get_contents($file))) {
        vwarn('Texte marketing obsolète (2 formules) dans ' . basename($file));
    }
}

// 9. Cohérence tenants existants vs nouveaux plans
$stmt = $pdo->query("SELECT license_type, COUNT(*) AS c FROM tenants GROUP BY license_type");
$tenantCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
if ($tenantCounts) {
    foreach ($tenantCounts as $row) {
        $slug = SubscriptionPlan::normalizeSlug($row['license_type']);
        vok("Tenants en base : {$row['license_type']} = {$row['c']} (normalisé: {$slug})");
    }
} else {
    vwarn('Aucun tenant en base');
}

// 10. Admin prolongation — détection bug license_type forcé
$handlers = file_get_contents(__DIR__ . '/../admin_platform/_handlers.php');
if (preg_match("/license_type = 'annual'/", $handlers)) {
    vwarn("admin_platform/_handlers.php force license_type='annual' lors d'une prolongation manuelle (peut écraser un tenant 'starter')");
}

echo "\n=== Résumé ===\n";
echo "OK: {$ok} | FAIL: {$fail} | WARN: {$warn}\n";

exit($fail > 0 ? 1 : 0);
