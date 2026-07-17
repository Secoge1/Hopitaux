<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeHr.php';

$leaveModel = new PeLeave();
$employeeModel = new PeEmployee();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['review_id'], $_POST['review_status'])) {
        $leaveModel->review((int) $_POST['review_id'], $_POST['review_status']);
        redirectWithMessage(pharma_erp_url('hr/conges.php'), 'Demande traitée.', 'success');
    } else {
        try {
            $leaveModel->create([
                'employee_id' => (int) $_POST['employee_id'],
                'leave_type' => $_POST['leave_type'] ?? 'annual',
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'reason' => trim($_POST['reason'] ?? ''),
            ]);
            redirectWithMessage(pharma_erp_url('hr/conges.php'), 'Demande enregistrée.', 'success');
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$leaves = $leaveModel->getAll();
$employees = $employeeModel->getActiveForPayroll();
$filter = $_GET['status'] ?? '';

pharma_erp_page_start(['active' => 'hr', 'title' => 'Congés', 'icon' => 'fa-umbrella-beach']);
pharma_erp_toolbar([['href' => pharma_erp_url('hr/'), 'label' => 'Retour RH', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Nouvelle demande</div>
            <div class="pharma-pro-panel-body">
                <form method="post" class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Employé</label>
                        <select name="employee_id" class="form-select" required>
                            <?php foreach ($employees as $e): ?>
                            <option value="<?= (int) $e['id'] ?>"><?= htmlspecialchars($e['first_name'] . ' ' . $e['last_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Type</label>
                        <select name="leave_type" class="form-select">
                            <option value="annual">Congé annuel</option>
                            <option value="sick">Maladie</option>
                            <option value="maternity">Maternité</option>
                            <option value="unpaid">Sans solde</option>
                            <option value="other">Autre</option>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label">Début</label><input type="date" name="start_date" class="form-control" required></div>
                    <div class="col-6"><label class="form-label">Fin</label><input type="date" name="end_date" class="form-control" required></div>
                    <div class="col-12"><label class="form-label">Motif</label><textarea name="reason" class="form-control" rows="2"></textarea></div>
                    <div class="col-12"><button type="submit" class="btn btn-pharma-primary">Soumettre</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Demandes</div>
            <div class="table-responsive">
                <table class="pharma-pro-table">
                    <thead><tr><th>Employé</th><th>Type</th><th>Période</th><th>Jours</th><th>Statut</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($leaves as $l): if ($filter && $l['status'] !== $filter) continue; ?>
                        <tr>
                            <td><?= htmlspecialchars($l['employee_name']) ?></td>
                            <td><?= htmlspecialchars($l['leave_type']) ?></td>
                            <td><?= date('d/m', strtotime($l['start_date'])) ?> — <?= date('d/m/Y', strtotime($l['end_date'])) ?></td>
                            <td><?= (int) $l['days_count'] ?></td>
                            <td><span class="pe-badge pe-badge--<?= $l['status'] === 'pending' ? 'warning' : 'active' ?>"><?= htmlspecialchars($l['status']) ?></span></td>
                            <td class="text-end">
                                <?php if ($l['status'] === 'pending'): ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="review_id" value="<?= (int) $l['id'] ?>">
                                    <input type="hidden" name="review_status" value="approved">
                                    <button class="btn btn-sm btn-success" title="Approuver"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="review_id" value="<?= (int) $l['id'] ?>">
                                    <input type="hidden" name="review_status" value="rejected">
                                    <button class="btn btn-sm btn-outline-danger" title="Refuser"><i class="fas fa-times"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
