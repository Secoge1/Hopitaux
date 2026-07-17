<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';
require_once __DIR__ . '/../../models/pharma_erp/PeStock.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$productModel = new PeProduct();
$stockModel = new PeStock();
$pharmacyModel = new PePharmacy();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'sku' => trim($_POST['sku'] ?? ''),
            'barcode_primary' => trim($_POST['barcode_primary'] ?? ''),
            'name' => trim($_POST['name'] ?? ''),
            'generic_name' => trim($_POST['generic_name'] ?? ''),
            'dci' => trim($_POST['dci'] ?? ''),
            'dosage_form' => $_POST['dosage_form'] ?? 'tablet',
            'strength' => trim($_POST['strength'] ?? ''),
            'purchase_price' => (float) ($_POST['purchase_price'] ?? 0),
            'sale_price' => (float) ($_POST['sale_price'] ?? 0),
            'vat_rate' => (float) ($_POST['vat_rate'] ?? 0),
            'reorder_level' => (int) ($_POST['reorder_level'] ?? 10),
            'requires_prescription' => !empty($_POST['requires_prescription']),
            'is_controlled' => !empty($_POST['is_controlled']),
        ];

        if ($data['name'] === '') {
            throw new InvalidArgumentException('Le nom du produit est obligatoire.');
        }

        $productId = $productModel->create($data);
        if (!$productId) {
            throw new RuntimeException('Erreur lors de la création.');
        }

        $initialStock = (int) ($_POST['initial_stock'] ?? 0);
        if ($initialStock > 0) {
            $pharmacy = $pharmacyModel->getDefault();
            $depositId = $pharmacyModel->getDefaultDepositId((int) $pharmacy['id']);
            if ($depositId) {
                $stockModel->stockIn([
                    'pharmacy_id' => (int) $pharmacy['id'],
                    'deposit_id' => $depositId,
                    'product_id' => $productId,
                    'quantity' => $initialStock,
                    'unit_cost' => $data['purchase_price'],
                    'lot_number' => trim($_POST['lot_number'] ?? '') ?: 'INIT-' . date('Ymd'),
                    'expiry_date' => $_POST['expiry_date'] ?? date('Y-m-d', strtotime('+2 years')),
                    'notes' => 'Stock initial à la création',
                ]);
            }
        }

        redirectWithMessage(pharma_erp_url('products/'), 'Produit créé avec succès.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start([
    'active' => 'products',
    'title' => 'Nouveau produit',
    'subtitle' => 'Ajouter au catalogue PharmaPro',
    'icon' => 'fa-plus',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('products/'), 'label' => 'Retour à la liste', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);

if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post" class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Nom commercial *</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">SKU</label>
                <input type="text" name="sku" class="form-control" placeholder="Auto si vide">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nom générique / DCI</label>
                <input type="text" name="generic_name" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Code-barres</label>
                <input type="text" name="barcode_primary" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Forme</label>
                <select name="dosage_form" class="form-select">
                    <option value="tablet">Comprimé</option>
                    <option value="capsule">Gélule</option>
                    <option value="syrup">Sirop</option>
                    <option value="injection">Injection</option>
                    <option value="cream">Crème</option>
                    <option value="drops">Gouttes</option>
                    <option value="other">Autre</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Dosage</label>
                <input type="text" name="strength" class="form-control" placeholder="500 mg">
            </div>
            <div class="col-md-3">
                <label class="form-label">Prix achat (FCFA)</label>
                <input type="number" name="purchase_price" class="form-control" min="0" step="1" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label">Prix vente (FCFA) *</label>
                <input type="number" name="sale_price" class="form-control" min="0" step="1" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">TVA (%)</label>
                <input type="number" name="vat_rate" class="form-control" min="0" step="0.01" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label">Seuil réappro.</label>
                <input type="number" name="reorder_level" class="form-control" min="0" value="10">
            </div>
            <div class="col-md-3">
                <label class="form-label">Stock initial</label>
                <input type="number" name="initial_stock" class="form-control" min="0" value="0">
            </div>
            <div class="col-md-3">
                <label class="form-label">N° lot initial</label>
                <input type="text" name="lot_number" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Péremption lot</label>
                <input type="date" name="expiry_date" class="form-control" value="<?= date('Y-m-d', strtotime('+2 years')) ?>">
            </div>
            <div class="col-12">
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="requires_prescription" id="rx">
                    <label class="form-check-label" for="rx">Ordonnance obligatoire</label>
                </div>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="is_controlled" id="ctrl">
                    <label class="form-check-label" for="ctrl">Stupéfiant / contrôlé</label>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
