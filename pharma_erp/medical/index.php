<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeMedical.php';

$model = new PeMedical();
$status = trim($_GET['status'] ?? '');
$prescriptions = $model->getPrescriptions(1, 30, $status);

pharma_erp_page_start([
    'active' => 'medical',
    'title' => 'Ordonnances',
    'subtitle' => 'Pont patients HIS · dispensation',
    'icon' => 'fa-file-medical',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('medical/ajouter.php'), 'label' => 'Nouvelle ordonnance', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
]);
?>

<div class="pharma-pro-panel mb-3">
    <div class="pharma-pro-panel-body">
        <div class="btn-group btn-group-sm">
            <a href="<?= htmlspecialchars(pharma_erp_url('medical/')) ?>" class="btn btn-pharma-outline<?= $status === '' ? ' active' : '' ?>">Toutes</a>
            <a href="<?= htmlspecialchars(pharma_erp_url('medical/?status=pending')) ?>" class="btn btn-pharma-outline<?= $status === 'pending' ? ' active' : '' ?>">En attente</a>
            <a href="<?= htmlspecialchars(pharma_erp_url('medical/?status=dispensed')) ?>" class="btn btn-pharma-outline<?= $status === 'dispensed' ? ' active' : '' ?>">Dispensées</a>
        </div>
    </div>
</div>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>N°</th><th>Patient</th><th>Prescripteur</th><th>Date</th><th>Statut</th></tr>
            </thead>
            <tbody>
                <?php if (empty($prescriptions)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Aucune ordonnance</td></tr>
                <?php else: foreach ($prescriptions as $rx): ?>
                <tr>
                    <td><code><?= htmlspecialchars($rx['prescription_number']) ?></code></td>
                    <td><strong><?= htmlspecialchars(trim(($rx['first_name'] ?? '') . ' ' . ($rx['last_name'] ?? '')) ?: '—') ?></strong>
                        <?php if (!empty($rx['phone'])): ?><br><small class="text-muted"><?= htmlspecialchars($rx['phone']) ?></small><?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($rx['prescriber_name'] ?? '—') ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($rx['created_at'])) ?></td>
                    <td><span class="pe-badge pe-badge--<?= ($rx['status'] ?? '') === 'pending' ? 'warning' : 'active' ?>"><?= htmlspecialchars($rx['status']) ?></span></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
