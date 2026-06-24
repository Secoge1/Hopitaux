<?php
/**
 * Configuration Avancée du Système
 * Intégration de tous les systèmes avancés
 */

class AdvancedConfig {
    private static $instance = null;
    private $config = [];
    
    private function __construct() {
        $this->loadConfiguration();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Charger la configuration
     */
    private function loadConfiguration() {
        $this->config = [
            // Configuration des notifications
            'notifications' => [
                'enabled' => true,
                'auto_cleanup_days' => 30,
                'max_notifications_per_user' => 100,
                'email_notifications' => false,
                'sms_notifications' => false
            ],
            
            // Configuration du cache
            'cache' => [
                'enabled' => true,
                'default_ttl' => 3600,
                'max_cache_size_mb' => 100,
                'auto_cleanup' => true,
                'cleanup_interval_hours' => 24
            ],
            
            // Configuration de la sécurité
            'security' => [
                'enabled' => true,
                'max_login_attempts' => 5,
                'lockout_duration_minutes' => 15,
                'session_timeout_hours' => 8,
                'password_min_length' => 8,
                'require_special_chars' => true,
                'enable_csrf_protection' => true,
                'enable_xss_protection' => true
            ],
            
            // Configuration de la maintenance
            'maintenance' => [
                'enabled' => true,
                'auto_maintenance' => true,
                'maintenance_hour' => 2,
                'cleanup_old_logs_days' => 30,
                'cleanup_old_backups_count' => 10,
                'optimize_tables' => true,
                'check_data_integrity' => true
            ],
            
            // Configuration des performances
            'performance' => [
                'enable_query_cache' => true,
                'enable_page_cache' => true,
                'compress_output' => true,
                'minify_css_js' => false,
                'enable_gzip' => true
            ],
            
            // Configuration des rapports
            'reports' => [
                'enable_auto_reports' => true,
                'daily_summary' => true,
                'weekly_analytics' => true,
                'monthly_reports' => true,
                'export_formats' => ['pdf', 'html', 'csv']
            ],
            
            // Configuration des sauvegardes
            'backups' => [
                'auto_backup' => true,
                'backup_frequency_hours' => 24,
                'max_backup_files' => 10,
                'compress_backups' => true,
                'backup_retention_days' => 30
            ],
            
            // Configuration des logs
            'logging' => [
                'enable_system_logs' => true,
                'enable_error_logs' => true,
                'enable_access_logs' => true,
                'log_level' => 'info',
                'max_log_file_size_mb' => 10
            ]
        ];
    }
    
    /**
     * Obtenir une valeur de configuration
     */
    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        
        return $value;
    }
    
    /**
     * Définir une valeur de configuration
     */
    public function set($key, $value) {
        $keys = explode('.', $key);
        $config = &$this->config;
        
        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }
        
        $config = $value;
    }
    
    /**
     * Vérifier si une fonctionnalité est activée
     */
    public function isEnabled($feature) {
        return $this->get($feature . '.enabled', false);
    }
    
    /**
     * Obtenir la configuration complète
     */
    public function getAll() {
        return $this->config;
    }
    
    /**
     * Initialiser tous les systèmes avancés
     */
    public function initializeAdvancedSystems() {
        // Créer d'abord les tables de base si elles n'existent pas
        $this->ensureBaseTablesExist();
        
        // Initialiser le système de sécurité
        if ($this->isEnabled('security')) {
            // Utiliser la version sécurisée qui n'a pas de clés étrangères
            require_once __DIR__ . '/../includes/SecuritySystemSafe.php';
            SecuritySystemSafe::getInstance();
        }
        
        // Initialiser le système de notifications
        if ($this->isEnabled('notifications')) {
            // Utiliser la version sécurisée qui n'a pas de clés étrangères
            require_once __DIR__ . '/../includes/NotificationSystemSafe.php';
            NotificationSystemSafe::getInstance();
        }
        
        // Initialiser le système de cache
        if ($this->isEnabled('cache')) {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance();
        }
        
        // Initialiser le système de maintenance
        if ($this->isEnabled('maintenance')) {
            require_once __DIR__ . '/../includes/MaintenanceSystem.php';
            MaintenanceSystem::getInstance();
        }
    }
    
    /**
     * S'assurer que les tables de base existent
     */
    private function ensureBaseTablesExist() {
        try {
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if ($db) {
                // Vérifier que les tables essentielles existent
                // Si createTables() existe, l'appeler (pour compatibilité avec l'ancienne version)
                if (method_exists($database, 'createTables')) {
                    $database->createTables();
                    
                    // Insérer des données d'exemple si nécessaire
                    if (method_exists($database, 'insertSampleData')) {
                        $database->insertSampleData();
                    }
                    
                    // Attendre un peu pour s'assurer que les tables sont créées
                    usleep(100000); // 0.1 seconde
                }
                
                // Vérifier que la table utilisateurs est vraiment prête
                $this->waitForTableReady($db, 'utilisateurs');
            }
        } catch (Exception $e) {
            // En cas d'erreur, continuer sans les tables de base
            // Les systèmes avancés s'adapteront
            // Pas d'affichage d'erreur pour ne pas casser l'interface
        }
    }
    
    /**
     * Attendre qu'une table soit prête
     */
    private function waitForTableReady($db, $tableName, $maxAttempts = 10) {
        for ($i = 0; $i < $maxAttempts; $i++) {
            try {
                // Vérifier que la table existe
                $stmt = $db->query("SHOW TABLES LIKE '$tableName'");
                if ($stmt->rowCount() > 0) {
                    // Vérifier que la table est accessible
                    $stmt = $db->query("DESCRIBE $tableName");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    if (in_array('id', $columns)) {
                        return true; // Table prête
                    }
                }
                
                // Attendre un peu plus
                usleep(200000); // 0.2 seconde
                
            } catch (Exception $e) {
                // Continuer à essayer
                usleep(200000);
            }
        }
        
        return false; // Table pas prête après tous les essais
    }
    
    /**
     * Exécuter la maintenance automatique si nécessaire
     */
    public function runScheduledMaintenance() {
        if ($this->isEnabled('maintenance') && $this->isEnabled('maintenance.auto_maintenance')) {
            $maintenanceHour = $this->get('maintenance.maintenance_hour', 2);
            $currentHour = (int)date('H');
            
            if ($currentHour === $maintenanceHour) {
                require_once __DIR__ . '/../includes/MaintenanceSystem.php';
                $maintenance = MaintenanceSystem::getInstance();
                $maintenance->scheduleMaintenance();
            }
        }
    }
    
    /**
     * Nettoyer automatiquement les données anciennes
     */
    public function runAutoCleanup() {
        // Nettoyer les notifications anciennes
        if ($this->isEnabled('notifications')) {
            require_once __DIR__ . '/../includes/NotificationSystem.php';
            $notifications = NotificationSystem::getInstance();
            $notifications->cleanupOldNotifications();
        }
        
        // Nettoyer le cache
        if ($this->isEnabled('cache')) {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            $cache = CacheSystem::getInstance();
            $cache->cleanup();
        }
        
        // Nettoyer les données de sécurité
        if ($this->isEnabled('security')) {
            require_once __DIR__ . '/../includes/SecuritySystem.php';
            $security = SecuritySystem::getInstance();
            $security->cleanupOldSecurityData();
        }
    }
    
    /**
     * Obtenir les statistiques système
     */
    public function getSystemStats() {
        $stats = [];
        
        // Statistiques du cache
        if ($this->isEnabled('cache')) {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            $cache = CacheSystem::getInstance();
            $stats['cache'] = $cache->getStats();
        }
        
        // Statistiques de sécurité
        if ($this->isEnabled('security')) {
            require_once __DIR__ . '/../includes/SecuritySystem.php';
            $security = SecuritySystem::getInstance();
            $stats['security'] = $security->getSecurityStats();
        }
        
        // Statistiques de maintenance
        if ($this->isEnabled('maintenance')) {
            require_once __DIR__ . '/../includes/MaintenanceSystem.php';
            $maintenance = MaintenanceSystem::getInstance();
            $stats['maintenance'] = $maintenance->getMaintenanceStats();
        }
        
        return $stats;
    }
    
    /**
     * Vérifier l'état de santé du système
     */
    public function getSystemHealth() {
        $health = [
            'status' => 'healthy',
            'issues' => [],
            'recommendations' => []
        ];
        
        try {
            // Vérifier la connexion à la base de données
            require_once __DIR__ . '/database.php';
            $database = new Database();
            $db = $database->getConnection();
            
            if (!$db) {
                $health['status'] = 'critical';
                $health['issues'][] = 'Impossible de se connecter à la base de données';
            }
            
            // Vérifier l'espace disque
            $freeSpace = disk_free_space(__DIR__ . '/..');
            $totalSpace = disk_total_space(__DIR__ . '/..');
            $usedSpacePercent = (($totalSpace - $freeSpace) / $totalSpace) * 100;
            
            if ($usedSpacePercent > 90) {
                $health['status'] = 'warning';
                $health['issues'][] = 'Espace disque presque plein (' . round($usedSpacePercent, 1) . '%)';
                $health['recommendations'][] = 'Nettoyer les fichiers temporaires et les anciens logs';
            }
            
            // Vérifier les permissions des dossiers
            $criticalDirs = ['uploads', 'cache', 'logs', 'backups'];
            foreach ($criticalDirs as $dir) {
                $dirPath = __DIR__ . '/../' . $dir;
                if (!is_writable($dirPath)) {
                    $health['status'] = 'warning';
                    $health['issues'][] = "Le dossier $dir n'est pas accessible en écriture";
                    $health['recommendations'][] = "Vérifier les permissions du dossier $dir";
                }
            }
            
            // Vérifier la taille des logs
            $logsDir = __DIR__ . '/../logs/';
            $logFiles = glob($logsDir . '*.log');
            $totalLogSize = 0;
            
            foreach ($logFiles as $logFile) {
                $totalLogSize += filesize($logFile);
            }
            
            $totalLogSizeMB = round($totalLogSize / 1024 / 1024, 2);
            if ($totalLogSizeMB > 100) {
                $health['status'] = 'warning';
                $health['issues'][] = "Taille totale des logs élevée: {$totalLogSizeMB} MB";
                $health['recommendations'][] = 'Nettoyer les anciens logs';
            }
            
        } catch (Exception $e) {
            $health['status'] = 'error';
            $health['issues'][] = 'Erreur lors de la vérification: ' . $e->getMessage();
        }
        
        return $health;
    }
    
    /**
     * Sauvegarder la configuration
     */
    public function saveConfiguration() {
        $configFile = __DIR__ . '/advanced_config.json';
        return file_put_contents($configFile, json_encode($this->config, JSON_PRETTY_PRINT)) !== false;
    }
    
    /**
     * Charger la configuration depuis un fichier
     */
    public function loadConfigurationFromFile($file = null) {
        if (!$file) {
            $file = __DIR__ . '/advanced_config.json';
        }
        
        if (file_exists($file)) {
            $config = json_decode(file_get_contents($file), true);
            if ($config) {
                $this->config = array_merge($this->config, $config);
                return true;
            }
        }
        
        return false;
    }
}
?>
