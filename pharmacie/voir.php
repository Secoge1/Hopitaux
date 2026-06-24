<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('pharmacie'));

require_once __DIR__ . '/../models/Medicament.php';

$medicamentModel = new Medicament();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

$mouvements = [];

try {
    $medicament = $medicamentModel->getById($id);
    if (!$medicament) {
        header("Location: index.php");
        exit;
    }
    $mouvements = $medicamentModel->getMouvementsStock($id);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

?>
<?php
app_module_page_start([
    'active'   => 'pharmacie',
    'title'    => $medicament['nom_commercial'],
    'subtitle' => 'Fiche médicament',
    'icon'     => 'fa-pills',
]);
app_module_back_toolbar(app_url('pharmacie/index.php'), 'Retour à la liste', [
    ['href' => app_url('pharmacie/modifier.php?id=' . $id), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary'],
    ['href' => app_url('pharmacie/mouvement.php?id=' . $id), 'label' => 'Mouvement stock', 'icon' => 'fa-exchange-alt', 'class' => 'btn-outline-info'],
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
                <h5 class="mb-0"><i class="fas fa-pills me-2"></i>Informations du Médicament</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Nom commercial:</strong><br>
                        <?php echo htmlspecialchars($medicament['nom_commercial']); ?>
                    </div>
                    <?php if (!empty($medicament['nom_generique'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Nom générique:</strong><br>
                        <?php echo htmlspecialchars($medicament['nom_generique']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($medicament['categorie'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Catégorie:</strong><br>
                        <?php echo htmlspecialchars($medicament['categorie']); ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <strong>Forme:</strong><br>
                        <?php echo ucfirst($medicament['forme'] ?? ''); ?>
                    </div>
                    <?php if (!empty($medicament['dosage'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Dosage:</strong><br>
                        <?php echo htmlspecialchars($medicament['dosage']); ?>
                        <?php if (!empty($medicament['unite'])): ?>
                            <?php echo htmlspecialchars($medicament['unite']); ?>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6 mb-3">
                        <strong>Prix unitaire:</strong><br>
                        <h4 class="text-primary"><?php echo number_format((float)$medicament['prix_unitaire'], 0, ',', ' '); ?> FCFA</h4>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Statut:</strong><br>
                        <span class="badge bg-<?php echo ($medicament['statut'] ?? '') === 'disponible' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($medicament['statut'] ?? ''); ?>
                        </span>
                    </div>
                    <?php if (!empty($medicament['fournisseur'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Fournisseur:</strong><br>
                        <?php echo htmlspecialchars($medicament['fournisseur']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($medicament['date_peremption'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Date de péremption:</strong><br>
                        <?php echo date('d/m/Y', strtotime($medicament['date_peremption'])); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($medicament['lot'])): ?>
                    <div class="col-md-6 mb-3">
                        <strong>Lot:</strong><br>
                        <?php echo htmlspecialchars($medicament['lot']); ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($medicament['notes'])): ?>
                    <div class="col-12 mb-3">
                        <strong>Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($medicament['notes'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Stock</h5>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong>Actuel:</strong> <?php echo (int)$medicament['stock_actuel']; ?> unités</p>
                <p class="mb-1"><strong>Minimum:</strong> <?php echo (int)$medicament['stock_minimum']; ?></p>
                <p class="mb-0"><strong>Maximum:</strong> <?php echo (int)$medicament['stock_maximum']; ?></p>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>Derniers Mouvements</h5>
            </div>
            <div class="card-body">
                <?php if (empty($mouvements)): ?>
                    <p class="text-muted mb-0">Aucun mouvement enregistré</p>
                <?php else: ?>
                    <?php foreach (array_slice($mouvements, 0, 5) as $mouvement): ?>
                    <div class="mb-2 pb-2 border-bottom">
                        <strong><?php echo ucfirst($mouvement['type_mouvement']); ?></strong><br>
                        <small>
                            <?php echo $mouvement['quantite'] > 0 ? '+' : ''; ?><?php echo $mouvement['quantite']; ?> unités
                            le <?php echo date('d/m/Y H:i', strtotime($mouvement['date_mouvement'])); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php app_module_page_end(); ?>
