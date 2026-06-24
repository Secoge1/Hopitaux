<?php

require_once __DIR__ . '/../includes/init.php';

require_once __DIR__ . '/../includes/app_module_layout.php';

extract(app_module_context('assurances'));



require_once __DIR__ . '/../models/Assurance.php';



$assuranceModel = new Assurance();



$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';

$limit = 20;



try {

    $assurances = $assuranceModel->getAll($page, $limit, $search, $statut);

    $total = $assuranceModel->getCount($search, $statut);

    $total_pages = ceil($total / $limit);

    $stats = $assuranceModel->getStats();

} catch (Exception $e) {

    die("Erreur: " . $e->getMessage());

}



app_module_page_start([

    'active'   => 'assurances',

    'title'    => 'Gestion des Assurances',

    'subtitle' => 'Contrats et couvertures',

    'icon'     => 'fa-shield-alt',

]);

app_module_toolbar([

    ['href' => app_url('assurances/ajouter.php'), 'label' => 'Nouvelle Assurance', 'icon' => 'fa-plus'],

]);

app_module_flash();

?>



<?php require __DIR__ . '/_list_view.php'; ?>



<?php app_module_page_end(); ?>

