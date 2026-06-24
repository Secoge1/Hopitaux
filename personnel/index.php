<?php

require_once __DIR__ . '/../includes/init.php';

require_once __DIR__ . '/../includes/app_module_layout.php';

extract(app_module_context('personnel'));



require_once __DIR__ . '/../models/Personnel.php';



$personnelModel = new Personnel();



$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Filtre statut : par défaut « actif » seulement (la suppression passe le membre en « inactif »)

$statutFilterExplicit = array_key_exists('statut', $_GET);

$statut = $statutFilterExplicit ? trim((string) $_GET['statut']) : 'actif';

$poste = isset($_GET['poste']) ? trim($_GET['poste']) : '';

$departement = isset($_GET['departement']) ? trim($_GET['departement']) : '';

$limit = 20;



$paginationQuery = array_filter([

    'search' => $search !== '' ? $search : null,

    'statut' => $statutFilterExplicit ? $statut : null,

    'poste' => $poste !== '' ? $poste : null,

    'departement' => $departement !== '' ? $departement : null,

], static function ($v) {

    return $v !== null;

});



try {

    $personnel = $personnelModel->getAll($page, $limit, $search, $statut, $poste, $departement);

    $total = $personnelModel->getCount($search, $statut, $poste, $departement);

    $total_pages = ceil($total / $limit);

    $stats = $personnelModel->getStats();

    $totalEnBase = (int) ($stats['total'] ?? 0);

} catch (Exception $e) {

    die("Erreur: " . $e->getMessage());

}



app_module_page_start([

    'active'   => 'personnel',

    'title'    => 'Gestion du Personnel',

    'subtitle' => 'Équipe et ressources humaines',

    'icon'     => 'fa-user-tie',

]);

app_module_toolbar([

    ['href' => app_url('personnel/ajouter.php'), 'label' => 'Nouveau Membre', 'icon' => 'fa-plus'],

]);

app_module_flash();

?>



        <?php if (!empty($_GET['deleted'])): ?>

            <div class="alert alert-success alert-dismissible fade show" role="alert">

                <i class="fas fa-check-circle me-2"></i>Le membre a été retiré de la liste (statut <strong>inactif</strong>). Utilisez le filtre « Tous les statuts » pour le voir à nouveau.

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>

            </div>

        <?php endif; ?>



<?php require __DIR__ . '/_list_view.php'; ?>



<?php app_module_page_end(); ?>

