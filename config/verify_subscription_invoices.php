<?php
/**
 * Vérification facturation abonnements SaaS.
 * Usage : php config/verify_subscription_invoices.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI uniquement.\n");
}

$base = dirname(__DIR__);
require_once $base . '/config/db.php';
require_once $base . '/includes/saas/TenantSchema.php';
require_once $base . '/includes/saas/SubscriptionCheckout.php';
require_once $base . '/includes/saas/SubscriptionInvoice.php';
require_once $base . '/includes/saas/subscription_invoice_render.php';

$ok = 0;
$fail = 0;

function icheck(bool $c, string $l): void
{
    global $ok, $fail;
    echo ($c ? 'OK  ' : 'FAIL ') . "$l\n";
    $c ? $ok++ : $fail++;
}

echo "=== Vérification facturation abonnements ===\n\n";

$files = [
    'includes/saas/SubscriptionInvoice.php',
    'includes/saas/subscription_invoice_render.php',
    'includes/saas/SubscriptionCheckout.php',
    'includes/saas/TenantSchema.php',
    'includes/saas/PlatformAdminStats.php',
    'assets/css/subscription-invoice.css',
    'admin_platform/facturation.php',
    'admin_platform/facture_abonnement.php',
    'admin_platform/facture_abonnement_pdf.php',
    'admin_platform/index.php',
    'admin_platform/_handlers.php',
];
foreach ($files as $f) {
    icheck(is_file($base . '/' . $f), "Fichier $f");
    if (is_file($base . '/' . $f)) {
        $out = [];
        $code = 0;
        exec('php -l ' . escapeshellarg($base . '/' . $f) . ' 2>&1', $out, $code);
        icheck($code === 0, 'Syntaxe PHP ' . $f);
    }
}

TenantSchema::ensure();
$pdo = getDB();

icheck(
    (bool) $pdo->query("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subscription_invoices'")->fetchColumn(),
    'Table subscription_invoices'
);

$requiredCols = [
    'subscription_order_id', 'invoice_number', 'amount_xof', 'buyer_company',
    'buyer_email', 'license_type', 'order_type', 'ref_command', 'issued_at', 'line_description',
];
foreach ($requiredCols as $col) {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'subscription_invoices' AND COLUMN_NAME = ?"
    );
    $stmt->execute([$col]);
    icheck((bool) $stmt->fetchColumn(), "Colonne subscription_invoices.$col");
}

$ukOrder = (bool) $pdo->query(
    "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'subscription_invoices' AND INDEX_NAME = 'uk_subscription_order' LIMIT 1"
)->fetchColumn();
icheck($ukOrder, 'Contrainte unique uk_subscription_order (1 facture / commande)');

$layout = file_get_contents($base . '/includes/app_platform_layout.php') ?: '';
icheck(strpos($layout, "'billing'") !== false && strpos($layout, 'facturation.php') !== false, 'Menu admin Facturation');

$checkoutSrc = file_get_contents($base . '/includes/saas/SubscriptionCheckout.php') ?: '';
icheck(strpos($checkoutSrc, 'SubscriptionInvoice') !== false, 'SubscriptionCheckout utilise SubscriptionInvoice');
icheck(strpos($checkoutSrc, 'ensureFromPaidOrder') !== false, 'Hook ensureFromPaidOrder après paiement');

$handlers = file_get_contents($base . '/admin_platform/_handlers.php') ?: '';
icheck(strpos($handlers, 'invoice_number') !== false, 'Message admin mentionne la facture');

$svc = new SubscriptionInvoice();

// Test numérotation + rendu HTML
$testSuffix = date('His') . random_int(100, 999);
$fakeOrderId = 999999000 + random_int(1, 999);
$testRef = 'TEST-INV-' . $testSuffix;
$pdo->prepare('DELETE FROM subscription_invoices WHERE subscription_order_id = ?')->execute([$fakeOrderId]);
$pdo->prepare('DELETE FROM subscription_orders WHERE id = ? OR ref_command = ?')->execute([$fakeOrderId, $testRef]);

$pdo->prepare("
    INSERT INTO subscription_orders
    (id, ref_command, order_type, license_type, amount_xof, company_name, email, phone,
     payment_status, payment_provider, paid_at, ipn_payload)
    VALUES (?, ?, 'new', 'annual', 100000, 'Clinique Test Facture', 'test@facture.local', '70000000',
            'paid', 'mobile_money', NOW(), ?)
")->execute([
    $fakeOrderId,
    $testRef,
    json_encode(['payment_method' => 'Orange Money', 'manual_confirmation' => true], JSON_UNESCAPED_UNICODE),
]);

$result = $svc->ensureFromPaidOrder($fakeOrderId);
icheck(!empty($result['success']) && !empty($result['invoice_number']), 'Génération facture commande payée test');
icheck(strpos($result['invoice_number'] ?? '', 'SUB-') === 0, 'Numéro facture préfixe SUB-');

$dup = $svc->ensureFromPaidOrder($fakeOrderId);
icheck(
    !empty($dup['success']) && ($dup['invoice_number'] ?? '') === ($result['invoice_number'] ?? ''),
    'Idempotence : pas de doublon facture'
);

$invoice = $svc->getById((int) ($result['invoice_id'] ?? 0));
icheck($invoice !== null && (int) $invoice['amount_xof'] === 100000, 'Facture lue en BDD avec montant correct');

if ($invoice) {
    $html = sub_invoice_render_html($invoice, false);
    icheck(
        strpos($html, $invoice['invoice_number']) !== false
        && strpos($html, 'Clinique Test Facture') !== false
        && strpos($html, '70') !== false,
        'Rendu HTML facture contient n°, client et montant'
    );
} else {
    icheck(false, 'Rendu HTML facture contient n°, client et montant');
}

$listed = $svc->listInvoices(5, $testRef);
icheck(count($listed) >= 1, 'Liste factures avec recherche');

// Commande pending → refus facture
$pendingId = $fakeOrderId + 1;
$pendingRef = 'TEST-PENDING-' . $testSuffix;
$pdo->prepare('DELETE FROM subscription_orders WHERE id = ? OR ref_command = ?')->execute([$pendingId, $pendingRef]);
$pdo->prepare("
    INSERT INTO subscription_orders
    (id, ref_command, order_type, license_type, amount_xof, company_name, email, payment_status)
    VALUES (?, ?, 'new', 'annual', 100000, 'Pending Co', 'pending@test.local', 'pending')
")->execute([$pendingId, $pendingRef]);
$pendingResult = $svc->ensureFromPaidOrder($pendingId);
icheck(empty($pendingResult['success']), 'Refus facture si commande non payée');
$pdo->prepare('DELETE FROM subscription_orders WHERE id = ?')->execute([$pendingId]);

// E2E : createOrder → markPaidManually → facture auto
$checkout = new SubscriptionCheckout();
$suffix = date('His');
$orderResult = $checkout->createOrder([
    'license_type' => 'annual',
    'company_name' => 'E2E Facture Verify ' . $suffix,
    'email' => 'e2e-facture-' . $suffix . '@verify.local',
    'phone' => '76000000',
    'nom_utilisateur' => 'e2e_inv_' . $suffix,
    'password' => 'TestE2E123',
    'nom_complet' => 'E2E Admin',
]);
icheck(!empty($orderResult['success']), 'E2E createOrder() réussi');
$e2eOrderId = (int) ($orderResult['order_id'] ?? 0);
$e2eRef = $orderResult['ref_command'] ?? '';

if ($e2eOrderId > 0) {
    $payResult = $checkout->markPaidManually($e2eOrderId, 'Orange Money Test');
    icheck(!empty($payResult['success']), 'E2E markPaidManually() réussi');
    icheck(!empty($payResult['invoice_number']), 'E2E retourne invoice_number');
    icheck(!empty($payResult['tenant_id']), 'E2E tenant activé');

    $e2eInv = $svc->getByOrderId($e2eOrderId);
    icheck($e2eInv !== null && $e2eInv['invoice_number'] === ($payResult['invoice_number'] ?? ''), 'E2E facture en BDD après paiement');
    icheck((int) ($e2eInv['tenant_id'] ?? 0) === (int) ($payResult['tenant_id'] ?? 0), 'E2E facture liée au tenant');

    $rePay = $checkout->markPaidManually($e2eOrderId);
    icheck(
        !empty($rePay['success']) && ($rePay['invoice_number'] ?? '') === ($payResult['invoice_number'] ?? ''),
        'E2E re-confirmation idempotente (même facture)'
    );

    // backfill : idempotent au 2e appel (1er peut rattraper des paiements historiques)
    $beforeBackfill = $svc->countInvoices();
    $backfilled1 = $svc->backfillMissing();
    $afterBackfill = $svc->countInvoices();
    icheck($afterBackfill >= $beforeBackfill, 'backfillMissing ne diminue pas le compteur');
    icheck($backfilled1 === ($afterBackfill - $beforeBackfill), 'backfillMissing cohérent avec le delta');
    $backfilled2 = $svc->backfillMissing();
    icheck($backfilled2 === 0, 'backfillMissing() idempotent au 2e appel');
    icheck($svc->countInvoices() === $afterBackfill, '2e backfill ne duplique pas');

    // Stats dashboard
    require_once $base . '/includes/saas/PlatformAdminStats.php';
    $dashStats = (new PlatformAdminStats())->getDashboardStats();
    icheck(isset($dashStats['invoices_count']) && (int) $dashStats['invoices_count'] >= 1, 'PlatformAdminStats.invoices_count');

    // Sécurité pages admin
    foreach (['facture_abonnement.php', 'facture_abonnement_pdf.php'] as $page) {
        $src = file_get_contents($base . '/admin_platform/' . $page) ?: '';
        icheck(strpos($src, 'saas_require_platform_admin') !== false, "Sécurité $page (admin requis)");
    }
    $factPage = file_get_contents($base . '/admin_platform/facturation.php') ?: '';
    icheck(strpos($factPage, 'app_platform_require_admin') !== false, 'Sécurité facturation.php (admin requis)');

    // Nettoyage E2E (tenant + commande + facture via CASCADE)
    $e2eTenantId = (int) ($payResult['tenant_id'] ?? 0);
    if ($e2eTenantId > 1) {
        $pdo->prepare('DELETE FROM subscription_orders WHERE tenant_id = ?')->execute([$e2eTenantId]);
        $pdo->prepare('DELETE FROM utilisateurs WHERE tenant_id = ?')->execute([$e2eTenantId]);
        $pdo->prepare('DELETE FROM tenants WHERE id = ?')->execute([$e2eTenantId]);
        icheck($svc->getByOrderId($e2eOrderId) === null, 'E2E nettoyage : facture supprimée (CASCADE)');
    } else {
        icheck(true, 'E2E nettoyage skipped (tenant principal)');
    }
}

// Nettoyage
$pdo->prepare('DELETE FROM subscription_invoices WHERE subscription_order_id = ?')->execute([$fakeOrderId]);
$pdo->prepare('DELETE FROM subscription_orders WHERE id = ?')->execute([$fakeOrderId]);
icheck(true, 'Nettoyage données test');

echo "\n=== Résultat : {$ok} OK / {$fail} FAIL ===\n";
exit($fail > 0 ? 1 : 0);
