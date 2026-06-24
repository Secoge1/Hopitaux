<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('dossiers'));

require_once __DIR__ . '/../config/currency.php';
require_once __DIR__ . '/../models/Dossier.php';
require_once __DIR__ . '/../models/Patient.php';

$dossierModel = new Dossier();
$patientModel = new Patient();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$priorite = isset($_GET['priorite']) ? trim($_GET['priorite']) : '';
$limit = 20;

$filters = [];
if ($search) {
    $filters['search'] = $search;
}
if ($statut) {
    $filters['statut'] = $statut;
}
if ($priorite) {
    $filters['priorite'] = $priorite;
}

$dossiers = $dossierModel->getAll($page, $limit, $filters);
$stats = $dossierModel->getStats();
$totalDossiers = $dossierModel->count($filters);
$total_pages = (int) ceil($totalDossiers / $limit);

app_module_page_start([
    'active'   => 'dossiers',
    'title'    => 'Dossiers Patients',
    'subtitle' => 'Gestion des dossiers médicaux',
    'icon'     => 'fa-folder',
]);
app_module_toolbar([
    ['href' => app_url('dossiers/nouveau_dossier.php'), 'label' => 'Nouveau Dossier', 'icon' => 'fa-plus'],
    ['href' => app_url('dossiers/recherche.php'), 'label' => 'Recherche Avancée', 'icon' => 'fa-search', 'class' => 'btn-outline-secondary'],
]);
app_module_flash();
?>

<?php require __DIR__ . '/_list_view.php'; ?>

        <!-- Fonctionnalités disponibles -->
        <div class="card mt-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Fonctionnalités Disponibles</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Gestion complète des dossiers :</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Création de dossiers patients</li>
                            <li><i class="fas fa-check text-success me-2"></i>Visualisation détaillée</li>
                            <li><i class="fas fa-check text-success me-2"></i>Modification des dossiers</li>
                            <li><i class="fas fa-check text-success me-2"></i>Suppression sécurisée</li>
                            <li><i class="fas fa-check text-success me-2"></i>Gestion des antécédents</li>
                            <li><i class="fas fa-check text-success me-2"></i>Suivi des allergies</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Fonctionnalités avancées :</h6>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Recherche et filtres</li>
                            <li><i class="fas fa-check text-success me-2"></i>Gestion des priorités</li>
                            <li><i class="fas fa-check text-success me-2"></i>Statuts des dossiers</li>
                            <li><i class="fas fa-check text-success me-2"></i>Groupes sanguins</li>
                            <li><i class="fas fa-check text-success me-2"></i>Statistiques en temps réel</li>
                            <li><i class="fas fa-check text-success me-2"></i>Interface responsive</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Types de dossiers -->
        <div class="card mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-folder me-2"></i>Types de Dossiers</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded border-danger border-start border-4">
                            <i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>
                            <h6>Dossiers Prioritaires</h6>
                            <small class="text-muted">Patients en urgence ou suivi intensif</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded border-warning border-start border-4">
                            <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                            <h6>Dossiers en Suivi</h6>
                            <small class="text-muted">Consultations régulières</small>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-center p-3 border rounded border-success border-start border-4">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h6>Dossiers Stables</h6>
                            <small class="text-muted">Suivi de routine</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php app_module_page_end(); ?>
