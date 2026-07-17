<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';
extract(pharma_erp_context());
require_once __DIR__ . '/../../models/pharma_erp/PePharmacy.php';

$model = new PePharmacy();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Nom obligatoire.');
        }
        $model->create([
            'name' => $name,
            'code' => trim($_POST['code'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'city' => trim($_POST['city'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'is_default' => isset($_POST['is_default']),
        ]);
        redirectWithMessage(pharma_erp_url('settings/officines.php'), 'Officine créée.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pharmacies = $model->getAll();
pharma_erp_page_start(['active' => 'settings', 'title' => 'Officines', 'subtitle' => count($pharmacies) . ' site(s)', 'icon' => 'fa-store']);
pharma_erp_toolbar([
    ['href' => pharma_erp_url('settings/'), 'label' => 'Paramètres', 'icon' => 'fa-sliders', 'class' => 'btn-pharma-outline'],
]);
?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="pharma-pro-panel"><div class="pharma-pro-panel-header">Nouvelle officine</div><div class="pharma-pro-panel-body">
            <form method="post" class="row g-3">
                <div class="col-12"><label class="form-label">Nom *</label><input type="text" name="name" class="form-control" required></div>
                <div class="col-12"><label class="form-label">Code</label><input type="text" name="code" class="form-control"></div>
                <div class="col-12"><label class="form-label">Ville</label><input type="text" name="city" class="form-control"></div>
                <div class="col-12"><label class="form-label">Téléphone</label><input type="text" name="phone" class="form-control"></div>
                <div class="col-12"><div class="form-check"><input type="checkbox" name="is_default" class="form-check-input" id="is_def"><label class="form-check-label" for="is_def">Officine par défaut</label></div></div>
                <div class="col-12"><button type="submit" class="btn btn-pharma-primary w-100"><i class="fas fa-plus me-1"></i> Créer</button></div>
            </form>
        </div></div>
    </div>
    <div class="col-lg-7">
        <div class="pharma-pro-panel"><div class="pharma-pro-panel-header">Sites enregistrés</div><div class="pharma-pro-panel-body">
            <?php foreach ($pharmacies as $ph): ?>
            <div class="d-flex justify-content-between border-bottom py-2">
                <div><strong><?= htmlspecialchars($ph['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($ph['code']) ?> · <?= htmlspecialchars($ph['city'] ?? '—') ?></small></div>
                <?php if ($ph['is_default']): ?><span class="pe-badge pe-badge--active">Par défaut</span><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div></div>
    </div>
</div>
<?php pharma_erp_page_end(); ?>
