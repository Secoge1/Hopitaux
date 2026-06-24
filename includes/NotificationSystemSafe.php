<?php
/**
 * Système de Notifications - Version Sécurisée
 * Gestion des notifications en temps réel
 * Version qui n'utilise jamais de clés étrangères pour éviter les erreurs
 */

class NotificationSystemSafe {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $this->pdo = $this->getConnection();
        $this->createNotificationsTable();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir la connexion à la base de données
     */
    private function getConnection() {
        try {
            // Utiliser la configuration de la base de données principale
            require_once __DIR__ . '/../config/db.php';
            return getDB();
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Créer la table des notifications (sans clé étrangère)
     */
    private function createNotificationsTable() {
        if (!$this->pdo) return;
        
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error', 'urgent') NOT NULL,
            titre VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            module VARCHAR(100),
            lien VARCHAR(255),
            lu BOOLEAN DEFAULT FALSE,
            date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            date_lecture TIMESTAMP NULL,
            INDEX idx_user_lu (user_id, lu),
            INDEX idx_type (type),
            INDEX idx_date (date_creation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Créer une nouvelle notification
     */
    public function createNotification($userId, $type, $titre, $message, $module = null, $lien = null) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "INSERT INTO notifications (user_id, type, titre, message, module, lien) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$userId, $type, $titre, $message, $module, $lien]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Créer une notification pour tous les utilisateurs d'un rôle spécifique
     */
    public function createNotificationForRole($role, $type, $titre, $message, $module = null, $lien = null) {
        if (!$this->pdo) return false;
        
        try {
            // Récupérer tous les utilisateurs du rôle
            $sql = "SELECT id FROM utilisateurs WHERE role = ? AND statut = 'actif'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$role]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $success = true;
            foreach ($users as $user) {
                if (!$this->createNotification($user['id'], $type, $titre, $message, $module, $lien)) {
                    $success = false;
                }
            }
            
            return $success;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Marquer une notification comme lue
     */
    public function markAsRead($notificationId, $userId) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "UPDATE notifications SET lu = TRUE, date_lecture = CURRENT_TIMESTAMP WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Marquer toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead($userId) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "UPDATE notifications SET lu = TRUE, date_lecture = CURRENT_TIMESTAMP WHERE user_id = ? AND lu = FALSE";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtenir les notifications d'un utilisateur
     */
    public function getUserNotifications($userId, $limit = 20, $unreadOnly = false) {
        if (!$this->pdo) return [];
        
        try {
            $where = "user_id = ?";
            $params = [$userId];
            
            if ($unreadOnly) {
                $where .= " AND lu = FALSE";
            }
            
            $sql = "SELECT * FROM notifications WHERE $where ORDER BY date_creation DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [$limit]));
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtenir le nombre de notifications non lues d'un utilisateur
     */
    public function getUnreadNotificationCount($userId) {
        if (!$this->pdo) return 0;
        
        try {
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND lu = FALSE";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['count'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Supprimer une notification
     */
    public function deleteNotification($notificationId, $userId) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "DELETE FROM notifications WHERE id = ? AND user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$notificationId, $userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Supprimer toutes les notifications d'un utilisateur
     */
    public function deleteAllUserNotifications($userId) {
        if (!$this->pdo) return false;
        
        try {
            $sql = "DELETE FROM notifications WHERE user_id = ?";
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute([$userId]);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Créer une notification système
     */
    public function createSystemNotification($type, $titre, $message, $module = null, $lien = null) {
        if (!$this->pdo) return false;
        
        try {
            // Créer la notification pour tous les utilisateurs actifs
            $sql = "SELECT id FROM utilisateurs WHERE statut = 'actif'";
            $stmt = $this->pdo->query($sql);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $success = true;
            foreach ($users as $user) {
                if (!$this->createNotification($user['id'], $type, $titre, $message, $module, $lien)) {
                    $success = false;
                }
            }
            
            return $success;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Nettoyer les anciennes notifications
     */
    public function cleanupOldNotifications() {
        if (!$this->pdo) return false;
        
        try {
            // Supprimer les notifications lues de plus de 30 jours
            $sql = "DELETE FROM notifications WHERE lu = TRUE AND date_creation < DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            // Supprimer les notifications non lues de plus de 90 jours
            $sql = "DELETE FROM notifications WHERE lu = FALSE AND date_creation < DATE_SUB(NOW(), INTERVAL 90 DAY)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtenir les statistiques des notifications
     */
    public function getNotificationStats() {
        if (!$this->pdo) return [];
        
        try {
            $stats = [];
            
            // Total des notifications
            $sql = "SELECT COUNT(*) as count FROM notifications";
            $stmt = $this->pdo->query($sql);
            $stats['total_notifications'] = $stmt->fetch()['count'];
            
            // Notifications non lues
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE lu = FALSE";
            $stmt = $this->pdo->query($sql);
            $stats['unread_notifications'] = $stmt->fetch()['count'];
            
            // Notifications par type
            $sql = "SELECT type, COUNT(*) as count FROM notifications GROUP BY type";
            $stmt = $this->pdo->query($sql);
            $stats['notifications_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Notifications aujourd'hui
            $sql = "SELECT COUNT(*) as count FROM notifications WHERE DATE(date_creation) = CURDATE()";
            $stmt = $this->pdo->query($sql);
            $stats['notifications_today'] = $stmt->fetch()['count'];
            
            return $stats;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Créer une notification de test
     */
    public function createTestNotification($userId = 1) {
        return $this->createNotification(
            $userId,
            'info',
            'Test de Notification',
            'Ceci est une notification de test pour vérifier le bon fonctionnement du système.',
            'test',
            null
        );
    }
}
?>




