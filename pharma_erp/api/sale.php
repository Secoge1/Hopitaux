<?php
/**
 * API PharmaPro — finaliser une vente POS.
 */
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

pharma_erp_require_feature();

if (!pharma_erp_user_can_access()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON invalide']);
    exit;
}

require_once __DIR__ . '/../../models/pharma_erp/PeSale.php';

try {
    $saleModel = new PeSale();
    $result = $saleModel->createSale(
        (int) ($input['pharmacy_id'] ?? 0),
        (int) ($input['register_id'] ?? 0),
        (int) ($input['deposit_id'] ?? 0),
        $input['lines'] ?? [],
        [
            'method' => $input['payment_method'] ?? 'cash',
            'amount' => (float) ($input['amount_paid'] ?? 0),
            'discount' => (float) ($input['discount'] ?? 0),
            'promo_code' => trim($input['promo_code'] ?? ''),
            'loyalty_phone' => trim($input['loyalty_phone'] ?? ''),
            'reference' => $input['reference'] ?? null,
            'provider' => $input['provider'] ?? null,
        ],
        $input['customer_name'] ?? null
    );
    echo json_encode(['success' => true, 'sale' => $result]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['error' => $e->getMessage()]);
}
