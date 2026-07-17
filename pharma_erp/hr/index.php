<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeHr.php';
require_once __DIR__ . '/../../models/pharma_erp/PeHisFinanceBridge.php';

$employeeModel = new PeEmployee();
$payrollModel = new PePayroll();
$leaveModel = new PeLeave();

$employees = $employeeModel->getAll(1, 5);
$payrolls = $payrollModel->getRuns(5);
$leaveStats = $leaveModel->getStats();
$hisSync = PeHisFinanceBridge::isEnabled();

pharma_erp_page_start([
    'active' => 'hr',
    'title' => 'Ressources humaines',
    'subtitle' => 'Employés, paie et congés PharmaPro',
    'icon' => 'fa-users',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('hr/employes.php'), 'label' => 'Employés', 'icon' => 'fa-id-badge', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('hr/salaires.php'), 'label' => 'Paie', 'icon' => 'fa-money-check', 'class' => 'btn-pharma-outline'],
    ['href' => pharma_erp_url('hr/conges.php'), 'label' => 'Congés', 'icon' => 'fa-umbrella-beach', 'class' => 'btn-pharma-primary'],
]);
?>

<?php pharma_erp_kpi_cards([
    ['value' => (string) $employeeModel->getCount(), 'label' => 'Employés actifs', 'icon' => 'fa-user-tie'],
    ['value' => (string) count($payrolls), 'label' => 'Bulletins générés', 'icon' => 'fa-file-invoice-dollar', 'mod' => 'green'],
    ['value' => (string) ($leaveStats['pending'] ?? 0), 'label' => 'Congés en attente', 'icon' => 'fa-clock', 'mod' => 'amber'],
    ['value' => $hisSync ? 'Actif' : 'Inactif', 'label' => 'Sync HIS Finances', 'icon' => 'fa-link', 'mod' => $hisSync ? 'green' : 'rose'],
]); ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-id-badge"></i> Équipe pharmacie</div>
            <div class="pharma-pro-panel-body">
                <?php if (empty($employees)): ?>
                    <p class="text-muted mb-0">Aucun employé — <a href="<?= pharma_erp_url('hr/employes/ajouter.php') ?>">Ajouter</a></p>
                <?php else: foreach ($employees as $e): ?>
                    <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
                        <div>
                            <strong><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($e['job_title'] ?? '—') ?></small>
                        </div>
                        <span><?= pharma_erp_format_money((float) $e['salary_base']) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header"><i class="fas fa-money-check"></i> Dernières paies</div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead><tr><th>Période</th><th>Net</th><th>Statut</th></tr></thead>
                    <tbody>
                        <?php if (empty($payrolls)): ?>
                        <tr><td colspan="3" class="text-muted text-center py-3">Aucune paie</td></tr>
                        <?php else: foreach ($payrolls as $p): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['period_label']) ?></td>
                            <td><?= pharma_erp_format_money((float) $p['total_net']) ?></td>
                            <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($p['status']) ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
