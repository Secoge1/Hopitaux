<?php
ob_start();
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/saas/SubscriptionInvoice.php';
require_once __DIR__ . '/../includes/saas/subscription_invoice_render.php';
require_once __DIR__ . '/../includes/saas/saas_helpers.php';
require_once __DIR__ . '/../includes/pdf_branding.php';

saas_require_platform_admin();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    pdf_discard_output_buffers();
    http_response_code(400);
    exit('Facture invalide.');
}

$invoiceSvc = new SubscriptionInvoice();
$invoice = $invoiceSvc->getById($id);

if (!$invoice) {
    pdf_discard_output_buffers();
    http_response_code(404);
    exit('Facture introuvable.');
}

try {
    sub_invoice_render_pdf($invoice);
} catch (Throwable $e) {
    error_log('facture_abonnement_pdf: ' . $e->getMessage());
    pdf_discard_output_buffers();
    if (!function_exists('app_url')) {
        require_once __DIR__ . '/../includes/app_layout.php';
    }
    header('Location: ' . app_url('admin_platform/facture_abonnement.php?id=' . $id . '&print=1'));
    exit;
}
