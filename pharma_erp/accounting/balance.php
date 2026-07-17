<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeAccounting.php';
require_once __DIR__ . '/../../models/pharma_erp/PeAccountingEngine.php';

PeAccountingEngine::ensureSeed();
$balance = (new PeAccounting())->getBalance();

$totalDebit = 0.0;
$totalCredit = 0.0;
foreach ($balance as $row) {
    $totalDebit += (float) ($row['total_debit'] ?? 0);
    $totalCredit += (float) ($row['total_credit'] ?? 0);
}

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Balance générale',
    'subtitle' => 'Exercice en cours',
    'icon' => 'fa-balance-scale',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
?>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>Compte</th><th>Libellé</th><th>Classe</th><th class="text-end">Total débit</th><th class="text-end">Total crédit</th><th class="text-end">Solde</th></tr>
            </thead>
            <tbody>
                <?php foreach ($balance as $row):
                    if ((float) $row['total_debit'] == 0 && (float) $row['total_credit'] == 0 && (float) $row['current_balance'] == 0) continue;
                ?>
                <tr>
                    <td><code><?= htmlspecialchars($row['account_number']) ?></code></td>
                    <td><?= htmlspecialchars($row['account_label']) ?></td>
                    <td><?= (int) $row['account_class'] ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) ($row['total_debit'] ?? 0)) ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) ($row['total_credit'] ?? 0)) ?></td>
                    <td class="text-end fw-bold"><?= pharma_erp_format_money((float) $row['current_balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold">
                    <td colspan="3">TOTAUX</td>
                    <td class="text-end"><?= pharma_erp_format_money($totalDebit) ?></td>
                    <td class="text-end"><?= pharma_erp_format_money($totalCredit) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
