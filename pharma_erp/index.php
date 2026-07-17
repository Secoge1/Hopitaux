<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../models/pharma_erp/PeSale.php';
require_once __DIR__ . '/../models/pharma_erp/PeProduct.php';
require_once __DIR__ . '/../models/pharma_erp/PeStock.php';
require_once __DIR__ . '/../models/pharma_erp/PePharmacy.php';

$saleModel = new PeSale();
$productModel = new PeProduct();
$stockModel = new PeStock();
$pharmacyModel = new PePharmacy();

$stats = $saleModel->getDashboardStats(30);
$recentSales = $saleModel->getRecentSales(8);
$salesTrend = $saleModel->getSalesTrend(14);
$topProducts = $productModel->getTopSelling(5, 30);
$lowStock = $productModel->getLowStock(8);
$expiryAlerts = $stockModel->getExpiryAlerts(90, 8);
$pharmacy = $pharmacyModel->getDefault();

$trendLabels = json_encode(array_column($salesTrend, 'date'), JSON_UNESCAPED_UNICODE);
$trendTotals = json_encode(array_column($salesTrend, 'total'));
$trendProfits = json_encode(array_column($salesTrend, 'profit'));

pharma_erp_page_start([
    'active' => 'dashboard',
    'title' => 'Tableau de bord',
    'subtitle' => $pharmacy ? 'Officine : ' . $pharmacy['name'] : 'PharmaPro ERP',
    'icon' => 'fa-chart-line',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('pos/'), 'label' => 'Ouvrir la caisse', 'icon' => 'fa-cash-register', 'class' => 'btn-pharma-secondary'],
    ['href' => pharma_erp_url('products/ajouter.php'), 'label' => 'Nouveau produit', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
]);
?>

<?php pharma_erp_kpi_cards([
    ['value' => pharma_erp_format_money($stats['sales_today'] ?? 0), 'label' => 'Ventes aujourd\'hui', 'icon' => 'fa-coins', 'mod' => 'green'],
    ['value' => pharma_erp_format_money($stats['profit_today'] ?? 0), 'label' => 'Bénéfice journalier', 'icon' => 'fa-chart-pie', 'mod' => 'green'],
    ['value' => (string) ($stats['products_active'] ?? 0), 'label' => 'Produits actifs', 'icon' => 'fa-capsules'],
    ['value' => pharma_erp_format_money($stats['stock_value'] ?? 0), 'label' => 'Valeur du stock', 'icon' => 'fa-warehouse', 'mod' => 'amber'],
    ['value' => (string) ($stats['low_stock'] ?? 0), 'label' => 'Alertes rupture', 'icon' => 'fa-triangle-exclamation', 'mod' => 'rose'],
    ['value' => (string) ($stats['expiry_alerts'] ?? 0), 'label' => 'Péremptions < 90j', 'icon' => 'fa-calendar-xmark', 'mod' => 'rose'],
]); ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="pharma-pro-panel mb-4">
            <div class="pharma-pro-panel-header">
                <i class="fas fa-chart-area text-primary"></i> Évolution des ventes (14 jours)
            </div>
            <div class="pharma-pro-panel-body">
                <canvas id="peSalesChart" height="100"></canvas>
            </div>
        </div>

        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">
                <i class="fas fa-receipt text-primary"></i> Dernières ventes
            </div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead>
                        <tr>
                            <th>N°</th>
                            <th>Date</th>
                            <th>Client</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Bénéfice</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentSales)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune vente enregistrée</td></tr>
                        <?php else: foreach ($recentSales as $sale): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($sale['completed_at'])) ?></td>
                            <td><?= htmlspecialchars($sale['customer_name'] ?: '—') ?></td>
                            <td class="text-end"><?= pharma_erp_format_money((float) $sale['total_ttc']) ?></td>
                            <td class="text-end text-success"><?= pharma_erp_format_money((float) $sale['profit_amount']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="pharma-pro-panel mb-4">
            <div class="pharma-pro-panel-header">
                <i class="fas fa-fire text-warning"></i> Top produits (30j)
            </div>
            <div class="pharma-pro-panel-body">
                <?php if (empty($topProducts)): ?>
                    <p class="text-muted mb-0">Pas encore de données</p>
                <?php else: foreach ($topProducts as $i => $p): ?>
                    <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                        <div>
                            <span class="badge bg-primary me-1"><?= $i + 1 ?></span>
                            <?= htmlspecialchars($p['name']) ?>
                        </div>
                        <strong><?= (int) $p['qty_sold'] ?> u.</strong>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="pharma-pro-panel mb-4">
            <div class="pharma-pro-panel-header">
                <i class="fas fa-box-open text-danger"></i> Stock faible
            </div>
            <div class="pharma-pro-panel-body">
                <?php if (empty($lowStock)): ?>
                    <p class="text-muted mb-0">Aucune alerte</p>
                <?php else: foreach ($lowStock as $item): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= htmlspecialchars($item['name']) ?></span>
                        <span class="pe-badge pe-badge--danger"><?= (int) $item['stock_total'] ?> / <?= (int) $item['reorder_level'] ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">
                <i class="fas fa-hourglass-half text-warning"></i> Péremptions proches
            </div>
            <div class="pharma-pro-panel-body">
                <?php if (empty($expiryAlerts)): ?>
                    <p class="text-muted mb-0">Aucune alerte</p>
                <?php else: foreach ($expiryAlerts as $lot): ?>
                    <div class="d-flex justify-content-between mb-2 small">
                        <span><?= htmlspecialchars($lot['product_name']) ?></span>
                        <span class="pe-badge pe-badge--warning"><?= date('d/m/Y', strtotime($lot['expiry_date'])) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const ctx = document.getElementById('peSalesChart');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= $trendLabels ?>,
            datasets: [
                {
                    label: 'Ventes (FCFA)',
                    data: <?= $trendTotals ?>,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14, 165, 233, 0.1)',
                    fill: true,
                    tension: 0.4,
                },
                {
                    label: 'Bénéfice (FCFA)',
                    data: <?= $trendProfits ?>,
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>

<?php pharma_erp_page_end(); ?>
