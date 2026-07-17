<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PePurchase.php';
require_once __DIR__ . '/../../models/pharma_erp/PeSupplier.php';
require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$purchaseModel = new PePurchase();
$supplierModel = new PeSupplier();
$productModel = new PeProduct();
$pharmacyModel = new PePharmacy();

$pharmacy = $pharmacyModel->getDefault();
$suppliers = $supplierModel->getAll(1, 200);
$products = $productModel->getAll(1, 500);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$pharmacy) {
            throw new RuntimeException('Officine non configurée.');
        }
        $supplierId = (int) ($_POST['supplier_id'] ?? 0);
        $productIds = $_POST['product_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $costs = $_POST['unit_cost'] ?? [];

        $lines = [];
        foreach ($productIds as $i => $pid) {
            $pid = (int) $pid;
            $qty = (int) ($quantities[$i] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $lines[] = [
                    'product_id' => $pid,
                    'quantity' => $qty,
                    'unit_cost' => (float) ($costs[$i] ?? 0),
                ];
            }
        }

        $result = $purchaseModel->createOrder(
            (int) $pharmacy['id'],
            $supplierId,
            $lines,
            trim($_POST['notes'] ?? '')
        );

        redirectWithMessage(
            pharma_erp_url('purchases/reception.php?id=' . $result['id']),
            'Commande ' . $result['order_number'] . ' créée.',
            'success'
        );
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start([
    'active' => 'purchases',
    'title' => 'Nouvelle commande',
    'icon' => 'fa-plus',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('purchases/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);

if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post" id="poForm">
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Fournisseur *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($suppliers as $s): ?>
                        <option value="<?= (int) $s['id'] ?>"><?= htmlspecialchars($s['company_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notes</label>
                    <input type="text" name="notes" class="form-control">
                </div>
            </div>

            <h6 class="mb-3">Lignes de commande</h6>
            <div id="poLines">
                <div class="row g-2 mb-2 po-line">
                    <div class="col-md-5">
                        <select name="product_id[]" class="form-select" required>
                            <option value="">Produit</option>
                            <?php foreach ($products as $p): ?>
                            <option value="<?= (int) $p['id'] ?>" data-cost="<?= (float) $p['purchase_price'] ?>">
                                <?= htmlspecialchars($p['sku'] . ' — ' . $p['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2"><input type="number" name="quantity[]" class="form-control" min="1" value="1" placeholder="Qté" required></div>
                    <div class="col-md-3"><input type="number" name="unit_cost[]" class="form-control po-cost" min="0" step="0.01" placeholder="Prix achat"></div>
                    <div class="col-md-2"><button type="button" class="btn btn-pharma-outline w-100 po-remove" disabled><i class="fas fa-times"></i></button></div>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-pharma-outline mb-3" id="poAddLine"><i class="fas fa-plus me-1"></i> Ajouter une ligne</button>
            <div><button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Créer la commande</button></div>
        </form>
    </div>
</div>

<script>
document.getElementById('poAddLine').addEventListener('click', function () {
    const tpl = document.querySelector('.po-line').cloneNode(true);
    tpl.querySelectorAll('input').forEach(i => { if (i.type === 'number' && i.name === 'quantity[]') i.value = 1; else if (i.type !== 'number') i.value = ''; else i.value = ''; });
    tpl.querySelector('.po-remove').disabled = false;
    document.getElementById('poLines').appendChild(tpl);
});
document.getElementById('poLines').addEventListener('click', function (e) {
    if (e.target.closest('.po-remove') && !e.target.closest('.po-remove').disabled) {
        e.target.closest('.po-line').remove();
    }
});
document.getElementById('poLines').addEventListener('change', function (e) {
    if (e.target.name === 'product_id[]') {
        const opt = e.target.selectedOptions[0];
        const cost = opt ? opt.dataset.cost : 0;
        e.target.closest('.po-line').querySelector('.po-cost').value = cost || '';
    }
});
</script>

<?php pharma_erp_page_end(); ?>
