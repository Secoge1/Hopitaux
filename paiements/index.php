<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('paiements'));

require_once '../config/config.php';
require_once '../config/CurrencyConfig.php';
require_once '../models/Paiement.php';

$paiementModel = new Paiement();

$search = $_GET['search'] ?? '';
$statut = $_GET['statut'] ?? '';
$type_paiement = $_GET['type_paiement'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;

$filters = [];
if ($search) $filters['search'] = $search;
if ($statut) $filters['statut'] = $statut;
if ($type_paiement) $filters['type_paiement'] = $type_paiement;

$paiements = $paiementModel->getAll($page, $limit, $filters);
$stats = $paiementModel->getStats();
$totalPaiements = $paiementModel->count($filters);
$totalPages = (int) ceil($totalPaiements / $limit);

$typesPaiement = $paiementModel->getTypesPaiement();
$statuts = $paiementModel->getStatuts();

app_module_page_start([
    'active'   => 'paiements',
    'title'    => 'Gestion des Paiements',
    'subtitle' => 'Facturation et encaissements',
    'icon'     => 'fa-credit-card',
]);
app_module_toolbar([
    ['href' => app_url('paiements/ajouter.php'), 'label' => 'Nouveau Paiement', 'icon' => 'fa-plus'],
]);
app_module_flash();
?>

<?php require __DIR__ . '/_list_view.php'; ?>

<?php app_module_page_end(); ?>
