<?php
/**
 * Classe SystemLogs
 * Gestion des journaux système
 */

require_once __DIR__ . '/../includes/saas/TenantScope.php';

class SystemLogs {
    private $pdo;
    
    public function __construct() {
        $this->pdo = $this->getConnection();
        $this->createLogsTable();
    }
    
    /**
     * Obtenir la connexion à la base de données
     */
    private function getConnection() {
        try {
            if (!function_exists('getDB')) {
                require_once __DIR__ . '/db.php';
            }
            return getDB();
        } catch (Exception $e) {
            error_log('SystemLogs connexion: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Statistiques vides par défaut
     */
    private function emptyStats(): array {
        return [
            'total_logs'  => 0,
            'today_logs'  => 0,
            'week_logs'   => 0,
            'month_logs'  => 0,
            'top_actions' => [],
            'top_users'   => [],
        ];
    }
    
    /**
     * Créer la table des journaux si elle n'existe pas
     */
    private function createLogsTable() {
        if (!$this->pdo) return;
        
        $sql = "CREATE TABLE IF NOT EXISTS system_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            user_id INT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Ajouter une entrée dans les journaux
     */
    public function addLog($action, $details = '', $userId = null, $ipAddress = null) {
        if (!$this->pdo) return false;
        
        try {
            $columns = ['action', 'details', 'user_id', 'ip_address'];
            $placeholders = array_fill(0, count($columns), '?');
            $userId = $userId ?? ($_SESSION['user_id'] ?? null);
            $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $values = [$action, $details, $userId, $ipAddress];
            TenantScope::bindInsert($this->pdo, 'system_logs', $columns, $placeholders, $values);
            $sql = 'INSERT INTO system_logs (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($values);
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtenir les journaux avec filtres
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        if (!$this->pdo) return [];
        
        try {
            $where = [];
            $params = [];
            
            // Filtre par action
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }
            
            // Filtre par utilisateur
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            // Filtre par date
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            // Filtre par recherche textuelle
            if (!empty($filters['search'])) {
                $where[] = "(action LIKE ? OR details LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            TenantScope::appendWhere($this->pdo, 'system_logs', $where, $params, 'l');
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT l.*, u.nom as user_nom, u.prenom as user_prenom 
                    FROM system_logs l 
                    LEFT JOIN utilisateurs u ON l.user_id = u.id 
                    $whereClause 
                    ORDER BY l.created_at DESC 
                    LIMIT ? OFFSET ?";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtenir le nombre total de journaux
     */
    public function getLogsCount($filters = []) {
        if (!$this->pdo) return 0;
        
        try {
            $where = [];
            $params = [];
            
            // Appliquer les mêmes filtres
            if (!empty($filters['action'])) {
                $where[] = "action = ?";
                $params[] = $filters['action'];
            }
            
            if (!empty($filters['user_id'])) {
                $where[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= ?";
                $params[] = $filters['date_from'] . ' 00:00:00';
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= ?";
                $params[] = $filters['date_to'] . ' 23:59:59';
            }
            
            if (!empty($filters['search'])) {
                $where[] = "(action LIKE ? OR details LIKE ?)";
                $searchTerm = '%' . $filters['search'] . '%';
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            TenantScope::appendWhere($this->pdo, 'system_logs', $where, $params);
            $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

            $sql = "SELECT COUNT(*) FROM system_logs $whereClause";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchColumn();

        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Obtenir les actions disponibles
     */
    public function getAvailableActions() {
        if (!$this->pdo) return [];

        try {
            $where = [];
            $params = [];
            TenantScope::appendWhere($this->pdo, 'system_logs', $where, $params);
            $whereClause = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            $sql = "SELECT DISTINCT action FROM system_logs $whereClause ORDER BY action";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtenir les statistiques des journaux
     */
    public function getLogsStats() {
        if (!$this->pdo) {
            return $this->emptyStats();
        }
        
        try {
            $stats = $this->emptyStats();
            $stats['total_logs'] = TenantScope::count($this->pdo, 'system_logs');
            $stats['today_logs'] = TenantScope::count($this->pdo, 'system_logs', ['DATE(created_at) = CURDATE()']);
            $stats['week_logs'] = TenantScope::count($this->pdo, 'system_logs', ['YEARWEEK(created_at) = YEARWEEK(NOW())']);
            $stats['month_logs'] = TenantScope::count(
                $this->pdo,
                'system_logs',
                ['MONTH(created_at) = MONTH(NOW())', 'YEAR(created_at) = YEAR(NOW())']
            );

            $where = [];
            $params = [];
            TenantScope::appendWhere($this->pdo, 'system_logs', $where, $params);
            $wc = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
            $stmt = $this->pdo->prepare("SELECT action, COUNT(*) as count FROM system_logs $wc GROUP BY action ORDER BY count DESC LIMIT 5");
            $stmt->execute($params);
            $stats['top_actions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $where2 = ['user_id IS NOT NULL'];
            $params2 = [];
            TenantScope::appendWhere($this->pdo, 'system_logs', $where2, $params2);
            $stmt = $this->pdo->prepare(
                'SELECT user_id, COUNT(*) as count FROM system_logs WHERE ' . implode(' AND ', $where2)
                . ' GROUP BY user_id ORDER BY count DESC LIMIT 5'
            );
            $stmt->execute($params2);
            $stats['top_users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $stats;
            
        } catch (Exception $e) {
            error_log('SystemLogs getLogsStats: ' . $e->getMessage());
            return $this->emptyStats();
        }
    }
    
    /**
     * Nettoyer les anciens journaux
     */
    public function cleanOldLogs($daysToKeep = 90) {
        if (!$this->pdo) return false;
        
        try {
            $where = ['created_at < DATE_SUB(NOW(), INTERVAL ? DAY)'];
            $params = [$daysToKeep];
            TenantScope::deleteWhere($this->pdo, 'system_logs', $where, $params);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Exporter les journaux en CSV
     */
    public function exportLogsCSV($filters = []) {
        if (!$this->pdo) return false;
        
        try {
            $logs = $this->getLogs($filters, 10000, 0); // Limite élevée pour l'export
            
            if (empty($logs)) {
                return false;
            }
            
            $filename = 'system_logs_' . date('Y-m-d_H-i-s') . '.csv';
            $filepath = '../backups/' . $filename;
            
            $file = fopen($filepath, 'w');
            if (!$file) {
                return false;
            }
            
            // En-têtes CSV
            fputcsv($file, ['ID', 'Action', 'Détails', 'Utilisateur', 'Adresse IP', 'Date de création']);
            
            // Données
            foreach ($logs as $log) {
                $userName = $log['user_nom'] && $log['user_prenom'] 
                    ? $log['user_nom'] . ' ' . $log['user_prenom'] 
                    : 'Utilisateur #' . $log['user_id'];
                
                fputcsv($file, [
                    $log['id'],
                    $log['action'],
                    $log['details'],
                    $userName,
                    $log['ip_address'],
                    $log['created_at']
                ]);
            }
            
            fclose($file);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'count' => count($logs)
            ];
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtenir les informations d'un utilisateur
     */
    public function getUserInfo($userId) {
        if (!$this->pdo || !$userId) return null;
        
        try {
            $sql = "SELECT id, nom, prenom, email, statut FROM utilisateurs WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Formater une action pour l'affichage
     */
    public function formatAction($action) {
        $actions = [
            'login' => 'Connexion',
            'logout' => 'Déconnexion',
            'create' => 'Création',
            'update' => 'Modification',
            'delete' => 'Suppression',
            'export' => 'Export',
            'import' => 'Import',
            'backup' => 'Sauvegarde',
            'restore' => 'Restauration',
            'database' => 'Base de données',
            'files' => 'Fichiers',
            'upload' => 'Upload',
            'download' => 'Téléchargement'
        ];
        
        return $actions[$action] ?? ucfirst($action);
    }
    
    /**
     * Obtenir la couleur d'une action
     */
    public function getActionColor($action) {
        $colors = [
            'login' => 'success',
            'logout' => 'secondary',
            'create' => 'primary',
            'update' => 'info',
            'delete' => 'danger',
            'export' => 'warning',
            'import' => 'warning',
            'backup' => 'success',
            'restore' => 'info',
            'database' => 'primary',
            'files' => 'secondary',
            'upload' => 'success',
            'download' => 'info'
        ];
        
        return $colors[$action] ?? 'secondary';
    }
}
?>
