<?php
/**
 * Système de Notifications en Temps Réel
 * Gestion des notifications pour tous les modules du système
 */

require_once __DIR__ . '/saas/TenantScope.php';

class NotificationSystem {
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
     * Créer la table des notifications si elle n'existe pas
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
            FOREIGN KEY (user_id) REFERENCES utilisateurs(id) ON DELETE CASCADE,
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
            $columns = ['user_id', 'type', 'titre', 'message', 'module', 'lien'];
            $placeholders = array_fill(0, count($columns), '?');
            $values = [$userId, $type, $titre, $message, $module, $lien];
            TenantScope::bindInsert($this->pdo, 'notifications', $columns, $placeholders, $values);
            $sql = 'INSERT INTO notifications (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo->prepare($sql);
            
            return $stmt->execute($values);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Créer une notification pour tous les utilisateurs d'un rôle spécifique
     */
    public function createNotificationForRole($role, $type, $titre, $message, $module = null, $lien = null, ?int $tenantId = null) {
        if (!$this->pdo) return false;
        
        try {
            $where = ['role = ?', "statut = 'actif'"];
            $params = [$role];
            if ($tenantId !== null && $tenantId > 0) {
                $where[] = 'tenant_id = ?';
                $params[] = $tenantId;
            } else {
                TenantScope::appendWhere($this->pdo, 'utilisateurs', $where, $params);
            }
            $sql = 'SELECT id FROM utilisateurs WHERE ' . implode(' AND ', $where);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
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
            return TenantScope::updateWhere(
                $this->pdo,
                'notifications',
                'lu = TRUE, date_lecture = CURRENT_TIMESTAMP',
                ['id = ?', 'user_id = ?'],
                [$notificationId, $userId]
            );
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
            return TenantScope::updateWhere(
                $this->pdo,
                'notifications',
                'lu = TRUE, date_lecture = CURRENT_TIMESTAMP',
                ['user_id = ?', 'lu = FALSE'],
                [$userId]
            );
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
            $where = ['user_id = ?'];
            $params = [$userId];
            if ($unreadOnly) {
                $where[] = 'lu = FALSE';
            }
            TenantScope::appendWhere($this->pdo, 'notifications', $where, $params);
            $sql = 'SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . ' ORDER BY date_creation DESC LIMIT ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [$limit]));
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug: Log pour vérifier
            error_log("NotificationSystem::getUserNotifications - User: $userId, Limit: $limit, UnreadOnly: " . ($unreadOnly ? 'true' : 'false') . ", Count: " . count($result));
            
            return $result;
        } catch (Exception $e) {
            error_log("NotificationSystem::getUserNotifications - Erreur: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtenir le nombre de notifications non lues
     */
    public function getUnreadCount($userId) {
        if (!$this->pdo) return 0;
        
        try {
            return TenantScope::count($this->pdo, 'notifications', ['user_id = ?', 'lu = FALSE'], [$userId]);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Dernier identifiant de notification pour un utilisateur (point de départ du polling).
     */
    public function getMaxNotificationId($userId) {
        if (!$this->pdo) return 0;

        try {
            $where = ['user_id = ?'];
            $params = [$userId];
            TenantScope::appendWhere($this->pdo, 'notifications', $where, $params);
            $sql = 'SELECT COALESCE(MAX(id), 0) FROM notifications WHERE ' . implode(' AND ', $where);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Notifications créées après un identifiant donné.
     *
     * @return list<array<string, mixed>>
     */
    public function getNotificationsSince($userId, int $lastId, int $limit = 20) {
        if (!$this->pdo || $lastId < 0) return [];

        try {
            $where = ['user_id = ?', 'id > ?'];
            $params = [$userId, $lastId];
            TenantScope::appendWhere($this->pdo, 'notifications', $where, $params);
            $sql = 'SELECT id, type, titre, message, module, lien, lu, date_creation
                    FROM notifications WHERE ' . implode(' AND ', $where) . '
                    ORDER BY id ASC LIMIT ?';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(array_merge($params, [$limit]));
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Supprimer une notification
     */
    public function deleteNotification($notificationId, $userId) {
        if (!$this->pdo) return false;
        
        try {
            return TenantScope::deleteWhere(
                $this->pdo,
                'notifications',
                ['id = ?', 'user_id = ?'],
                [$notificationId, $userId]
            ) > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Supprimer les anciennes notifications (plus de 30 jours)
     */
    public function cleanupOldNotifications() {
        if (!$this->pdo) return false;
        
        try {
            $this->foreachActiveTenant(function (int $tenantId) {
                TenantScope::deleteWhere(
                    $this->pdo,
                    'notifications',
                    ['tenant_id = ?', 'date_creation < DATE_SUB(NOW(), INTERVAL 30 DAY)'],
                    [$tenantId]
                );
            });
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Créer des notifications automatiques pour différents événements
     */
    public function createAutomaticNotifications() {
        // Notification pour les rendez-vous du jour
        $this->createRendezVousNotifications();
        
        // Notification pour les consultations en retard
        $this->createConsultationNotifications();
        
        // Notification pour les analyses en cours
        $this->createAnalyseNotifications();
        
        // Notification pour les paiements en attente
        $this->createPaiementNotifications();
    }
    
    /**
     * Notifications pour les rendez-vous
     */
    private function foreachActiveTenant(callable $callback): void
    {
        if (!$this->pdo) {
            return;
        }
        try {
            $tenants = $this->pdo->query("SELECT id FROM tenants WHERE status = 'active'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tenants as $tenantId) {
                $callback((int) $tenantId);
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    private function createRendezVousNotifications() {
        if (!$this->pdo) return;
        
        try {
            $this->foreachActiveTenant(function (int $tenantId) {
                $sql = "SELECT rv.*, p.nom, p.prenom, m.nom as medecin_nom 
                        FROM rendez_vous rv 
                        JOIN patients p ON rv.patient_id = p.id 
                        JOIN medecins m ON rv.medecin_id = m.id 
                        WHERE rv.tenant_id = ?
                        AND rv.statut = 'confirme' 
                        AND rv.date_rdv BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)
                        AND rv.date_rdv > NOW()";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $rdvs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($rdvs as $rdv) {
                    $this->createNotificationForRole(
                        'medecin',
                        'warning',
                        'Rendez-vous dans 1 heure',
                        "Patient: {$rdv['nom']} {$rdv['prenom']} - {$rdv['medecin_nom']}",
                        'rendez-vous',
                        "rendez-vous/voir.php?id={$rdv['id']}",
                        $tenantId
                    );
                }
            });
        } catch (Exception $e) {
            // Gérer l'erreur silencieusement
        }
    }
    
    /**
     * Notifications pour les consultations
     */
    private function createConsultationNotifications() {
        if (!$this->pdo) return;
        
        try {
            $this->foreachActiveTenant(function (int $tenantId) {
                $sql = "SELECT c.*, p.nom, p.prenom 
                        FROM consultations c 
                        JOIN patients p ON c.patient_id = p.id 
                        WHERE c.tenant_id = ?
                        AND c.date_consultation < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                        AND c.statut = 'en_cours'";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($consultations as $consultation) {
                    $this->createNotificationForRole(
                        'medecin',
                        'error',
                        'Consultation en retard',
                        "Patient: {$consultation['nom']} {$consultation['prenom']}",
                        'consultations',
                        "consultations/voir.php?id={$consultation['id']}",
                        $tenantId
                    );
                }
            });
        } catch (Exception $e) {
            // Gérer l'erreur silencieusement
        }
    }
    
    /**
     * Notifications pour les analyses
     */
    private function createAnalyseNotifications() {
        if (!$this->pdo) return;
        
        try {
            $this->foreachActiveTenant(function (int $tenantId) {
                $sql = "SELECT a.*, p.nom, p.prenom 
                        FROM analyses a 
                        JOIN patients p ON a.patient_id = p.id 
                        WHERE a.tenant_id = ?
                        AND a.priorite = 'urgente' 
                        AND a.statut = 'en_cours'
                        AND a.date_creation < DATE_SUB(NOW(), INTERVAL 2 HOUR)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $analyses = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($analyses as $analyse) {
                    $this->createNotificationForRole(
                        'laboratoire',
                        'urgent',
                        'Analyse urgente en retard',
                        "Patient: {$analyse['nom']} {$analyse['prenom']} - {$analyse['type']}",
                        'laboratoire',
                        "laboratoire/voir.php?id={$analyse['id']}",
                        $tenantId
                    );
                }
            });
        } catch (Exception $e) {
            // Gérer l'erreur silencieusement
        }
    }
    
    /**
     * Notifications pour les paiements
     */
    private function createPaiementNotifications() {
        if (!$this->pdo) return;
        
        try {
            $this->foreachActiveTenant(function (int $tenantId) {
                $sql = "SELECT p.*, pt.nom, pt.prenom 
                        FROM paiements p 
                        JOIN patients pt ON p.patient_id = pt.id 
                        WHERE p.tenant_id = ?
                        AND p.statut = 'en_attente'
                        AND p.date_creation < DATE_SUB(NOW(), INTERVAL 7 DAY)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($paiements as $paiement) {
                    $this->createNotificationForRole(
                        'secretaire',
                        'warning',
                        'Paiement en attente depuis 7 jours',
                        "Patient: {$paiement['nom']} {$paiement['prenom']} - Montant: {$paiement['montant']} FCFA",
                        'paiements',
                        "paiements/voir.php?id={$paiement['id']}",
                        $tenantId
                    );
                }
            });
        } catch (Exception $e) {
            // Gérer l'erreur silencieusement
        }
    }
}
?>




