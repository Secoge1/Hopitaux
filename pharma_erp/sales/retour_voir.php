<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeReturn.php';

$id = (int) ($_GET['id'] ?? 0);
$model = new PeReturn();
$ret = $model->getById($id);
if (!$ret) {
    redirectWithMessage(pharma_erp_url('sales/retours.php'), 'Retour introuvable.', 'warning');
}

pharma_erp_page_start(['active' => 'sales', 'title' => 'Retour ' . $ret['return_number'], 'icon' => 'fa-undo']);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('sales/retours.php'), 'label' => 'Liste retours', 'icon' => 'fa-list', 'class' => 'btn-pharma-outline'],
]);
?>
<div class="pharma-pro-panel mb-3"><div class="pharma-pro-panel-body">
    Vente <code><?= htmlspecialchars($ret['sale_number']) ?></code> ·
    Client <?= htmlspecialchars($ret['customer_name'] ?: '—') ?> ·
    <?= date('d/m/Y H:i', strtotime($ret['created_at'])) ?>
    <?php if (!empty($ret['reason'])): ?> · <?= htmlspecialchars($ret['reason']) ?><?php endif; ?>
</div></div>
<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead><tr><th>Produit</th><th>Qté</th><th class="text-end">P.U.</th><th class="text-end">Total</th></tr></thead>
            <tbody>
            <?php foreach ($ret['lines'] as $l): ?>
            <tr>
                <td><?= htmlspecialchars($l['product_name'] ?? 'Produit #' . $l['product_id']) ?></td>
                <td><?= (int) $l['quantity'] ?></td>
                <td class="text-end"><?= pharma_erp_format_money((float) $l['unit_price']) ?></td>
                <td class="text-end"><?= pharma_erp_format_money((float) $l['line_total']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="3" class="text-end">Total remboursé</th><th class="text-end"><?= pharma_erp_format_money((float) $ret['total_refund']) ?></th></tr></tfoot>
        </table>
    </div>
</div>
<?php pharma_erp_page_end(); ?>
