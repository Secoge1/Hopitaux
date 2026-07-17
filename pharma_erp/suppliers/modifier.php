<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeSupplier.php';

$model = new PeSupplier();
$id = (int) ($_GET['id'] ?? 0);
$supplier = $id > 0 ? $model->findById($id) : null;
$error = '';

if (!$supplier) {
    redirectWithMessage(pharma_erp_url('suppliers/'), 'Fournisseur introuvable.', 'danger');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['company_name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Raison sociale obligatoire.');
        }
        $ok = $model->update($id, [
            'company_name' => $name,
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'payment_terms_days' => (int) ($_POST['payment_terms_days'] ?? 30),
            'status' => $_POST['status'] ?? 'active',
        ]);
        if (!$ok) {
            throw new RuntimeException('Erreur mise à jour.');
        }
        redirectWithMessage(pharma_erp_url('suppliers/'), 'Fournisseur mis à jour.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start([
    'active' => 'suppliers',
    'title' => 'Modifier fournisseur',
    'subtitle' => $supplier['code'],
    'icon' => 'fa-edit',
]);

pharma_erp_toolbar([
    ['href' => pharma_erp_url('suppliers/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline'],
]);

if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post" class="row g-3">
            <div class="col-md-8">
                <label class="form-label">Raison sociale *</label>
                <input type="text" name="company_name" class="form-control" required value="<?= htmlspecialchars($supplier['company_name']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Code</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($supplier['code']) ?>" disabled>
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact</label>
                <input type="text" name="contact_name" class="form-control" value="<?= htmlspecialchars($supplier['contact_name'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Téléphone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($supplier['phone'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Délai paiement (j)</label>
                <input type="number" name="payment_terms_days" class="form-control" value="<?= (int) $supplier['payment_terms_days'] ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($supplier['email'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="active"<?= ($supplier['status'] ?? '') === 'active' ? ' selected' : '' ?>>Actif</option>
                    <option value="inactive"<?= ($supplier['status'] ?? '') === 'inactive' ? ' selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Adresse</label>
                <input type="text" name="address" class="form-control" value="<?= htmlspecialchars($supplier['address'] ?? '') ?>">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
