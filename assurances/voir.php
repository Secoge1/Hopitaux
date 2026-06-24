<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('assurances'));

require_once __DIR__ . '/../models/Assurance.php';

$assuranceModel = new Assurance();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $assurance = $assuranceModel->getById($id);
    if (!$assurance) {
        header("Location: index.php");
        exit;
    }
    $contrats = $assuranceModel->getContrats($id);
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

?>
<?php
app_module_page_start([
    'active'   => 'assurances',
    'title'    => $assurance['nom'],
    'subtitle' => 'Fiche assurance',
    'icon'     => 'fa-shield-alt',
]);
app_module_back_toolbar(app_url('assurances/index.php'), 'Retour à la liste', [
    ['href' => app_url('assurances/modifier.php?id=' . $id), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary'],
    ['href' => app_url('assurances/contrats.php?assurance_id=' . $id), 'label' => 'Contrats', 'icon' => 'fa-file-contract', 'class' => 'btn-outline-info']
]);
app_module_flash();
?>
<div class="row">
            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations de l'Assurance</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <strong>Nom:</strong><br>
                                <?php echo htmlspecialchars($assurance['nom']); ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Type:</strong><br>
                                <span class="badge bg-primary"><?php echo ucfirst($assurance['type']); ?></span>
                            </div>
                            <?php if ($assurance['numero_agrement']): ?>
                            <div class="col-md-6 mb-3">
                                <strong>Numéro d'Agrément:</strong><br>
                                <?php echo htmlspecialchars($assurance['numero_agrement']); ?>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-6 mb-3">
                                <strong>Taux de Remboursement:</strong><br>
                                <h4 class="text-primary"><?php echo number_format($assurance['taux_remboursement'], 2); ?>%</h4>
                            </div>
                            <div class="col-md-6 mb-3">
                                <strong>Statut:</strong><br>
                                <span class="badge bg-<?php echo $assurance['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($assurance['statut']); ?>
                                </span>
                            </div>
                            <?php if ($assurance['telephone']): ?>
                            <div class="col-md-6 mb-3">
                                <strong>Téléphone:</strong><br>
                                <?php echo htmlspecialchars($assurance['telephone']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($assurance['email']): ?>
                            <div class="col-md-6 mb-3">
                                <strong>Email:</strong><br>
                                <?php echo htmlspecialchars($assurance['email']); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($assurance['adresse']): ?>
                            <div class="col-12 mb-3">
                                <strong>Adresse:</strong><br>
                                <?php echo nl2br(htmlspecialchars($assurance['adresse'])); ?>
                            </div>
                            <?php endif; ?>
                            <?php if ($assurance['notes']): ?>
                            <div class="col-12 mb-3">
                                <strong>Notes:</strong><br>
                                <?php echo nl2br(htmlspecialchars($assurance['notes'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-contract me-2"></i>Contrats</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($contrats)): ?>
                            <p class="text-muted">Aucun contrat enregistré</p>
                        <?php else: ?>
                            <p><strong><?php echo count($contrats); ?> contrat(s)</strong> actif(s)</p>
                            <?php foreach (array_slice($contrats, 0, 5) as $contrat): ?>
                            <div class="mb-2 pb-2 border-bottom">
                                <?php if (!empty($contrat['patient_nom']) || !empty($contrat['patient_prenom'])): ?>
                                    <strong><?php echo htmlspecialchars(trim(($contrat['patient_prenom'] ?? '') . ' ' . ($contrat['patient_nom'] ?? ''))); ?></strong><br>
                                <?php else: ?>
                                    <strong>Patient #<?php echo $contrat['patient_id']; ?></strong><br>
                                <?php endif; ?>
                                <?php if (!empty($contrat['numero_contrat'])): ?>
                                    <small class="text-muted">Contrat: <?php echo htmlspecialchars($contrat['numero_contrat']); ?></small><br>
                                <?php endif; ?>
                                <small>
                                    Du <?php echo date('d/m/Y', strtotime($contrat['date_debut'])); ?> 
                                    au <?php echo $contrat['date_fin'] ? date('d/m/Y', strtotime($contrat['date_fin'])) : 'Indéfini'; ?>
                                </small><br>
                                <span class="badge bg-<?php echo $contrat['statut'] === 'actif' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($contrat['statut']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script src="../assets/js/auto-responsive.js"></script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
