<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeInventory.php';

$inventoryModel = new PeInventory();
$id = (int) ($_GET['id'] ?? 0);
$inventory = $id > 0 ? $inventoryModel->findById($id) : null;
$error = '';
$success = '';

if (!$inventory) {
    redirectWithMessage(pharma_erp_url('stock/inventaire.php'), 'Inventaire introuvable.', 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['validate'])) {
            if (!$inventoryModel->validate($id)) {
                throw new RuntimeException('Validation impossible.');
            }
            redirectWithMessage(pharma_erp_url('stock/inventaire.php'), 'Inventaire validé — stock ajusté.', 'success');
        }
        if (isset($_POST['line_id'])) {
            $inventoryModel->updateLineCount((int) $_POST['line_id'], (int) ($_POST['counted_qty'] ?? 0));
            $success = 'Quantité mise à jour.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    $inventory = $inventoryModel->findById($id);
}

$lines = $inventoryModel->getLines($id);
$isOpen = ($inventory['status'] ?? '') === 'counting';

pharma_erp_page_start([
    'active' => 'stock',
    'title' => 'Inventaire ' . ($inventory['inventory_number'] ?? ''),
    'subtitle' => $inventory['deposit_name'] ?? '',
    'icon' => 'fa-clipboard-check',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('stock/inventaire.php'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-list"></i> Lignes de comptage (<?= count($lines) ?>)</span>
        <?php if ($isOpen): ?>
        <form method="post" class="d-inline" onsubmit="return confirm('Valider et ajuster le stock ?');">
            <button type="submit" name="validate" value="1" class="btn btn-sm btn-pharma-primary"><i class="fas fa-check me-1"></i> Valider inventaire</button>
        </form>
        <?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>Produit</th><th>SKU</th><th class="text-end">Système</th><th class="text-end">Compté</th><th class="text-end">Écart</th><?php if ($isOpen): ?><th></th><?php endif; ?></tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $line):
                    $variance = (int) $line['counted_qty'] - (int) $line['system_qty'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($line['product_name']) ?></td>
                    <td><code><?= htmlspecialchars($line['sku']) ?></code></td>
                    <td class="text-end"><?= (int) $line['system_qty'] ?></td>
                    <td class="text-end"><?= (int) $line['counted_qty'] ?></td>
                    <td class="text-end <?= $variance !== 0 ? ($variance > 0 ? 'text-success' : 'text-danger') : '' ?>">
                        <?= $variance > 0 ? '+' : '' ?><?= $variance ?>
                    </td>
                    <?php if ($isOpen): ?>
                    <td class="text-end" style="min-width:140px">
                        <form method="post" class="d-flex gap-1 justify-content-end">
                            <input type="hidden" name="line_id" value="<?= (int) $line['id'] ?>">
                            <input type="number" name="counted_qty" class="form-control form-control-sm" style="width:70px" min="0" value="<?= (int) $line['counted_qty'] ?>">
                            <button type="submit" class="btn btn-sm btn-pharma-outline"><i class="fas fa-save"></i></button>
                        </form>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
