<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/pharma_erp/layout.php';

extract(pharma_erp_context());

require_once __DIR__ . '/../../models/pharma_erp/PePromotion.php';

$model = new PePromotion();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            throw new InvalidArgumentException('Nom obligatoire.');
        }
        $id = $model->create([
            'code' => trim($_POST['code'] ?? ''),
            'name' => $name,
            'discount_type' => $_POST['discount_type'] ?? 'percent',
            'discount_value' => (float) ($_POST['discount_value'] ?? 0),
            'min_amount' => (float) ($_POST['min_amount'] ?? 0),
            'starts_at' => $_POST['starts_at'] ?: null,
            'ends_at' => $_POST['ends_at'] ?: null,
            'status' => $_POST['status'] ?? 'active',
        ]);
        if (!$id) {
            throw new RuntimeException('Erreur création.');
        }
        redirectWithMessage(pharma_erp_url('promotions/'), 'Promotion créée.', 'success');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

pharma_erp_page_start(['active' => 'promotions', 'title' => 'Nouvelle promotion', 'icon' => 'fa-plus']);
pharma_erp_toolbar([['href' => pharma_erp_url('promotions/'), 'label' => 'Retour', 'icon' => 'fa-arrow-left', 'class' => 'btn-pharma-outline']]);
if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div class="pharma-pro-panel">
    <div class="pharma-pro-panel-body">
        <form method="post" class="row g-3">
            <div class="col-md-8"><label class="form-label">Nom *</label><input name="name" class="form-control" required placeholder="Soldes été"></div>
            <div class="col-md-4"><label class="form-label">Code promo</label><input name="code" class="form-control" placeholder="Auto"></div>
            <div class="col-md-4"><label class="form-label">Type remise</label>
                <select name="discount_type" class="form-select">
                    <option value="percent">Pourcentage (%)</option>
                    <option value="fixed">Montant fixe (FCFA)</option>
                </select>
            </div>
            <div class="col-md-4"><label class="form-label">Valeur</label><input name="discount_value" type="number" step="0.01" class="form-control" min="0" value="10"></div>
            <div class="col-md-4"><label class="form-label">Montant minimum</label><input name="min_amount" type="number" class="form-control" min="0" value="0"></div>
            <div class="col-md-4"><label class="form-label">Début</label><input name="starts_at" type="datetime-local" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Fin</label><input name="ends_at" type="datetime-local" class="form-control"></div>
            <div class="col-md-4"><label class="form-label">Statut</label>
                <select name="status" class="form-select"><option value="active">Actif</option><option value="inactive">Inactif</option></select>
            </div>
            <div class="col-12"><button type="submit" class="btn btn-pharma-primary"><i class="fas fa-save me-1"></i> Enregistrer</button></div>
        </form>
    </div>
</div>

<?php pharma_erp_page_end(); ?>
