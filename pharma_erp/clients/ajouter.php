<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PeCustomer.php';

$model = new PeCustomer();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $model->create([
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name' => trim($_POST['last_name'] ?? ''),
            'company_name' => trim($_POST['company_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'birth_date' => trim($_POST['birth_date'] ?? '') ?: null,
            'notes' => trim($_POST['notes'] ?? ''),
        ]);
        redirectWithMessage(pharma_erp_url('clients/'), 'Client créé.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start(['active' => 'clients', 'title' => 'Nouveau client', 'icon' => 'fa-user-plus']);
pharma_erp_toolbar([['href' => pharma_erp_url('clients/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="pharma-pro-panel"><div class="pharma-pro-panel-body">
<form method="post" class="row g-3">
    <div class="col-md-6"><label class="form-label">Prénom</label><input type="text" name="first_name" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Nom</label><input type="text" name="last_name" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Société</label><input type="text" name="company_name" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Téléphone (fidélité)</label><input type="text" name="phone" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Ville</label><input type="text" name="city" class="form-control"></div>
    <div class="col-md-6"><label class="form-label">Date de naissance</label><input type="date" name="birth_date" class="form-control"></div>
    <div class="col-12"><label class="form-label">Adresse</label><input type="text" name="address" class="form-control"></div>
    <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
    <div class="col-12"><button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button></div>
</form>
</div></div>
<?php pharma_erp_page_end(); ?>
