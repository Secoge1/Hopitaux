<?php

require_once __DIR__ . '/../includes/init.php';

require_once __DIR__ . '/../includes/app_module_layout.php';

extract(app_module_context('maintenance'));



require_once __DIR__ . '/../models/Maintenance.php';



$maintenanceModel = new Maintenance();



$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';

$categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';

$limit = 20;



try {

    $equipements = $maintenanceModel->getEquipements($page, $limit, $search, $statut, $categorie);

    $total = $maintenanceModel->getCount($search, $statut, $categorie);

    $total_pages = ceil($total / $limit);

    $stats = $maintenanceModel->getStats();

    $alertes = $maintenanceModel->getMaintenanceAlertes();

} catch (Exception $e) {

    die("Erreur: " . $e->getMessage());

}



app_module_page_start([

    'active'   => 'maintenance',

    'title'    => 'Maintenance & Logistique',

    'subtitle' => 'Équipements et interventions',

    'icon'     => 'fa-tools',

]);

app_module_toolbar([

    ['href' => app_url('maintenance/ajouter_equipement.php'), 'label' => 'Nouvel Équipement', 'icon' => 'fa-plus'],

]);

app_module_flash();

?>



        <?php if (count($alertes) > 0): ?>

        <div class="alert alert-warning mb-4">

            <h5><i class="fas fa-exclamation-triangle me-2"></i>Maintenances à prévoir</h5>

            <p class="mb-0">

                <strong><?php echo count($alertes); ?> équipement(s)</strong> nécessitent une maintenance dans les 7 prochains jours

            </p>

        </div>

        <?php endif; ?>



<?php require __DIR__ . '/_list_view.php'; ?>



<?php app_module_page_end(); ?>

