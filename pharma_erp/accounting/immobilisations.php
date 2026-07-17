<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeFixedAsset.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$model = new PeFixedAsset();
$pharmacyModel = new PePharmacy();
$defaultPharmacy = $pharmacyModel->getDefault();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_asset'])) {
    try {
        $label = trim($_POST['label'] ?? '');
        if ($label === '') {
            throw new InvalidArgumentException('Libellé obligatoire.');
        }
        $model->create([
            'pharmacy_id' => $defaultPharmacy['id'] ?? null,
            'label' => $label,
            'category' => trim($_POST['category'] ?? ''),
            'acquisition_date' => trim($_POST['acquisition_date'] ?? '') ?: date('Y-m-d'),
            'acquisition_cost' => (float) ($_POST['acquisition_cost'] ?? 0),
            'useful_life_months' => (int) ($_POST['useful_life_months'] ?? 60),
            'residual_value' => (float) ($_POST['residual_value'] ?? 0),
            'notes' => trim($_POST['notes'] ?? ''),
        ]);
        redirectWithMessage(pharma_erp_url('accounting/immobilisations.php'), 'Immobilisation enregistrée.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['depreciate_id'])) {
    $model->runDepreciation((int) $_POST['depreciate_id']);
    redirectWithMessage(pharma_erp_url('accounting/immobilisations.php'), 'Amortissement mensuel appliqué.', 'success');
}

$assets = $model->getAll(1, 50);

pharma_erp_page_start(['active' => 'accounting', 'title' => 'Immobilisations', 'icon' => 'fa-building']);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Comptabilité', 'icon' => 'fa-book', 'class' => 'btn-pharma-outline'],
]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="pharma-pro-panel"><div class="pharma-pro-panel-header">Nouvelle immobilisation</div><div class="pharma-pro-panel-body">
            <form method="post" class="row g-2">
                <input type="hidden" name="create_asset" value="1">
                <div class="col-12"><input type="text" name="label" class="form-control" placeholder="Libellé *" required></div>
                <div class="col-12"><input type="text" name="category" class="form-control" placeholder="Catégorie"></div>
                <div class="col-12"><input type="date" name="acquisition_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                <div class="col-12"><input type="number" step="0.01" name="acquisition_cost" class="form-control" placeholder="Coût acquisition" required></div>
                <div class="col-6"><input type="number" name="useful_life_months" class="form-control" value="60" placeholder="Durée (mois)"></div>
                <div class="col-6"><input type="number" step="0.01" name="residual_value" class="form-control" value="0" placeholder="Valeur résiduelle"></div>
                <div class="col-12"><button type="submit" class="btn btn-pharma-primary w-100">Ajouter</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-8">
        <div class="pharma-pro-panel"><div class="table-responsive">
            <table class="pharma-pro-table">
                <thead><tr><th>Code</th><th>Libellé</th><th>Coût</th><th>VNC</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($assets)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Aucune immobilisation</td></tr>
                <?php else: foreach ($assets as $a): ?>
                <tr>
                    <td><code><?= htmlspecialchars($a['asset_code']) ?></code></td>
                    <td><?= htmlspecialchars($a['label']) ?></td>
                    <td><?= pharma_erp_format_money((float) $a['acquisition_cost']) ?></td>
                    <td><?= pharma_erp_format_money((float) $a['net_book_value']) ?></td>
                    <td class="text-end">
                        <form method="post" class="d-inline"><input type="hidden" name="depreciate_id" value="<?= (int) $a['id'] ?>"><button type="submit" class="btn btn-sm btn-pharma-outline" title="Amortir 1 mois"><i class="fas fa-chart-line"></i></button></form>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div></div>
    </div>
</div>
<?php pharma_erp_page_end(); ?>
