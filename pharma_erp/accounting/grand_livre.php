<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeReporting.php';
require_once __DIR__ . '/../../models/pharma_erp/PeAccounting.php';

$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$reporting = new PeReporting();
$grouped = $reporting->getGrandLivreGrouped($dateFrom, $dateTo);
$accounts = (new PeAccounting())->getAccounts();

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Grand Livre',
    'subtitle' => 'Mouvements comptables par compte SYSCOHADA',
    'icon' => 'fa-book-open',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/export_grand_livre_pdf.php?date_from=' . urlencode($dateFrom) . '&date_to=' . urlencode($dateTo)), 'label' => 'Export PDF', 'icon' => 'fa-file-pdf', 'class' => 'btn-pharma-primary', 'target' => '_blank'],
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
?>

<form method="get" class="row g-2 mb-3 align-items-end">
    <div class="col-auto"><label class="form-label small">Du</label><input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($dateFrom) ?>"></div>
    <div class="col-auto"><label class="form-label small">Au</label><input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($dateTo) ?>"></div>
    <div class="col-auto"><button type="submit" class="btn btn-sm btn-pharma-outline">Filtrer</button></div>
</form>

<?php foreach ($grouped as $account): ?>
<div class="pharma-pro-panel mb-4">
    <div class="pharma-pro-panel-header">
        <?= htmlspecialchars($account['account_number']) ?> — <?= htmlspecialchars($account['account_label']) ?>
    </div>
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead><tr><th>Date</th><th>N°</th><th>Jnl</th><th>Libellé</th><th class="text-end">Débit</th><th class="text-end">Crédit</th></tr></thead>
            <tbody>
                <?php foreach ($account['lines'] as $line): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($line['entry_date'])) ?></td>
                    <td><code><?= htmlspecialchars($line['entry_number']) ?></code></td>
                    <td><?= htmlspecialchars($line['journal_code']) ?></td>
                    <td><?= htmlspecialchars($line['line_label'] ?: $line['entry_label']) ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $line['debit']) ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $line['credit']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="4" class="text-end">Totaux</td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $account['total_debit']) ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $account['total_credit']) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php if (empty($grouped)): ?>
<div class="alert alert-info">Aucune écriture sur cette période.</div>
<?php endif; ?>

<?php pharma_erp_page_end(); ?>
