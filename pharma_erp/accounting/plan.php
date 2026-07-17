<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeAccounting.php';
require_once __DIR__ . '/../../models/pharma_erp/PeAccountingEngine.php';

PeAccountingEngine::ensureSeed();
$accounts = (new PeAccounting())->getAccounts(trim($_GET['search'] ?? ''));

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Plan comptable SYSCOHADA',
    'subtitle' => count($accounts) . ' compte(s)',
    'icon' => 'fa-list',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Retour compta', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
?>

<form method="get" class="mb-3">
    <input type="text" name="search" class="form-control" placeholder="Rechercher un compte…" value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
</form>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>N° compte</th><th>Libellé</th><th>Classe</th><th>Type</th><th class="text-end">Solde</th></tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $a): ?>
                <tr>
                    <td><code><?= htmlspecialchars($a['account_number']) ?></code></td>
                    <td><?= htmlspecialchars($a['account_label']) ?></td>
                    <td><?= (int) $a['account_class'] ?></td>
                    <td><?= htmlspecialchars($a['account_type']) ?></td>
                    <td class="text-end fw-semibold"><?= pharma_erp_format_money((float) $a['current_balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
