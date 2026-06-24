<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('dossiers'));

require_once '../models/Dossier.php';
require_once '../models/Patient.php';

$dossierModel = new Dossier();
$patientModel = new Patient();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header("Location: index.php");
    exit();
}

$dossier = $dossierModel->getById($id);
if (!$dossier) {
    header("Location: index.php");
    exit();
}

$groupesSanguins = $dossierModel->getGroupesSanguins();
$priorites = $dossierModel->getPriorites();
$statuts = $dossierModel->getStatuts();
?>
<?php
app_module_page_start([
    'active'   => 'dossiers',
    'title'    => 'Dossier Patient',
    'subtitle' => isset($dossier['numero_dossier']) ? 'N° ' . $dossier['numero_dossier'] : '',
    'icon'     => 'fa-folder',
]);
app_module_back_toolbar(app_url('dossiers/index.php'), 'Retour à la liste', [
    ['href' => app_url('dossiers/modifier.php?id=' . $id), 'label' => 'Modifier', 'icon' => 'fa-edit', 'class' => 'btn-outline-primary']
]);
app_module_flash();
?>
<div class="card">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Statut Actuel</h6>
                    </div>
                    <div class="card-body">
                        <div class="text-center">
                            <span class="status-badge status-<?php echo $dossier['statut']; ?> fs-5">
                                <?php echo htmlspecialchars($statuts[$dossier['statut']] ?? ucfirst($dossier['statut'])); ?>
                            </span>
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">
                                <?php
                                $statutDescriptions = [
                                    'actif' => 'Le dossier est actuellement actif et en cours d\'utilisation',
                                    'inactif' => 'Le dossier est temporairement inactif',
                                    'archive' => 'Le dossier a été archivé'
                                ];
                                echo $statutDescriptions[$dossier['statut']] ?? 'Statut non défini';
                                ?>
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Boutons d'action -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <a href="modifier.php?id=<?php echo $dossier['id']; ?>" class="btn btn-warning btn-lg me-3">
                    <i class="fas fa-edit me-2"></i>Modifier ce dossier
                </a>
                <a href="index.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Retour à la liste
                </a>
            </div>
        </div>
    </div>
<?php
ob_start();
?>
<script src="assets/js/auto-responsive.js"></script>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
