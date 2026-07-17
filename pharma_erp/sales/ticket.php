<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/bootstrap.php';

pharma_erp_require_role();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    echo 'Vente introuvable';
    exit;
}

require_once __DIR__ . '/../../models/pharma_erp/PeSale.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';
require_once __DIR__ . '/../../includes/pharma_erp/thermal_sale_ticket.php';

$sale = (new PeSale())->getById($id);
if (!$sale) {
    http_response_code(404);
    echo 'Vente introuvable';
    exit;
}

$pharmacy = null;
if (!empty($sale['pharmacy_id'])) {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM pe_pharmacies WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $sale['pharmacy_id']]);
    $pharmacy = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!$pharmacy) {
    $pharmacy = (new PePharmacy())->getDefault() ?: [];
}

header('Content-Type: text/html; charset=utf-8');
echo pharma_erp_render_sale_ticket_html($sale, $pharmacy);
