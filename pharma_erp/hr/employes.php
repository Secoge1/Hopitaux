<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeHr.php';

$model = new PeEmployee();
$employees = $model->getAll(1, 50, trim($_GET['search'] ?? ''));

pharma_erp_page_start(['active' => 'hr', 'title' => 'Employés PharmaPro', 'icon' => 'fa-id-badge']);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('hr/employes/ajouter.php'), 'label' => 'Ajouter', 'icon' => 'fa-plus', 'class' => 'btn-pharma-primary'],
    ['href' => pharma_erp_url('hr/'), 'label' => 'Retour RH', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);
?>

<div class="pharma-pro-panel">
    <div class="table-responsive">
        <table class="pharma-pro-table">
            <thead>
                <tr><th>Code</th><th>Nom</th><th>Poste</th><th>Département</th><th class="text-end">Salaire base</th><th>Statut</th><th></th></tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $e): ?>
                <tr>
                    <td><code><?= htmlspecialchars($e['employee_code']) ?></code></td>
                    <td><strong><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></strong></td>
                    <td><?= htmlspecialchars($e['job_title'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($e['department'] ?? '—') ?></td>
                    <td class="text-end"><?= pharma_erp_format_money((float) $e['salary_base']) ?></td>
                    <td><span class="pe-badge pe-badge--active"><?= htmlspecialchars($e['status']) ?></span></td>
                    <td class="text-end">
                        <a href="<?= htmlspecialchars(pharma_erp_url('hr/employes/modifier.php?id=' . (int) $e['id'])) ?>" class="btn btn-sm btn-pharma-outline"><i class="fas fa-edit"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
