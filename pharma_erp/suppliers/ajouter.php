<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PeSupplier.php';

$model = new PeSupplier();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['company_name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Raison sociale obligatoire.');
        }
        $id = $model->create([
            'code' => trim($_POST['code'] ?? ''),
            'company_name' => $name,
            'contact_name' => trim($_POST['contact_name'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'payment_terms_days' => (int) ($_POST['payment_terms_days'] ?? 30),
        ]);
        if (!$id) {
            throw new RuntimeException('Erreur création.');
        }
        redirectWithMessage(pharma_erp_url('suppliers/'), 'Fournisseur créé.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start([
    'active' => 'suppliers',
    'title' => 'Nouveau fournisseur',
    'icon' => 'fa-plus',
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
                <input type="text" name="company_name" class="form-control" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Code</label>
                <input type="text" name="code" class="form-control" placeholder="Auto">
            </div>
            <div class="col-md-6">
                <label class="form-label">Contact</label>
                <input type="text" name="contact_name" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Téléphone</label>
                <input type="text" name="phone" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Délai paiement (j)</label>
                <input type="number" name="payment_terms_days" class="form-control" value="30">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control">
            </div>
            <div class="col-md-6">
                <label class="form-label">Adresse</label>
                <input type="text" name="address" class="form-control">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
