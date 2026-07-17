<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeHr.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$payrollModel = new PePayroll();
$pharmacyModel = new PePharmacy();
$pharmacy = $pharmacyModel->getDefault();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate'])) {
    try {
        if (!$pharmacy) {
            throw new RuntimeException('Officine non configurée.');
        }
        $result = $payrollModel->generateRun(
            (int) $pharmacy['id'],
            trim($_POST['period_label'] ?? date('F Y')),
            $_POST['period_start'] ?? date('Y-m-01'),
            $_POST['period_end'] ?? date('Y-m-t')
        );
        redirectWithMessage(pharma_erp_url('hr/salaires.php'), 'Paie ' . $result['run_number'] . ' générée.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_id'])) {
    try {
        if ($payrollModel->validateRun((int) $_POST['validate_id'])) {
            redirectWithMessage(pharma_erp_url('hr/salaires.php'), 'Paie validée — écriture comptable générée.', 'success');
        }
        $error = 'Validation impossible.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$runs = $payrollModel->getRuns(20);

pharma_erp_page_start(['active' => 'hr', 'title' => 'Paie & salaires', 'icon' => 'fa-money-check']);
pharma_erp_toolbar([['href' => pharma_erp_url('hr/'), 'label' => 'Retour RH', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="pharma-pro-panel mb-4">
    <div class="pharma-pro-panel-header">Générer une paie</div>
    <div class="pharma-pro-panel-body">
        <form method="post" class="row g-3 align-items-end">
            <input type="hidden" name="generate" value="1">
            <div class="col-md-4"><label class="form-label">Période</label><input name="period_label" class="form-control" value="<?= date('F Y') ?>" required></div>
            <div class="col-md-3"><label class="form-label">Début</label><input name="period_start" type="date" class="form-control" value="<?= date('Y-m-01') ?>"></div>
            <div class="col-md-3"><label class="form-label">Fin</label><input name="period_end" type="date" class="form-control" value="<?= date('Y-m-t') ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn btn-pharma-primary w-100">Générer</button></div>
        </form>
    </div>
</div>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead><tr><th>N°</th><th>Période</th><th>Brut</th><th>Net</th><th>Statut</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($runs as $r): ?>
                <tr>
                    <td><code><?= htmlspecialchars($r['run_number']) ?></code></td>
                    <td><?= htmlspecialchars($r['period_label']) ?></td>
                    <td><?= pharma_erp_format_money((float) $r['total_gross']) ?></td>
                    <td><strong><?= pharma_erp_format_money((float) $r['total_net']) ?></strong></td>
                    <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($r['status']) ?></span></td>
                    <td class="text-end">
                        <?php if ($r['status'] === 'draft'): ?>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="validate_id" value="<?= (int) $r['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-pharma-secondary">Valider & comptabiliser</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
