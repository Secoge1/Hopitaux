<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('maintenance'));

require_once __DIR__ . '/../models/Maintenance.php';

$maintenanceModel = new Maintenance();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $equipement = $maintenanceModel->getEquipementById($id);
    if (!$equipement) {
        header("Location: index.php");
        exit;
    }
    $interventions = $maintenanceModel->getInterventions($id);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

?>
<?php
app_module_page_start([
    'active'   => 'maintenance',
    'title'    => $equipement['nom'],
    'subtitle' => 'Fiche équipement',
    'icon'     => 'fa-tools',
]);
app_module_back_toolbar(app_url('maintenance/index.php'), 'Retour à la liste', [
    ['href' => app_url('maintenance/modifier_equipement.php?id=' . $id), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary'],
    ['href' => app_url('maintenance/intervention.php?equipement_id=' . $id), 'label' => 'Interventions', 'icon' => 'fa-wrench', 'class' => 'btn-outline-info'],
]);
app_module_flash();
?>
<?php if (isset($_GET['success'])): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i>Modifications enregistrées avec succès.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Informations de l'Équipement</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Nom:</strong><br>
                        <?php echo htmlspecialchars($equipement['nom']); ?>
                    </div>
                    <?php if (!empty($equipement['numero_serie'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>N° série:</strong><br>
                        <?php echo htmlspecialchars($equipement['numero_serie']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['categorie'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Catégorie:</strong><br>
                        <?php echo htmlspecialchars($equipement['categorie']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['marque'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Marque:</strong><br>
                        <?php echo htmlspecialchars($equipement['marque']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['modele'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Modèle:</strong><br>
                        <?php echo htmlspecialchars($equipement['modele']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['localisation'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Localisation:</strong><br>
                        <?php echo htmlspecialchars($equipement['localisation']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <strong>Statut:</strong><br>
                        <span class="badge bg-<?php echo ($equipement['statut'] ?? '') === 'disponible' ? 'success' : 'secondary'; ?>">
                            <?php echo ucfirst($equipement['statut'] ?? ''); ?>
                        </span>
                    </div>
                    <?php if (!empty($equipement['date_acquisition'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Date d'acquisition:</strong><br>
                        <?php echo date('d/m/Y', strtotime($equipement['date_acquisition'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['valeur'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Valeur:</strong><br>
                        <?php echo number_format((float)$equipement['valeur'], 0, ',', ' '); ?> FCFA
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($equipement['notes'])): ?>
                    <div class="col-12 mb-3">
                        <strong>Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($equipement['notes'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Interventions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($interventions)): ?>
                    <p class="text-muted">Aucune intervention enregistrée</p>
                <?php else: ?>
                    <?php foreach (array_slice($interventions, 0, 5) as $intervention): ?>
                    <div class="mb-2 pb-2 border-bottom">
                        <strong><?php echo ucfirst($intervention['type_intervention']); ?></strong><br>
                        <small>
                            <?php echo date('d/m/Y', strtotime($intervention['date_intervention'])); ?><br>
                            <?php echo htmlspecialchars($intervention['description'] ?? ''); ?>
                        </small><br>
                        <span class="badge bg-<?php
                            echo $intervention['statut'] === 'termine' ? 'success' :
                                ($intervention['statut'] === 'en_cours' ? 'warning' : 'secondary');
                        ?>">
                            <?php echo ucfirst($intervention['statut']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php app_module_page_end(); ?>
