<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/saas/SubscriptionInvoice.php';
require_once __DIR__ . '/../includes/saas/subscription_invoice_render.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';

saas_require_platform_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$forPrint = isset($_GET['print']);

if ($id <= 0) {
    http_response_code(400);
    exit('Facture invalide.');
}

$invoiceSvc = new SubscriptionInvoice();
$invoice = $invoiceSvc->getById($id);

if (!$invoice) {
    http_response_code(404);
    exit('Facture introuvable.');
}

echo sub_invoice_render_html($invoice, $forPrint);
exit;
