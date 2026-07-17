<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeInventory.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$inventoryModel = new PeInventory();
$pharmacyModel = new PePharmacy();
$pharmacy = $pharmacyModel->getDefault();
$deposits = $pharmacy ? $pharmacyModel->getDeposits((int) $pharmacy['id']) : [];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['deposit_id'])) {
    try {
        $id = $inventoryModel->create((int) $_POST['deposit_id'], $pharmacy ? (int) $pharmacy['id'] : null);
        if (!$id) {
            throw new RuntimeException('Impossible de créer l\'inventaire.');
        }
        redirectWithMessage(pharma_erp_url('stock/inventaire_detail.php?id=' . $id), 'Inventaire ouvert.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$inventories = $inventoryModel->getAll($page, 20);

pharma_erp_page_start([
    'active' => 'stock',
    'title' => 'Inventaires physiques',
    'subtitle' => 'Comptage et ajustements de stock',
    'icon' => 'fa-clipboard-list',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('stock/'), 'label' => 'Retour stock', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-plus"></i> Nouvel inventaire</div>
            <div class="pharma-pro-panel-body">
                <?php if (empty($deposits)): ?>
                <p class="text-muted mb-0">Aucun dépôt configuré.</p>
                <?php else: ?>
                <form method="post">
                    <label class="form-label">Dépôt</label>
                    <select name="deposit_id" class="form-select mb-3" required>
                        <?php foreach ($deposits as $d): ?>
                        <option value="<?= (int) $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-pharma-primary w-100"><i class="fas fa-play me-1"></i> Démarrer</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-history"></i> Inventaires</div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead>
                        <tr><th>N°</th><th>Dépôt</th><th>Statut</th><th>Démarré</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventories)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun inventaire</td></tr>
                        <?php else: foreach ($inventories as $inv): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($inv['inventory_number']) ?></code></td>
                            <td><?= htmlspecialchars($inv['deposit_name'] ?? '—') ?></td>
                            <td><span class="pe-badge pe-badge--<?= $inv['status'] === 'validated' ? 'active' : 'warning' ?>"><?= htmlspecialchars($inv['status']) ?></span></td>
                            <td><?= date('d/m/Y H:i', strtotime($inv['started_at'])) ?></td>
                            <td class="text-end">
                                <a href="<?= htmlspecialchars(pharma_erp_url('stock/inventaire_detail.php?id=' . (int) $inv['id'])) ?>" class="btn btn-sm btn-pharma-outline">Ouvrir</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
