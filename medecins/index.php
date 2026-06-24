<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('medecins'));

require_once __DIR__ . '/../models/Medecin.php';
require_once __DIR__ . '/../includes/medecin_settings.php';
$medecinModel = new Medecin();

if (!$auth->estAdmin() && StaffScope::isActive()) {
    $ctx = StaffScope::context();
    if (!empty($ctx['medecin_id'])) {
        header('Location: ' . app_url('medecins/voir.php?id=' . (int) $ctx['medecin_id']));
        exit;
    }
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$specialite = isset($_GET['specialite']) ? trim($_GET['specialite']) : '';
$typeProfil = isset($_GET['type_profil']) ? trim($_GET['type_profil']) : '';
$limit = 20;

$medecins = $medecinModel->getAll($page, $limit, $search, $specialite, $typeProfil);
$total_medecins = $medecinModel->getCount($search, $specialite, $typeProfil);
$total_pages = ceil($total_medecins / $limit);

$stats = $medecinModel->getStats();
$specialites = $medecinModel->getSpecialites();

$pageTitle = (!$auth->estAdmin() && StaffScope::isActive()) ? 'Mon profil' : 'Équipe médicale';
$pageSubtitle = (!$auth->estAdmin() && StaffScope::isActive())
    ? 'Votre fiche professionnelle (rattachement requis pour accéder à vos dossiers)'
    : 'Médecins, sage-femmes, infirmiers, laborantins et autres professionnels';

app_module_page_start([
    'active'   => 'medecins',
    'title'    => $pageTitle,
    'subtitle' => $pageSubtitle,
    'icon'     => 'fa-user-md',
]);
if (medecin_create_allowed($auth)) {
    app_module_toolbar([
        ['href' => app_url('medecins/ajouter.php'), 'label' => 'Nouveau professionnel', 'icon' => 'fa-plus'],
    ]);
}
app_module_flash();
?>

<?php require __DIR__ . '/_list_view.php'; ?>

    <?php if (medecin_admin_actions_allowed($auth)): ?>
    <!-- Modal de confirmation de suppression -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true"
         data-delete-url="ajax_supprimer.php" data-delete-row-key="data-medecin-id"
         data-delete-entity-label="médecin(s)">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Confirmation de suppression
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Êtes-vous sûr de vouloir supprimer définitivement le professionnel :</p>
                    <p class="fw-bold text-danger" id="medecinNameToDelete"></p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Attention :</strong> Cette action est irréversible. La fiche et toutes les données liées (consultations, rendez-vous, analyses) seront supprimées définitivement.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="fas fa-trash me-2"></i>Oui, supprimer définitivement
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php ob_start(); ?>
    <?php if (medecin_admin_actions_allowed($auth)): ?>
    <!-- Suppression gérée par AppModActions (head) + #deleteModal ci-dessus -->
    <?php endif; ?>
<?php
$GLOBALS['app_page_scripts'] = ob_get_clean();
app_module_page_end();
