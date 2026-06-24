<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('rdv'));

require_once __DIR__ . '/../models/RendezVous.php';
$rdvModel = new RendezVous();

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statut = isset($_GET['statut']) ? trim($_GET['statut']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$limit = 20;

$rdvs = $rdvModel->getAll($page, $limit, $search, $statut, $date);
$total_rdvs = $rdvModel->getCount($search, $statut, $date);
$total_pages = ceil($total_rdvs / $limit);

$stats = $rdvModel->getStats();
$rdvs_today = $rdvModel->getToday();
$rdvs_week = $rdvModel->getWeek();

app_module_page_start([
    'active'   => 'rdv',
    'title'    => 'Gestion des Rendez-vous',
    'subtitle' => 'Planification et suivi des rendez-vous',
    'icon'     => 'fa-calendar-check',
]);
app_module_toolbar([
    ['href' => app_url('rendez-vous/ajouter.php'), 'label' => 'Nouveau RDV', 'icon' => 'fa-plus'],
]);
app_module_flash();
?>

        <!-- Messages de succès/erreur -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php 
                switch ($_GET['success']) {
                    case 'confirmed':
                        echo 'Rendez-vous confirmé avec succès !';
                        break;
                    case 'cancelled':
                        echo 'Rendez-vous annulé avec succès !';
                        break;
                    case 'completed':
                        echo 'Rendez-vous marqué comme terminé !';
                        break;
                    default:
                        echo 'Action effectuée avec succès !';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php 
                switch ($_GET['error']) {
                    case 'confirmation_failed':
                        echo 'Erreur lors de la confirmation du rendez-vous.';
                        break;
                    case 'cancellation_failed':
                        echo 'Erreur lors de l\'annulation du rendez-vous.';
                        break;
                    case 'invalid_status':
                        echo 'Action non autorisée pour ce statut de rendez-vous.';
                        break;
                    default:
                        echo 'Une erreur est survenue.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

<?php require __DIR__ . '/_list_view.php'; ?>

<?php app_module_page_end(); ?>
