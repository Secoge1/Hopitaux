<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeHr.php';
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';
require_once __DIR__ . '/../../models/Personnel.php';

$employeeModel = new PeEmployee();
$pharmacyModel = new PePharmacy();
$personnelModel = new Personnel();

$pharmacy = $pharmacyModel->getDefault();
$error = '';
$personnelList = $personnelModel->getAll(1, 100, '', 'actif');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!$pharmacy) {
            throw new RuntimeException('Officine non configurée.');
        }

        if (!empty($_POST['import_personnel_id'])) {
            $id = $employeeModel->importFromPersonnel((int) $_POST['import_personnel_id'], (int) $pharmacy['id']);
            if (!$id) {
                throw new RuntimeException('Import impossible (déjà lié ou personnel introuvable).');
            }
            redirectWithMessage(pharma_erp_url('hr/employes.php'), 'Employé importé depuis le HIS.', 'success');
        }

        $id = $employeeModel->create([
            'pharmacy_id' => (int) $pharmacy['id'],
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'job_title' => trim($_POST['job_title'] ?? ''),
            'department' => trim($_POST['department'] ?? 'Pharmacie'),
            'salary_base' => (float) ($_POST['salary_base'] ?? 0),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'hire_date' => $_POST['hire_date'] ?? date('Y-m-d'),
        ]);
        if (!$id) {
            throw new RuntimeException('Erreur création.');
        }
        redirectWithMessage(pharma_erp_url('hr/employes.php'), 'Employé ajouté.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start(['active' => 'hr', 'title' => 'Ajouter un employé', 'icon' => 'fa-plus']);
pharma_erp_toolbar([['href' => pharma_erp_url('hr/employes.php'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Importer depuis le HIS</div>
            <div class="pharma-pro-panel-body">
                <form method="post">
                    <select name="import_personnel_id" class="form-select mb-3" required>
                        <option value="">— Personnel hospitalier —</option>
                        <?php foreach ($personnelList as $p): ?>
                        <option value="<?= (int) $p['id'] ?>"><?= htmlspecialchars(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '') . ' — ' . ($p['poste'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-pharma-secondary"><i class="fas fa-download me-1"></i> Importer</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="pharma-pro-panel">
            <div class="pharma-pro-panel-header">Création manuelle</div>
            <div class="pharma-pro-panel-body">
                <form method="post" class="row g-3">
                    <div class="col-md-6"><label class="form-label">Prénom *</label><input name="first_name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Nom *</label><input name="last_name" class="form-control" required></div>
                    <div class="col-md-6"><label class="form-label">Poste</label><input name="job_title" class="form-control" placeholder="Pharmacien"></div>
                    <div class="col-md-6"><label class="form-label">Salaire base</label><input name="salary_base" type="number" class="form-control" min="0"></div>
                    <div class="col-md-6"><label class="form-label">Téléphone</label><input name="phone" class="form-control"></div>
                    <div class="col-md-6"><label class="form-label">Date embauche</label><input name="hire_date" type="date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
                    <div class="col-12"><button type="submit" class="btn btn-pharma-primary">Enregistrer</button></div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
