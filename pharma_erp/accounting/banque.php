<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeBank.php';

$bankModel = new PeBank();
$error = '';
$selectedAccountId = (int) ($_GET['account_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!empty($_POST['create_account'])) {
            $id = $bankModel->createAccount([
                'account_number' => trim($_POST['account_number'] ?? ''),
                'bank_name' => trim($_POST['bank_name'] ?? ''),
                'label' => trim($_POST['label'] ?? ''),
                'opening_balance' => (float) ($_POST['opening_balance'] ?? 0),
            ]);
            if (!$id) {
                throw new RuntimeException('Erreur création compte.');
            }
            redirectWithMessage(pharma_erp_url('accounting/banque.php?account_id=' . $id), 'Compte bancaire créé.', 'success');
        }
        if (!empty($_POST['add_movement'])) {
            $movId = $bankModel->addMovement([
                'bank_account_id' => (int) $_POST['bank_account_id'],
                'movement_date' => $_POST['movement_date'] ?? date('Y-m-d'),
                'label' => trim($_POST['label'] ?? ''),
                'debit' => (float) ($_POST['debit'] ?? 0),
                'credit' => (float) ($_POST['credit'] ?? 0),
                'reference' => trim($_POST['reference'] ?? ''),
            ]);
            if (!$movId) {
                throw new RuntimeException('Erreur mouvement.');
            }
            redirectWithMessage(pharma_erp_url('accounting/banque.php?account_id=' . (int) $_POST['bank_account_id']), 'Mouvement enregistré.', 'success');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$accounts = $bankModel->getAccounts();
if (!$selectedAccountId && !empty($accounts[0]['id'])) {
    $selectedAccountId = (int) $accounts[0]['id'];
}
$movements = $selectedAccountId ? $bankModel->getMovements($selectedAccountId) : [];
$selectedAccount = null;
foreach ($accounts as $acc) {
    if ((int) $acc['id'] === $selectedAccountId) {
        $selectedAccount = $acc;
        break;
    }
}

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Banque',
    'subtitle' => 'Comptes et mouvements bancaires',
    'icon' => 'fa-university',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Retour compta', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/tva.php'), 'label' => 'TVA', 'icon' => 'fa-percent', 'class' => 'btn-pharma-outline'],
]);
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="pharma-pro-panel mb-4">
            <div class="pharma-pro-panel-header">Comptes</div>
            <div class="pharma-pro-panel-body">
                <?php if (empty($accounts)): ?>
                <p class="text-muted small mb-0">Aucun compte bancaire.</p>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($accounts as $acc): ?>
                    <a href="<?= htmlspecialchars(pharma_erp_url('accounting/banque.php?account_id=' . (int) $acc['id'])) ?>"
                       class="list-group-item list-group-item-action<?= (int) $acc['id'] === $selectedAccountId ? ' active' : '' ?>">
                        <strong><?= htmlspecialchars($acc['bank_name']) ?></strong><br>
                        <small><?= htmlspecialchars($acc['account_number']) ?> — <?= pharma_erp_format_money((float) $acc['current_balance']) ?></small>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Nouveau compte</div>
            <div class="pharma-pro-panel-body">
                <form method="post" class="row g-2">
                    <input type="hidden" name="create_account" value="1">
                    <div class="col-12"><input name="bank_name" class="form-control form-control-sm" placeholder="Banque *" required></div>
                    <div class="col-12"><input name="account_number" class="form-control form-control-sm" placeholder="N° compte *" required></div>
                    <div class="col-12"><input name="label" class="form-control form-control-sm" placeholder="Libellé"></div>
                    <div class="col-12"><input name="opening_balance" type="number" class="form-control form-control-sm" placeholder="Solde initial" value="0"></div>
                    <div class="col-12"><button type="submit" class="btn btn-sm btn-pharma-primary w-100">Créer</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php if ($selectedAccount): ?>
        <div class="pharma-pro-panel mb-4">
            <div class="pharma-pro-panel-header">
                <?= htmlspecialchars($selectedAccount['label'] ?: $selectedAccount['bank_name']) ?>
                — Solde : <strong><?= pharma_erp_format_money((float) $selectedAccount['current_balance']) ?></strong>
            </div>
            <div class="pharma-pro-panel-body">
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="add_movement" value="1">
                    <input type="hidden" name="bank_account_id" value="<?= (int) $selectedAccountId ?>">
                    <div class="col-md-3"><label class="form-label small">Date</label><input type="date" name="movement_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-md-4"><label class="form-label small">Libellé</label><input name="label" class="form-control form-control-sm" required></div>
                    <div class="col-md-2"><label class="form-label small">Débit</label><input name="debit" type="number" class="form-control form-control-sm" min="0" step="0.01" value="0"></div>
                    <div class="col-md-2"><label class="form-label small">Crédit</label><input name="credit" type="number" class="form-control form-control-sm" min="0" step="0.01" value="0"></div>
                    <div class="col-md-1"><button type="submit" class="btn btn-sm btn-pharma-primary"><i class="fas fa-plus"></i></button></div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Mouvements</div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead><tr><th>Date</th><th>Libellé</th><th class="text-end">Débit</th><th class="text-end">Crédit</th></tr></thead>
                    <tbody>
                        <?php if (empty($movements)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Aucun mouvement</td></tr>
                        <?php else: foreach ($movements as $m): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($m['movement_date'])) ?></td>
                            <td><?= htmlspecialchars($m['label']) ?></td>
                            <td class="text-end text-danger"><?= (float) $m['debit'] > 0 ? pharma_erp_format_money((float) $m['debit']) : '—' ?></td>
                            <td class="text-end text-success"><?= (float) $m['credit'] > 0 ? pharma_erp_format_money((float) $m['credit']) : '—' ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
