<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeBank.php';

$bankModel = new PeBank();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $label = trim($_POST['period_label'] ?? '');
        $from = $_POST['date_from'] ?? '';
        $to = $_POST['date_to'] ?? '';
        if ($label === '' || $from === '' || $to === '') {
            throw new InvalidArgumentException('Période incomplète.');
        }
        $id = $bankModel->createVatPeriod($label, $from, $to);
        if (!$id) {
            throw new RuntimeException('Erreur création période.');
        }
        redirectWithMessage(pharma_erp_url('accounting/tva.php'), 'Période TVA créée.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$periods = $bankModel->getVatPeriods();

pharma_erp_page_start([
    'active' => 'accounting',
    'title' => 'Déclarations TVA',
    'subtitle' => 'TVA collectée sur ventes POS',
    'icon' => 'fa-percent',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('accounting/'), 'label' => 'Retour compta', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('accounting/banque.php'), 'label' => 'Banque', 'icon' => 'fa-university', 'class' => 'btn-pharma-outline'],
]);
?>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Nouvelle période</div>
            <div class="pharma-pro-panel-body">
                <form method="post" class="row g-3">
                    <div class="col-12"><label class="form-label">Libellé</label><input name="period_label" class="form-control" placeholder="T1 2026" required></div>
                    <div class="col-6"><label class="form-label">Du</label><input type="date" name="date_from" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Au</label><input type="date" name="date_to" class="form-control" required></div>
                    <div class="col-12"><button type="submit" class="btn btn-pharma-primary w-100"><i class="fas fa-calculator me-1"></i> Calculer TVA collectée</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Périodes enregistrées</div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead><tr><th>Période</th><th>Du</th><th>Au</th><th class="text-end">TVA collectée</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php if (empty($periods)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">Aucune période — créez une déclaration</td></tr>
                        <?php else: foreach ($periods as $p): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($p['period_label']) ?></strong></td>
                            <td><?= date('d/m/Y', strtotime($p['date_from'])) ?></td>
                            <td><?= date('d/m/Y', strtotime($p['date_to'])) ?></td>
                            <td class="text-end"><?= pharma_erp_format_money((float) $p['vat_collected']) ?></td>
                            <td><span class="pe-badge pe-badge--<?= ($p['status'] ?? '') === 'closed' ? 'active' : 'warning' ?>"><?= htmlspecialchars($p['status']) ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
