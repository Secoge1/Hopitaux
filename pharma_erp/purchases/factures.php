<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeSupplierInvoice.php';

$model = new PeSupplierInvoice();
$page = max(1, (int) ($_GET['page'] ?? 1));
$status = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');
$invoices = $model->getAll($page, 25, $status, $search);
$total = $model->getCount($status, $search);
$summary = $model->getSummary();

pharma_erp_page_start([
    'active' => 'purchases',
    'title' => 'Factures fournisseur',
    'subtitle' => $summary['total_due'] > 0 ? 'Reste dû : ' . pharma_erp_format_money($summary['total_due']) : 'Aucune dette en cours',
    'icon' => 'fa-file-invoice-dollar',
]);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('purchases/'), 'label' => 'Commandes', 'icon' => 'fa-file-invoice', 'class' => 'btn-pharma-outline'],
]);
?>
<div class="pharma-pro-panel mb-3"><div class="pharma-pro-panel-body row g-2 text-center">
    <div class="col-md-3"><span class="pe-badge pe-badge--warning"><?= (int) $summary['pending'] ?> en attente</span></div>
    <div class="col-md-3"><span class="pe-badge"><?= (int) $summary['partial'] ?> partielles</span></div>
    <div class="col-md-3"><span class="pe-badge pe-badge--active"><?= (int) $summary['paid'] ?> payées (période)</span></div>
</div></div>
<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead><tr><th>N° facture</th><th>Fournisseur</th><th>Réception</th><th>Date</th><th class="text-end">TTC</th><th class="text-end">Payé</th><th>Statut</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($invoices)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Aucune facture — créées à la réception des achats</td></tr>
            <?php else: foreach ($invoices as $inv): ?>
            <tr>
                <td><code><?= htmlspecialchars($inv['invoice_number']) ?></code></td>
                <td><?= htmlspecialchars($inv['supplier_name']) ?></td>
                <td><?= htmlspecialchars($inv['receipt_number'] ?? '—') ?></td>
                <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                <td class="text-end"><?= pharma_erp_format_money((float) $inv['amount_ttc']) ?></td>
                <td class="text-end"><?= pharma_erp_format_money((float) $inv['amount_paid']) ?></td>
                <td><span class="pe-badge"><?= htmlspecialchars($inv['status']) ?></span></td>
                <td class="text-end">
                    <?php if (in_array($inv['status'], ['pending', 'partial'], true)): ?>
                    <a href="<?= htmlspecialchars(pharma_erp_url('purchases/facture_payer.php?id=' . (int) $inv['id'])) ?>" class="btn btn-sm btn-pharma-primary"><i class="fas fa-money-bill"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php pharma_erp_page_end(); ?>
