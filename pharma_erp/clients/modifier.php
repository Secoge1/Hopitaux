<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeCustomer.php';

$id = (int) ($_GET['id'] ?? 0);
$model = new PeCustomer();
$client = $model->findById($id);
if (!$client) {
    redirectWithMessage(pharma_erp_url('clients/'), 'Client introuvable.', 'warning');
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $model->update($id, [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'birth_date' => trim($_POST['birth_date'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? ''),
            'status' => $_POST['status'] ?? 'active',
        ]);
        redirectWithMessage(pharma_erp_url('clients/'), 'Client mis à jour.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start(['active' => 'clients', 'title' => 'Modifier client', 'icon' => 'fa-user-edit']);
pharma_erp_toolbar([['href' => pharma_erp_url('clients/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="pharma-pro-panel"><div class="pharma-pro-panel-body">
<form method="post" class="row g-3">
    <div class="col-md-6"><label class="form-label">Prénom</label><input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($client['first_name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Nom</label><input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($client['last_name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Société</label><input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($client['company_name'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Téléphone</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($client['phone'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($client['email'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Statut</label><select name="status" class="form-select"><option value="active" <?= ($client['status'] ?? '') === 'active' ? 'selected' : '' ?>>Actif</option><option value="inactive" <?= ($client['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactif</option></select></div>
    <div class="col-md-6"><label class="form-label">Ville</label><input type="text" name="city" class="form-control" value="<?= htmlspecialchars($client['city'] ?? '') ?>"></div>
    <div class="col-md-6"><label class="form-label">Date de naissance</label><input type="date" name="birth_date" class="form-control" value="<?= htmlspecialchars($client['birth_date'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">Adresse</label><input type="text" name="address" class="form-control" value="<?= htmlspecialchars($client['address'] ?? '') ?>"></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea></div>
    <div class="col-12"><button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button></div>
</form>
</div></div>
<?php pharma_erp_page_end(); ?>
