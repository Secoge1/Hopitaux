<?php
/**
 * Système de Maintenance Automatique
 * Optimisation et maintenance du système
 */

class MaintenanceSystem {
    private static $instance = null;
    private $pdo;
    private $logFile;
    
    private function __construct() {
        $this->pdo = $this->getConnection();
        $this->logFile = __DIR__ . '/../logs/maintenance.log';
        $this->createMaintenanceTable();
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
            return new PDO(
                "mysql:host=localhost;dbname=efficasante;charset=utf8",
                "root",
                ""
            );
        } catch(PDOException $e) {
            return null;
        }
    }
    
    /**
     * Créer la table de maintenance
     */
    private function createMaintenanceTable() {
        if (!$this->pdo) return;
        
        $sql = "CREATE TABLE IF NOT EXISTS maintenance_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_name VARCHAR(100) NOT NULL,
            status ENUM('success', 'error', 'warning') NOT NULL,
            details TEXT,
            execution_time FLOAT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_task (task_name),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->pdo->exec($sql);
    }
    
    /**
     * Exécuter la maintenance complète
     */
    public function runFullMaintenance() {
        $this->log("Début de la maintenance complète");
        
        $results = [];
        
        // 1. Nettoyage de la base de données
        $results['database_cleanup'] = $this->cleanupDatabase();
        
        // 2. Optimisation des tables
        $results['table_optimization'] = $this->optimizeTables();
        
        // 3. Nettoyage du cache
        $results['cache_cleanup'] = $this->cleanupCache();
        
        // 4. Nettoyage des logs
        $results['logs_cleanup'] = $this->cleanupLogs();
        
        // 5. Nettoyage des fichiers temporaires
        $results['temp_cleanup'] = $this->cleanupTempFiles();
        
        // 6. Vérification de l'intégrité
        $results['integrity_check'] = $this->checkDataIntegrity();
        
        // 7. Nettoyage des sauvegardes anciennes
        $results['backup_cleanup'] = $this->cleanupOldBackups();
        
        // 8. Nettoyage des données de sécurité
        $results['security_cleanup'] = $this->cleanupSecurityData();
        
        $this->log("Maintenance complète terminée", $results);
        
        return $results;
    }
    
    /**
     * Nettoyage de la base de données
     */
    private function cleanupDatabase() {
        if (!$this->pdo) return ['success' => false, 'message' => 'Pas de connexion DB'];

        require_once __DIR__ . '/saas/TenantScope.php';
        $tenantId = TenantScope::currentTenantId();
        if (!$tenantId) {
            return ['success' => false, 'message' => 'Contexte établissement requis pour le nettoyage'];
        }
        
        try {
            $startTime = microtime(true);
            $cleaned = 0;

            $orphanDeletes = [
                'consultations' => 'patient_id',
                'rendez_vous' => 'patient_id',
                'analyses' => 'patient_id',
                'paiements' => 'patient_id',
                'documents_patients' => 'patient_id',
            ];
            foreach ($orphanDeletes as $table => $fk) {
                $sql = "DELETE t FROM `{$table}` t
                        LEFT JOIN patients p ON t.`{$fk}` = p.id AND p.tenant_id = t.tenant_id
                        WHERE t.tenant_id = ? AND p.id IS NULL";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$tenantId]);
                $cleaned += $stmt->rowCount();
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logMaintenanceTask('database_cleanup', 'success', "Nettoyé $cleaned enregistrements orphelins", $executionTime);
            
            return ['success' => true, 'cleaned' => $cleaned, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('database_cleanup', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Optimisation des tables
     */
    private function optimizeTables() {
        if (!$this->pdo) return ['success' => false, 'message' => 'Pas de connexion DB'];
        
        try {
            $startTime = microtime(true);
            $optimized = 0;
            
            // Obtenir la liste des tables
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                try {
                    $sql = "OPTIMIZE TABLE `$table`";
                    $this->pdo->exec($sql);
                    $optimized++;
                } catch (Exception $e) {
                    // Continuer avec les autres tables
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logMaintenanceTask('table_optimization', 'success', "Optimisé $optimized tables", $executionTime);
            
            return ['success' => true, 'optimized' => $optimized, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('table_optimization', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Nettoyage du cache
     */
    private function cleanupCache() {
        try {
            $startTime = microtime(true);
            
            require_once __DIR__ . '/CacheSystem.php';
            $cacheSystem = CacheSystem::getInstance();
            $deleted = $cacheSystem->cleanup();
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logMaintenanceTask('cache_cleanup', 'success', "Nettoyé $deleted fichiers de cache", $executionTime);
            
            return ['success' => true, 'deleted' => $deleted, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('cache_cleanup', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Nettoyage des logs
     */
    private function cleanupLogs() {
        try {
            $startTime = microtime(true);
            $deleted = 0;
            
            $logsDir = __DIR__ . '/../logs/';
            $files = glob($logsDir . '*.log');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    // Supprimer les logs de plus de 30 jours
                    if (filemtime($file) < (time() - 30 * 24 * 3600)) {
                        unlink($file);
                        $deleted++;
                    }
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logMaintenanceTask('logs_cleanup', 'success', "Nettoyé $deleted fichiers de logs", $executionTime);
            
            return ['success' => true, 'deleted' => $deleted, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('logs_cleanup', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Nettoyage des fichiers temporaires
     */
    private function cleanupTempFiles() {
        try {
            $startTime = microtime(true);
            $deleted = 0;
            
            $tempDirs = [
                __DIR__ . '/../uploads/temp/',
                __DIR__ . '/../cache/temp/',
                __DIR__ . '/../backups/temp/'
            ];
            
            foreach ($tempDirs as $tempDir) {
                if (is_dir($tempDir)) {
                    $files = glob($tempDir . '*');
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            // Supprimer les fichiers de plus de 24 heures
                            if (filemtime($file) < (time() - 24 * 3600)) {
                                unlink($file);
                                $deleted++;
                            }
                        }
                    }
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logMaintenanceTask('temp_cleanup', 'success', "Nettoyé $deleted fichiers temporaires", $executionTime);
            
            return ['success' => true, 'deleted' => $deleted, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('temp_cleanup', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Vérification de l'intégrité des données
     */
    private function checkDataIntegrity() {
        if (!$this->pdo) return ['success' => false, 'message' => 'Pas de connexion DB'];

        require_once __DIR__ . '/saas/TenantScope.php';
        
        try {
            $startTime = microtime(true);
            $issues = [];

            $patientsWithoutConsultations = TenantScope::count(
                $this->pdo,
                'patients',
                ['id NOT IN (SELECT patient_id FROM consultations WHERE tenant_id = patients.tenant_id)']
            );
            if ($patientsWithoutConsultations > 0) {
                $issues[] = "$patientsWithoutConsultations patients sans consultations";
            }

            $medecinsWithoutConsultations = TenantScope::count(
                $this->pdo,
                'medecins',
                ['id NOT IN (SELECT medecin_id FROM consultations WHERE tenant_id = medecins.tenant_id)']
            );
            if ($medecinsWithoutConsultations > 0) {
                $issues[] = "$medecinsWithoutConsultations médecins sans consultations";
            }

            $rdvPast = TenantScope::count(
                $this->pdo,
                'rendez_vous',
                ["date_rdv < NOW()", "statut = 'planifie'"]
            );
            
            if ($rdvPast > 0) {
                $issues[] = "$rdvPast rendez-vous passés non traités";
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $status = empty($issues) ? 'success' : 'warning';
            $message = empty($issues) ? 'Aucun problème d\'intégrité détecté' : implode(', ', $issues);
            
            $this->logMaintenanceTask('integrity_check', $status, $message, $executionTime);
            
            return ['success' => true, 'issues' => $issues, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('integrity_check', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Nettoyage des sauvegardes anciennes
     */
    private function cleanupOldBackups() {
        try {
            $startTime = microtime(true);
            $deleted = 0;
            
            $backupsDir = __DIR__ . '/../backups/';
            $files = glob($backupsDir . '*.sql');
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    // Garder seulement les 10 dernières sauvegardes
                    if (count($files) > 10) {
                        unlink($file);
                        $deleted++;
                    }
                }
            }
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logMaintenanceTask('backup_cleanup', 'success', "Nettoyé $deleted sauvegardes anciennes", $executionTime);
            
            return ['success' => true, 'deleted' => $deleted, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('backup_cleanup', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Nettoyage des données de sécurité
     */
    private function cleanupSecurityData() {
        try {
            $startTime = microtime(true);
            
            require_once __DIR__ . '/SecuritySystem.php';
            $securitySystem = SecuritySystem::getInstance();
            $securitySystem->cleanupOldSecurityData();
            
            $executionTime = microtime(true) - $startTime;
            
            $this->logMaintenanceTask('security_cleanup', 'success', 'Données de sécurité nettoyées', $executionTime);
            
            return ['success' => true, 'execution_time' => $executionTime];
            
        } catch (Exception $e) {
            $this->logMaintenanceTask('security_cleanup', 'error', $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Enregistrer une tâche de maintenance
     */
    private function logMaintenanceTask($taskName, $status, $details, $executionTime = null) {
        if (!$this->pdo) return;
        
        try {
            $sql = "INSERT INTO maintenance_logs (task_name, status, details, execution_time) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskName, $status, $details, $executionTime]);
        } catch (Exception $e) {
            // En cas d'erreur, on continue
        }
    }
    
    /**
     * Enregistrer dans le fichier de log
     */
    private function log($message, $data = null) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message";
        
        if ($data) {
            $logEntry .= " - " . json_encode($data);
        }
        
        $logEntry .= "\n";
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Obtenir les statistiques de maintenance
     */
    public function getMaintenanceStats() {
        if (!$this->pdo) return [];
        
        try {
            $stats = [];
            
            // Dernière maintenance
            $sql = "SELECT * FROM maintenance_logs ORDER BY created_at DESC LIMIT 1";
            $stmt = $this->pdo->query($sql);
            $lastMaintenance = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastMaintenance) {
                $stats['last_maintenance'] = $lastMaintenance['created_at'];
                $stats['last_status'] = $lastMaintenance['status'];
            }
            
            // Statistiques des 7 derniers jours
            $sql = "SELECT 
                        COUNT(*) as total_tasks,
                        SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_tasks,
                        SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as failed_tasks,
                        AVG(execution_time) as avg_execution_time
                    FROM maintenance_logs 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            
            $stmt = $this->pdo->query($sql);
            $weeklyStats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($weeklyStats) {
                $stats['weekly_total'] = $weeklyStats['total_tasks'];
                $stats['weekly_success'] = $weeklyStats['successful_tasks'];
                $stats['weekly_failed'] = $weeklyStats['failed_tasks'];
                $stats['weekly_avg_time'] = round($weeklyStats['avg_execution_time'], 2);
            }
            
            return $stats;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Planifier la maintenance automatique
     */
    public function scheduleMaintenance() {
        // Vérifier si la maintenance doit être exécutée (tous les jours à 2h du matin)
        $currentHour = (int)date('H');
        
        if ($currentHour === 2) {
            // Vérifier si la maintenance a déjà été exécutée aujourd'hui
            if (!$this->hasMaintenanceRunToday()) {
                $this->runFullMaintenance();
            }
        }
    }
    
    /**
     * Vérifier si la maintenance a déjà été exécutée aujourd'hui
     */
    private function hasMaintenanceRunToday() {
        if (!$this->pdo) return false;
        
        try {
            $sql = "SELECT COUNT(*) as count FROM maintenance_logs 
                    WHERE DATE(created_at) = CURDATE() AND task_name = 'full_maintenance'";
            $stmt = $this->pdo->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result && $result['count'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>


