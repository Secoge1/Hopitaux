<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('maintenance'));

require_once __DIR__ . '/../models/Maintenance.php';

$maintenanceModel = new Maintenance();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$id) {
    header('Location: index.php');
    exit;
}

$intervention = $maintenanceModel->getInterventionById($id);
if (!$intervention) {
    header('Location: index.php');
    exit;
}

$equipement_id = (int) $intervention['equipement_id'];
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    if ($maintenanceModel->deleteIntervention($id)) {
        header("Location: intervention.php?equipement_id=$equipement_id&deleted=1");
        exit;
    }
    $error = 'Erreur lors de la suppression.';
}

app_module_page_start([
    'active'   => 'maintenance',
    'title'    => 'Supprimer intervention',
    'subtitle' => 'Confirmation',
    'icon'     => 'fa-trash',
]);
app_module_back_toolbar(app_url("maintenance/intervention.php?equipement_id=$equipement_id"), 'Annuler');
app_module_flash();
?>
<div class="card">
    <div class="card-header bg-danger text-white">
        <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Supprimer l'intervention</h5>
    </div>
    <div class="card-body">
        <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <div class="alert alert-warning">
            <p class="mb-2">Intervention du <strong><?= date('d/m/Y', strtotime($intervention['date_intervention'])) ?></strong>
                — <?= htmlspecialchars(ucfirst($intervention['type_intervention'])) ?></p>
            <p class="mb-0 small"><?= htmlspecialchars($intervention['description']) ?></p>
        </div>
        <form method="POST" class="d-flex justify-content-between">
            <input type="hidden" name="confirm" value="1">
            <a href="intervention.php?equipement_id=<?= $equipement_id ?>" class="btn btn-secondary">Annuler</a>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Confirmer la suppression ?');">
                <i class="fas fa-trash me-2"></i>Supprimer
            </button>
        </form>
    </div>
</div>
<?php app_module_page_end(); ?>
