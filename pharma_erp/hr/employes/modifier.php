<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeHr.php';

$model = new PeEmployee();
$id = (int) ($_GET['id'] ?? 0);
$employee = $id > 0 ? $model->findById($id) : null;
$error = '';

if (!$employee) {
    redirectWithMessage(pharma_erp_url('hr/employes.php'), 'Employé introuvable.', 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $ok = $model->update($id, [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'job_title' => trim($_POST['job_title'] ?? ''),
            'department' => trim($_POST['department'] ?? 'Pharmacie'),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'salary_base' => (float) ($_POST['salary_base'] ?? 0),
            'status' => $_POST['status'] ?? 'active',
        ]);
        if (!$ok) {
            throw new RuntimeException('Erreur mise à jour.');
        }
        redirectWithMessage(pharma_erp_url('hr/employes.php'), 'Employé mis à jour.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start(['active' => 'hr', 'title' => 'Modifier employé', 'icon' => 'fa-user-edit']);
pharma_erp_toolbar([['href' => pharma_erp_url('hr/employes.php'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post" class="row g-3">
            <div class="col-md-4"><label class="form-label">Code</label><input class="form-control" value="<?= htmlspecialchars($employee['employee_code']) ?>" disabled></div>
            <div class="col-md-4"><label class="form-label">Prénom *</label><input name="first_name" class="form-control" required value="<?= htmlspecialchars($employee['first_name']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Nom *</label><input name="last_name" class="form-control" required value="<?= htmlspecialchars($employee['last_name']) ?>"></div>
            <div class="col-md-4"><label class="form-label">Poste</label><input name="job_title" class="form-control" value="<?= htmlspecialchars($employee['job_title'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Département</label><input name="department" class="form-control" value="<?= htmlspecialchars($employee['department'] ?? 'Pharmacie') ?>"></div>
            <div class="col-md-4"><label class="form-label">Salaire base</label><input name="salary_base" type="number" class="form-control" min="0" value="<?= (float) $employee['salary_base'] ?>"></div>
            <div class="col-md-4"><label class="form-label">Téléphone</label><input name="phone" class="form-control" value="<?= htmlspecialchars($employee['phone'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?= htmlspecialchars($employee['email'] ?? '') ?>"></div>
            <div class="col-md-4"><label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="active"<?= ($employee['status'] ?? '') === 'active' ? ' selected' : '' ?>>Actif</option>
                    <option value="on_leave"<?= ($employee['status'] ?? '') === 'on_leave' ? ' selected' : '' ?>>En congé</option>
                    <option value="terminated"<?= ($employee['status'] ?? '') === 'terminated' ? ' selected' : '' ?>>Terminé</option>
                </select>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button></div>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
