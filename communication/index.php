<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('communication'));

require_once __DIR__ . '/../models/Communication.php';
require_once __DIR__ . '/../includes/NotificationSystem.php';

$commModel = new Communication();
$notificationSystem = NotificationSystem::getInstance();
$user_id = $utilisateur['id'];
$role = $utilisateur['role'];

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$type = isset($_GET['type']) ? $_GET['type'] : 'received';
$limit = 20;

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $message_id = (int)$_GET['id'];
    if ($message_id > 0) {
        try {
            if ($commModel->deleteMessage($message_id, $user_id)) {
                header("Location: index.php?type=$type&deleted=1");
                exit;
            }
            header("Location: index.php?type=$type&error=delete_failed");
            exit;
        } catch (Exception $e) {
            header("Location: index.php?type=$type&error=" . urlencode($e->getMessage()));
            exit;
        }
    }
}

$successMessage = '';
$errorMessage = '';
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $successMessage = 'Message envoyé avec succès !';
}
if (isset($_GET['deleted']) && $_GET['deleted'] == '1') {
    $successMessage = 'Message supprimé avec succès !';
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'delete_failed') {
        $errorMessage = 'Erreur lors de la suppression du message.';
    } else {
        $errorMessage = htmlspecialchars(urldecode($_GET['error']));
    }
}

try {
    $messages = $commModel->getMessages($user_id, $page, $limit, $type);
    $stats = $commModel->getStats($user_id);
    $annonces = $commModel->getAnnonces($role, 5);

    $notificationsMessages = $notificationSystem->getUserNotifications($user_id, 10, true);
    $notificationsMessages = array_filter($notificationsMessages, function ($notif) {
        return $notif['module'] === 'communication';
    });
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

$commToolbar = [
    ['href' => app_url('communication/nouveau_message.php'), 'label' => 'Nouveau Message', 'icon' => 'fa-envelope'],
];
if ($auth->aUnRole(['admin'])) {
    $commToolbar[] = ['href' => app_url('communication/nouvelle_annonce.php'), 'label' => 'Nouvelle Annonce', 'icon' => 'fa-bullhorn', 'class' => 'btn-outline-secondary'];
}
app_module_page_start([
    'active'   => 'communication',
    'title'    => 'Communication Interne',
    'subtitle' => 'Messages et annonces',
    'icon'     => 'fa-comments',
]);
app_module_toolbar($commToolbar);
app_module_flash();
?>

        <?php if ($successMessage): ?>
        <div class="alert alert-success alert-dismissible fade show mb-4 shadow-sm" role="alert" style="border-left: 4px solid #28a745; animation: slideIn 0.3s ease-out;">
            <div class="d-flex align-items-center">
                <i class="fas fa-check-circle me-3" style="font-size: 1.5rem;"></i>
                <div class="flex-grow-1">
                    <strong>Succès !</strong> <?php echo htmlspecialchars($successMessage); ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Erreur !</strong> <?php echo htmlspecialchars($errorMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($notificationsMessages)): ?>
        <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
            <h5 class="alert-heading">
                <i class="fas fa-bell me-2"></i>Vous avez <?php echo count($notificationsMessages); ?> nouveau(x) message(s)
            </h5>
            <ul class="mb-0">
                <?php foreach (array_slice($notificationsMessages, 0, 5) as $notif): ?>
                <li>
                    <a href="<?php echo htmlspecialchars($notif['lien'] ?? 'index.php?type=received'); ?>" class="text-decoration-none">
                        <strong><?php echo htmlspecialchars($notif['titre']); ?></strong>:
                        <?php echo htmlspecialchars(substr($notif['message'], 0, 80)); ?>...
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php if (count($notificationsMessages) > 5): ?>
                <p class="mb-0 mt-2">
                    <a href="index.php?type=received" class="alert-link">Voir tous les nouveaux messages</a>
                </p>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

<?php require __DIR__ . '/_list_view.php'; ?>

<?php app_module_page_end(); ?>
