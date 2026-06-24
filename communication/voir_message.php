<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('communication'));

require_once __DIR__ . '/../models/Communication.php';

$commModel = new Communication();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $auth->getUtilisateur()['id'];

if (!$id) {
    header("Location: index.php");
    exit;
}

try {
    $message = $commModel->getMessageById($id, $user_id);
    if (!$message) {
        header("Location: index.php");
        exit;
    }
    
    // Marquer comme lu si c'est un message reçu (pas envoyé par l'utilisateur)
    // et qu'il n'est pas déjà lu
    if ($message['expediteur_id'] != $user_id && !$message['lu']) {
        $commModel->markAsRead($id, $user_id);
        // Recharger le message pour avoir la valeur 'lu' à jour
        $message = $commModel->getMessageById($id, $user_id);
    }
} catch (Exception $e) {
    die("Erreur: " . $e->getMessage());
}

?>
<?php
app_module_page_start([
    'active'   => 'communication',
    'title'    => 'Message',
    'subtitle' => isset($message_data['sujet']) ? $message_data['sujet'] : 'Détail du message',
    'icon'     => 'fa-comments',
]);
app_module_back_toolbar(app_url('communication/index.php'), 'Retour à la liste');
app_module_flash();
?>
<div class="card">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($message['sujet']); ?></h5>
                    <a href="index.php" class="btn btn-light btn-sm">
                        <i class="fas fa-arrow-left me-2"></i>Retour
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>De:</strong> <?php echo htmlspecialchars($message['expediteur_nom'] ?? 'Système'); ?><br>
                    <strong>À:</strong> <?php echo htmlspecialchars($message['destinataire_nom'] ?? ($message['destinataire_role'] ?? 'Tous')); ?><br>
                    <strong>Date:</strong> <?php echo date('d/m/Y H:i', strtotime($message['date_creation'])); ?><br>
                    <strong>Priorité:</strong> 
                    <span class="badge bg-<?php 
                        echo $message['priorite'] === 'urgente' ? 'danger' : 
                            ($message['priorite'] === 'haute' ? 'warning' : 'info'); 
                    ?>">
                        <?php echo ucfirst($message['priorite']); ?>
                    </span>
                </div>
                
                <hr>
                
                <div class="message-content mb-4">
                    <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                </div>
                
                <?php if ($message['expediteur_id'] != $user_id && $message['expediteur_id']): ?>
                <div class="border-top pt-3">
                    <a href="nouveau_message.php?repondre=<?php echo $message['id']; ?>" class="btn btn-primary">
                        <i class="fas fa-reply me-2"></i>Répondre
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php app_module_page_end(); ?>
