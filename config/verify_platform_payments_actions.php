<?php
/**
 * Vérification boutons d'actions — admin_platform/payments.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/saas/SubscriptionCheckout.php';
require_once $base . '/includes/saas/SubscriptionPlan.php';
require_once $base . '/includes/saas/saas_helpers.php';
require_once $base . '/includes/header_logo.php';
require_once $base . '/includes/app_platform_actions.php';

$ok = 0;
$fail = 0;
$warn = 0;

function vok($m) { global $ok; $ok++; echo "[OK] $m\n"; }
function vfail($m) { global $fail; $fail++; echo "[FAIL] $m\n"; }
function vwarn($m) { global $warn; $warn++; echo "[WARN] $m\n"; }

echo "=== Vérification actions paiements (admin_platform/payments.php) ===\n\n";

$paymentsPhp = file_get_contents($base . '/admin_platform/payments.php');
$handlersPhp = file_get_contents($base . '/admin_platform/_handlers.php');
$actionsPhp = file_get_contents($base . '/includes/app_platform_actions.php');

if (strpos($paymentsPhp, 'app_platform_payment_actions') !== false) {
    vok('payments.php appelle app_platform_payment_actions()');
} else {
    vfail('payments.php n\'utilise pas app_platform_payment_actions');
}

if (strpos($paymentsPhp, 'admin_platform_handle_post') !== false
    && strpos($paymentsPhp, 'app_platform_require_admin') !== false) {
    $posAdmin = strpos($paymentsPhp, 'app_platform_require_admin');
    $posPost = strpos($paymentsPhp, 'admin_platform_handle_post');
    if ($posAdmin !== false && $posPost !== false && $posAdmin < $posPost) {
        vok('Garde admin avant traitement POST');
    } else {
        vfail('Ordre garde admin / POST incorrect');
    }
} else {
    vfail('Garde admin ou handler POST manquant');
}

foreach (['confirm_payment', 'cancel_order'] as $action) {
    if (strpos($handlersPhp, "'$action'") !== false || strpos($handlersPhp, "\"$action\"") !== false) {
        vok("Handler POST — $action");
    } else {
        vfail("Handler POST — $action manquant");
    }
}

if (strpos($actionsPhp, 'function app_platform_payment_actions') !== false) {
    vok('Fonction app_platform_payment_actions définie');
} else {
    vfail('app_platform_payment_actions absente');
}

$checkout = new SubscriptionCheckout();
$orders = $checkout->listPendingOrders();
echo "\nCommandes en attente en base : " . count($orders) . "\n\n";

if (empty($orders)) {
    vwarn('Aucune commande pending — test HTML sur commande fictive');
    $sample = [
        'id' => 999,
        'ref_command' => 'TEST-REF-001',
        'tenant_id' => 0,
        'company_name' => 'Test',
        'email' => 'test@test.local',
    ];
    $html = app_platform_payment_actions($sample, false);
} else {
    $sample = $orders[0];
    $html = app_platform_payment_actions($sample, false);
    vok('HTML généré pour commande #' . (int) $sample['id']);
}

$checks = [
    'confirm_payment' => 'Bouton Confirmer (name=confirm_payment)',
    'cancel_order' => 'Bouton Annuler (name=cancel_order)',
    'order_id' => 'Champ caché order_id',
    'platform-act--success' => 'Style succès (Confirmer)',
    'platform-act--danger' => 'Style danger (Annuler)',
    'fa-file-invoice' => 'Lien Instructions client',
    'onsubmit=' => 'Confirmation JS sur le formulaire (onsubmit)',
    'platform-action-btns' => 'Conteneur actions',
];

foreach ($checks as $needle => $label) {
    if (strpos($html, $needle) !== false) {
        vok($label);
    } else {
        vfail($label . ' — absent du HTML');
    }
}

$formCount = substr_count($html, '<form');
if ($formCount === 2) {
    vok('2 formulaires POST distincts (Confirmer + Annuler)');
} else {
    vfail("Nombre de formulaires attendu 2, trouvé $formCount");
}

if (strpos($html, 'name="order_id" value="' . (int) $sample['id'] . '"') !== false) {
    vok('order_id correct dans les formulaires');
} else {
    vfail('order_id incorrect ou manquant');
}

if ((int) ($sample['tenant_id'] ?? 0) > 0) {
    if (strpos($html, 'fa-building') !== false && strpos($html, 'tenants.php?edit=') !== false) {
        vok('Lien Gérer établissement (tenant_id > 0)');
    } else {
        vfail('Lien Gérer établissement manquant alors que tenant_id est défini');
    }
} else {
    if (strpos($html, 'fa-building') === false) {
        vok('Pas de lien Gérer établissement (nouvelle commande sans tenant)');
    } else {
        vwarn('Lien Gérer établissement présent sans tenant_id');
    }
}

if (strpos($handlersPhp, 'markPaidManually') !== false) {
    vok('confirm_payment délègue à SubscriptionCheckout::markPaidManually');
} else {
    vfail('markPaidManually non utilisé dans le handler');
}

if (preg_match("/payment_status.*!==\s*'pending'/", $handlersPhp)) {
    vok('Handlers vérifient le statut pending');
} else {
    vfail('Vérification statut pending absente');
}

if (strpos($handlersPhp, 'markPaidManually') !== false && strpos($handlersPhp, "!== 'pending'") !== false) {
    vok('confirm_payment vérifie pending avant markPaidManually');
} else {
    vfail('confirm_payment sans contrôle pending explicite');
}

if (strpos($handlersPhp, 'csrf') === false && strpos($handlersPhp, 'CSRF') === false) {
    vwarn('Pas de token CSRF sur les actions POST (renforcement recommandé)');
}

echo "\n=== Résumé ===\n";
echo "OK: $ok | FAIL: $fail | WARN: $warn\n";
exit($fail > 0 ? 1 : 0);
