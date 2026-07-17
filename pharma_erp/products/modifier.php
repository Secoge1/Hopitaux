<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';

$model = new PeProduct();
$id = (int) ($_GET['id'] ?? 0);
$product = $model->findById($id);
$error = '';

if (!$product) {
    redirectWithMessage(pharma_erp_url('products/'), 'Produit introuvable.', 'warning');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'category_id' => $product['category_id'],
            'laboratory_id' => $product['laboratory_id'],
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
            'status' => $_POST['status'] ?? 'active',
        ];
        if ($model->update($id, $data)) {
            redirectWithMessage(pharma_erp_url('products/'), 'Produit mis à jour.', 'success');
        }
        $error = 'Erreur lors de la mise à jour.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start([
    'active' => 'products',
    'title' => 'Modifier produit',
    'subtitle' => $product['sku'],
    'icon' => 'fa-edit',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('products/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);

if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post" class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Nom commercial *</label>
                <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($product['name']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">SKU</label>
                <input type="text" class="form-control" disabled value="<?= htmlspecialchars($product['sku']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Nom générique</label>
                <input type="text" name="generic_name" class="form-control" value="<?= htmlspecialchars($product['generic_name'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Code-barres</label>
                <input type="text" name="barcode_primary" class="form-control" value="<?= htmlspecialchars($product['barcode_primary'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Prix achat</label>
                <input type="number" name="purchase_price" class="form-control" value="<?= (float) $product['purchase_price'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Prix vente</label>
                <input type="number" name="sale_price" class="form-control" value="<?= (float) $product['sale_price'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">TVA (%)</label>
                <input type="number" name="vat_rate" class="form-control" value="<?= (float) $product['vat_rate'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Seuil réappro.</label>
                <input type="number" name="reorder_level" class="form-control" value="<?= (int) $product['reorder_level'] ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactive" <?= $product['status'] === 'inactive' ? 'selected' : '' ?>>Inactif</option>
                    <option value="discontinued" <?= $product['status'] === 'discontinued' ? 'selected' : '' ?>>Arrêté</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
