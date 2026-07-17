<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PePurchase.php';

$model = new PePurchase();
$page = max(1, (int) ($_GET['page'] ?? 1));
$status = trim($_GET['status'] ?? '');
$orders = $model->getOrders($page, 20, $status);
$total = $model->getOrdersCount($status);

pharma_erp_page_start([
    'active' => 'purchases',
    'title' => 'Commandes d\'achat',
    'subtitle' => $total . ' commande(s)',
    'icon' => 'fa-file-invoice',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('purchases/ajouter.php'), 'label' => 'Nouvelle commande', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
    ['href' => pharma_erp_url('purchases/factures.php'), 'label' => 'Factures fournisseur', 'icon' => 'fa-file-invoice-dollar', 'class' => 'btn-pharma-outline'],
]);
?>

<div class="mb-3">
    <a href="?" class="btn btn-sm <?= $status === '' ? 'btn-pharma-primary' : 'btn-pharma-outline' ?>">Toutes</a>
    <a href="?status=ordered" class="btn btn-sm <?= $status === 'ordered' ? 'btn-pharma-primary' : 'btn-pharma-outline' ?>">Commandées</a>
    <a href="?status=partial" class="btn btn-sm <?= $status === 'partial' ? 'btn-pharma-primary' : 'btn-pharma-outline' ?>">Partielles</a>
    <a href="?status=received" class="btn btn-sm <?= $status === 'received' ? 'btn-pharma-primary' : 'btn-pharma-outline' ?>">Reçues</a>
</div>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr>
                    <th>N° BC</th>
                    <th>Date</th>
                    <th>Fournisseur</th>
                    <th class="text-end">Total TTC</th>
                    <th>Statut</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Aucune commande</td></tr>
                <?php else: foreach ($orders as $o): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                    <td><?= date('d/m/Y', strtotime($o['order_date'])) ?></td>
                    <td><?= htmlspecialchars($o['supplier_name']) ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $o['total_ttc']) ?></td>
                    <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($o['status']) ?></span></td>
                    <td class="text-end">
                        <?php if (in_array($o['status'], ['ordered', 'partial'], true)): ?>
                        <a href="<?= pharma_erp_url('purchases/reception.php?id=' . (int) $o['id']) ?>" class="btn btn-sm btn-pharma-secondary">
                            <i class="fas fa-truck-loading"></i> Réceptionner
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
