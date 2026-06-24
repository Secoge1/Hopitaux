<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('maintenance'));

require_once __DIR__ . '/../models/Maintenance.php';

$maintenanceModel = new Maintenance();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$equipement_id = isset($_GET['equipement_id']) ? (int) $_GET['equipement_id'] : 0;

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
$equipement = $maintenanceModel->getEquipementById($equipement_id);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'type_intervention' => $_POST['type_intervention'],
            'date_intervention' => $_POST['date_intervention'],
            'technicien' => $_POST['technicien'] ?: null,
            'cout' => $_POST['cout'] ?: 0.00,
            'description' => $_POST['description'],
            'resultat' => $_POST['resultat'] ?: null,
            'statut' => $_POST['statut'] ?? 'planifiee',
            'prochaine_intervention' => $_POST['prochaine_intervention'] ?: null,
        ];

        if ($maintenanceModel->updateIntervention($id, $data)) {
            header("Location: intervention.php?equipement_id=$equipement_id&success=1");
            exit;
        }
        $error = 'Erreur lors de la modification.';
    } catch (Exception $e) {
        $error = 'Erreur : ' . $e->getMessage();
    }
}

app_module_page_start([
    'active'   => 'maintenance',
    'title'    => 'Modifier intervention',
    'subtitle' => ($equipement['nom'] ?? '') . ' — ' . ($equipement['numero_serie'] ?? ''),
    'icon'     => 'fa-wrench',
]);
app_module_back_toolbar(app_url("maintenance/intervention.php?equipement_id=$equipement_id"), 'Retour aux interventions');
app_module_flash();
?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Modifier l'intervention #<?= (int) $id ?></h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label for="type_intervention" class="form-label">Type d'intervention *</label>
                <select class="form-select" id="type_intervention" name="type_intervention" required>
                    <?php foreach (['preventive' => 'Préventive', 'corrective' => 'Corrective', 'reparation' => 'Réparation', 'calibrage' => 'Calibrage', 'autre' => 'Autre'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($intervention['type_intervention'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="date_intervention" class="form-label">Date *</label>
                <input type="date" class="form-control" id="date_intervention" name="date_intervention"
                       value="<?= htmlspecialchars(substr($intervention['date_intervention'], 0, 10)) ?>" required>
            </div>
            <div class="col-md-6">
                <label for="technicien" class="form-label">Technicien</label>
                <input type="text" class="form-control" id="technicien" name="technicien"
                       value="<?= htmlspecialchars($intervention['technicien'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label for="cout" class="form-label">Coût (FCFA)</label>
                <input type="number" class="form-control" id="cout" name="cout" step="0.01" min="0"
                       value="<?= htmlspecialchars((string) ($intervention['cout'] ?? 0)) ?>">
            </div>
            <div class="col-md-6">
                <label for="statut" class="form-label">Statut</label>
                <select class="form-select" id="statut" name="statut">
                    <?php foreach (['planifiee' => 'Planifiée', 'en_cours' => 'En cours', 'terminee' => 'Terminée'] as $val => $label): ?>
                    <option value="<?= $val ?>" <?= ($intervention['statut'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="prochaine_intervention" class="form-label">Prochaine intervention</label>
                <input type="date" class="form-control" id="prochaine_intervention" name="prochaine_intervention"
                       value="<?= !empty($intervention['prochaine_intervention']) ? htmlspecialchars(substr($intervention['prochaine_intervention'], 0, 10)) : '' ?>">
            </div>
            <div class="col-12">
                <label for="description" class="form-label">Description *</label>
                <textarea class="form-control" id="description" name="description" rows="3" required><?= htmlspecialchars($intervention['description'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label for="resultat" class="form-label">Résultat</label>
                <textarea class="form-control" id="resultat" name="resultat" rows="2"><?= htmlspecialchars($intervention['resultat'] ?? '') ?></textarea>
            </div>
            <div class="col-12 d-flex justify-content-end gap-2">
                <a href="intervention.php?equipement_id=<?= $equipement_id ?>" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-2"></i>Enregistrer</button>
            </div>
        </form>
    </div>
</div>
<?php app_module_page_end(); ?>
