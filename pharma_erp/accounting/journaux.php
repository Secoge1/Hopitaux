<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeAccounting.php';
require_once __DIR__ . '/../../models/pharma_erp/PeHisFinanceBridge.php';

$model = new PeAccounting();
$page = max(1, (int) ($_GET['page'] ?? 1));
$entries = $model->getEntries($page, 30);
$hisSync = PeHisFinanceBridge::isEnabled();

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Journaux comptables',
    'icon' => 'fa-book-open',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
if ($hisSync): ?>
<div class="alert alert-success py-2 small"><i class="fas fa-link me-1"></i> Pont HIS actif — écritures répliquées dans Finances</div>
<?php endif; ?>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>N° écriture</th><th>Date</th><th>Journal</th><th>Libellé</th><th>Réf.</th><th class="text-end">Débit</th><th class="text-end">Crédit</th></tr>
            </thead>
            <tbody>
                <?php if (empty($entries)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">Aucune écriture</td></tr>
                <?php else: foreach ($entries as $e): ?>
                <tr>
                    <td><code><?= htmlspecialchars($e['entry_number']) ?></code></td>
                    <td><?= date('d/m/Y', strtotime($e['entry_date'])) ?></td>
                    <td><?= htmlspecialchars($e['journal_code']) ?></td>
                    <td><?= htmlspecialchars($e['label']) ?></td>
                    <td><small><?= htmlspecialchars(($e['reference_type'] ?? '') . ' #' . ($e['reference_id'] ?? '')) ?></small></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $e['total_debit']) ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $e['total_credit']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
