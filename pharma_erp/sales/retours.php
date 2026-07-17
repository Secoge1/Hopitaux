<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeReturn.php';

$model = new PeReturn();
$page = max(1, (int) ($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$returns = $model->getAll($page, 25, $search);
$total = $model->getCount($search);

pharma_erp_page_start([
    'active' => 'sales',
    'title' => 'Retours de vente',
    'subtitle' => $total . ' retour(s)',
    'icon' => 'fa-undo',
]);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('sales/'), 'label' => 'Historique ventes', 'icon' => 'fa-receipt', 'class' => 'btn-pharma-outline'],
]);
?>
<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead><tr><th>N° retour</th><th>Vente</th><th>Client</th><th>Date</th><th class="text-end">Remboursé</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($returns)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">Aucun retour</td></tr>
            <?php else: foreach ($returns as $r): ?>
            <tr>
                <td><code><?= htmlspecialchars($r['return_number']) ?></code></td>
                <td><?= htmlspecialchars($r['sale_number']) ?></td>
                <td><?= htmlspecialchars($r['customer_name'] ?: '—') ?></td>
                <td><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
                <td class="text-end"><strong><?= pharma_erp_format_money((float) $r['total_refund']) ?></strong></td>
                <td class="text-end"><a href="<?= htmlspecialchars(pharma_erp_url('sales/retour_voir.php?id=' . (int) $r['id'])) ?>" class="btn btn-sm btn-pharma-outline"><i class="fas fa-eye"></i></a></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php pharma_erp_page_end(); ?>
