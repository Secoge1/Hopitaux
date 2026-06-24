<?php
/**
 * Gestion des actions AJAX pour les notifications
 * Marquer comme lu, marquer tout comme lu, vérifier les nouvelles
 */

// Inclure les systèmes avancés
require_once 'init.php';

// Vérifier que l'utilisateur est connecté
$auth = Auth::getInstance();
if (!$auth->estConnecte()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Récupérer l'action demandée
$action = $_REQUEST['action'] ?? '';

// Récupérer l'utilisateur connecté
$utilisateur = $auth->getUtilisateur();
$userId = $utilisateur['id'];

// Initialiser le système de notifications
$notificationSystem = NotificationSystem::getInstance();

// Traiter l'action demandée
switch ($action) {
    case 'mark_read':
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            $success = $notificationSystem->markAsRead($notificationId, $userId);
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => 'ID de notification invalide']);
        }
        break;
        
    case 'mark_all_read':
        $success = $notificationSystem->markAllAsRead($userId);
        echo json_encode(['success' => $success]);
        break;
        
    case 'check_new':
        $unreadCount = (int) $notificationSystem->getUnreadCount($userId);
        $lastId = isset($_REQUEST['last_id']) ? (int) $_REQUEST['last_id'] : 0;
        $newItems = $lastId > 0
            ? $notificationSystem->getNotificationsSince($userId, $lastId)
            : [];
        $maxId = $lastId;
        $soundItems = [];
        foreach ($newItems as $row) {
            $rowId = (int) ($row['id'] ?? 0);
            if ($rowId > $maxId) {
                $maxId = $rowId;
            }
            if (empty($row['lu'])) {
                $soundItems[] = [
                    'id' => $rowId,
                    'module' => (string) ($row['module'] ?? ''),
                    'type' => (string) ($row['type'] ?? 'info'),
                    'titre' => (string) ($row['titre'] ?? ''),
                ];
            }
        }
        $lastCount = isset($_REQUEST['last_count']) ? (int) $_REQUEST['last_count'] : null;
        $hasNewNotifications = !empty($soundItems)
            || ($lastCount !== null && $unreadCount > $lastCount);
        echo json_encode([
            'hasNewNotifications' => $hasNewNotifications,
            'count' => $unreadCount,
            'lastId' => $maxId,
            'items' => $soundItems,
        ]);
        break;

    case 'list':
        header('Content-Type: application/json; charset=utf-8');
        $items = $notificationSystem->getUserNotifications($userId, 10);
        foreach ($items as &$item) {
            if (!empty($item['date_creation'])) {
                $item['date_creation'] = date('d/m/Y H:i', strtotime((string) $item['date_creation']));
            }
        }
        unset($item);
        $unreadCount = (int) $notificationSystem->getUnreadCount($userId);
        echo json_encode([
            'success' => true,
            'count' => $unreadCount,
            'notifications' => $items,
        ]);
        break;
        
    case 'create_test':
        // Créer une notification de test
        $success = $notificationSystem->createNotification(
            $userId,
            'Test - Système de Notifications',
            'Ceci est une notification de test pour vérifier le bon fonctionnement du système.',
            'test'
        );
        echo json_encode(['success' => $success]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}
?>
