<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeStock.php';
require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$stockModel = new PeStock();
$productModel = new PeProduct();
$pharmacyModel = new PePharmacy();
$error = '';

$pharmacy = $pharmacyModel->getDefault();
$depositId = $pharmacy ? $pharmacyModel->getDefaultDepositId((int) $pharmacy['id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$pharmacy || !$depositId) {
            throw new RuntimeException('Officine non configurée.');
        }
        $productId = (int) ($_POST['product_id'] ?? 0);
        $qty = (int) ($_POST['quantity'] ?? 0);
        if ($productId <= 0 || $qty <= 0) {
            throw new InvalidArgumentException('Produit et quantité obligatoires.');
        }
        $stockModel->stockIn([
            'pharmacy_id' => (int) $pharmacy['id'],
            'deposit_id' => $depositId,
            'product_id' => $productId,
            'quantity' => $qty,
            'unit_cost' => (float) ($_POST['unit_cost'] ?? 0),
            'lot_number' => trim($_POST['lot_number'] ?? '') ?: 'LOT-' . date('YmdHis'),
            'expiry_date' => $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+2 years')),
            'supplier_id' => !empty($_POST['supplier_id']) ? (int) $_POST['supplier_id'] : null,
            'notes' => trim($_POST['notes'] ?? ''),
        ]);
        redirectWithMessage(pharma_erp_url('stock/'), 'Entrée stock enregistrée.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$products = $productModel->getAll(1, 500);

pharma_erp_page_start([
    'active' => 'stock',
    'title' => 'Entrée stock',
    'subtitle' => 'Réception marchandise / inventaire',
    'icon' => 'fa-arrow-down',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('stock/'), 'label' => 'Retour stock', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);

if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Produit *</label>
                <select name="product_id" class="form-select" required>
                    <option value="">— Sélectionner —</option>
                    <?php foreach ($products as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars($p['sku'] . ' — ' . $p['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Quantité *</label>
                <input type="number" name="quantity" class="form-control" min="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Coût unitaire</label>
                <input type="number" name="unit_cost" class="form-control" min="0" step="0.01">
            </div>
            <div class="col-md-4">
                <label class="form-label">N° lot</label>
                <input type="text" name="lot_number" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">Date péremption *</label>
                <input type="date" name="expiry_date" class="form-control" required value="<?= date('Y-m-d', strtotime('+2 years')) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Notes</label>
                <input type="text" name="notes" class="form-control">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer l'entrée</button>
            </div>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
