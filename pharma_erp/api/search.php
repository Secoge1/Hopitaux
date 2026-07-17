<?php
/**
 * API PharmaPro — recherche produits (scanner / autocomplete).
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

require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';

$query = trim($_GET['q'] ?? $_GET['barcode'] ?? '');
if ($query === '') {
    echo json_encode(['results' => []]);
    exit;
}

$model = new PeProduct();
$product = $model->findByBarcode($query);

if ($product) {
    echo json_encode(['results' => [$product], 'exact' => true]);
    exit;
}

$results = $model->getAll(1, 15, $query);
echo json_encode(['results' => $results, 'exact' => false]);
