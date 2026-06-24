<?php

require_once __DIR__ . '/../includes/init.php';

require_once __DIR__ . '/../includes/app_module_layout.php';

extract(app_module_context('pharmacie'));



require_once __DIR__ . '/../models/Medicament.php';



$medicamentModel = new Medicament();



$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';

$categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';

$limit = 20;



try {

    $medicaments = $medicamentModel->getAll($page, $limit, $search, $statut, $categorie);

    $total = $medicamentModel->getCount($search, $statut, $categorie);

    $total_pages = ceil($total / $limit);

    $stats = $medicamentModel->getStats();

    $alertes_stock = $medicamentModel->getStockAlertes();

    $alertes_peremption = $medicamentModel->getPeremptionAlertes();

} catch (Exception $e) {

    die("Erreur: " . $e->getMessage());

}



app_module_page_start([

    'active'   => 'pharmacie',

    'title'    => 'Gestion de la Pharmacie',

    'subtitle' => 'Stock et médicaments',

    'icon'     => 'fa-pills',

]);

app_module_toolbar([

    ['href' => app_url('pharmacie/ajouter.php'), 'label' => 'Nouveau Médicament', 'icon' => 'fa-plus'],

]);

app_module_flash();

?>



        <?php if (count($alertes_stock) > 0 || count($alertes_peremption) > 0): ?>

        <div class="alert alert-warning mb-4">

            <h5><i class="fas fa-exclamation-triangle me-2"></i>Alertes</h5>

            <?php if (count($alertes_stock) > 0): ?>

                <p class="mb-1">

                    <strong><?php echo count($alertes_stock); ?> médicament(s)</strong> avec stock faible

                </p>

            <?php endif; ?>

            <?php if (count($alertes_peremption) > 0): ?>

                <p class="mb-0">

                    <strong><?php echo count($alertes_peremption); ?> médicament(s)</strong> proche(s) de la date de péremption

                </p>

            <?php endif; ?>

        </div>

        <?php endif; ?>



<?php require __DIR__ . '/_list_view.php'; ?>



<?php app_module_page_end(); ?>

