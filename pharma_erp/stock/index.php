<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeStock.php';
require_once __DIR__ . '/../../models/pharma_erp/PeProduct.php';

$stockModel = new PeStock();
$productModel = new PeProduct();
$page = max(1, (int) ($_GET['page'] ?? 1));
$movements = $stockModel->getMovements($page, 30);
$expiryAlerts = $stockModel->getExpiryAlerts(90, 15);
$lowStock = $productModel->getLowStock(15);
$stockValue = $stockModel->getStockValue();

pharma_erp_page_start([
    'active' => 'stock',
    'title' => 'Gestion du stock',
    'subtitle' => 'Mouvements, alertes et valorisation',
    'icon' => 'fa-boxes-stacked',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('stock/entree.php'), 'label' => 'Entrée stock', 'icon' => 'fa-arrow-down', 'class' => 'btn-pharma-primary'],
    ['href' => pharma_erp_url('stock/inventaire.php'), 'label' => 'Inventaire', 'icon' => 'fa-clipboard-list', 'class' => 'btn-pharma-outline'],
]);
?>

<?php pharma_erp_kpi_cards([
    ['value' => pharma_erp_format_money($stockValue), 'label' => 'Valeur totale du stock', 'icon' => 'fa-coins', 'mod' => 'green'],
    ['value' => (string) count($lowStock), 'label' => 'Produits en rupture', 'icon' => 'fa-triangle-exclamation', 'mod' => 'rose'],
    ['value' => (string) count($expiryAlerts), 'label' => 'Lots proches péremption', 'icon' => 'fa-calendar-xmark', 'mod' => 'amber'],
]); ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-exchange-alt"></i> Derniers mouvements</div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead>
                        <tr><th>Date</th><th>Produit</th><th>Type</th><th>Dépôt</th><th class="text-end">Qté</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movements)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucun mouvement</td></tr>
                        <?php else: foreach ($movements as $m): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                            <td><?= htmlspecialchars($m['product_name']) ?></td>
                            <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($m['movement_type']) ?></span></td>
                            <td><?= htmlspecialchars($m['deposit_name']) ?></td>
                            <td class="text-end <?= (int) $m['quantity'] < 0 ? 'text-danger' : 'text-success' ?>">
                                <?= (int) $m['quantity'] > 0 ? '+' : '' ?><?= (int) $m['quantity'] ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="pharma-pro-panel mb-4">
            <div class="pharma-pro-panel-header"><i class="fas fa-hourglass-half"></i> Péremptions</div>
            <div class="pharma-pro-panel-body">
                <?php foreach ($expiryAlerts as $lot): ?>
                <div class="mb-2 pb-2 border-bottom small">
                    <strong><?= htmlspecialchars($lot['product_name']) ?></strong><br>
                    Lot <?= htmlspecialchars($lot['lot_number']) ?> — <?= (int) $lot['current_qty'] ?> u.
                    <span class="pe-badge pe-badge--warning float-end"><?= date('d/m/Y', strtotime($lot['expiry_date'])) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if (empty($expiryAlerts)): ?><p class="text-muted mb-0">Aucune alerte</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
