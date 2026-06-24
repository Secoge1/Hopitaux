<?php
/**
 * Système de Cache pour Améliorer les Performances
 * Gestion du cache des données fréquemment utilisées
 */

class CacheSystem {
    private static $instance = null;
    private $cacheDir;
    private $defaultTTL = 300; // 5 minutes par défaut (au lieu d'1 heure)
    
    private function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/';
        $this->createCacheDirectory();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Créer le répertoire de cache s'il n'existe pas
     */
    private function createCacheDirectory() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        
        // Créer/mettre à jour le .htaccess (syntaxe Apache 2.4)
        $htaccess = $this->cacheDir . '.htaccess';
        $correctContent = "Require all denied\n";
        if (!file_exists($htaccess) || file_get_contents($htaccess) !== $correctContent) {
            file_put_contents($htaccess, $correctContent);
        }
    }
    
    /**
     * Générer une clé de cache unique
     * Le nom du fichier inclut un préfixe lisible pour permettre l'invalidation par motif (glob)
     */
    private function generateCacheKey($key, $params = []) {
        $hash = md5($key . serialize($params));
        $safePrefix = preg_replace('/[^a-z0-9_]/', '_', strtolower($key));
        return $this->cacheDir . $safePrefix . '_' . $hash . '.cache';
    }
    
    /**
     * Mettre en cache des données
     */
    public function set($key, $data, $ttl = null, $params = []) {
        $ttl = $ttl ?? $this->defaultTTL;
        $cacheFile = $this->generateCacheKey($key, $params);
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        try {
            return file_put_contents($cacheFile, serialize($cacheData)) !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Récupérer des données du cache
     */
    public function get($key, $params = []) {
        $cacheFile = $this->generateCacheKey($key, $params);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        try {
            $cacheData = unserialize(file_get_contents($cacheFile));
            
            if (!$cacheData || !isset($cacheData['expires']) || !isset($cacheData['data'])) {
                return null;
            }
            
            // Vérifier l'expiration
            if (time() > $cacheData['expires']) {
                unlink($cacheFile);
                return null;
            }
            
            return $cacheData['data'];
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Vérifier si une clé existe dans le cache
     */
    public function has($key, $params = []) {
        $cacheFile = $this->generateCacheKey($key, $params);
        
        if (!file_exists($cacheFile)) {
            return false;
        }
        
        try {
            $cacheData = unserialize(file_get_contents($cacheFile));
            
            if (!$cacheData || !isset($cacheData['expires'])) {
                return false;
            }
            
            return time() <= $cacheData['expires'];
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Supprimer une clé du cache
     */
    public function delete($key, $params = []) {
        $cacheFile = $this->generateCacheKey($key, $params);
        
        if (file_exists($cacheFile)) {
            try {
                return unlink($cacheFile);
            } catch (Exception $e) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Vider tout le cache
     */
    public function clear() {
        try {
            $files = glob($this->cacheDir . '*.cache');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Nettoyer le cache expiré
     */
    public function cleanup() {
        try {
            $files = glob($this->cacheDir . '*.cache');
            $deleted = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    try {
                        $cacheData = unserialize(file_get_contents($file));
                        
                        if (!$cacheData || !isset($cacheData['expires']) || time() > $cacheData['expires']) {
                            unlink($file);
                            $deleted++;
                        }
                    } catch (Exception $e) {
                        // Supprimer le fichier corrompu
                        unlink($file);
                        $deleted++;
                    }
                }
            }
            
            return $deleted;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Obtenir des statistiques du cache
     */
    public function getStats() {
        try {
            $files = glob($this->cacheDir . '*.cache');
            $totalFiles = count($files);
            $totalSize = 0;
            $expiredFiles = 0;
            $validFiles = 0;
            
            foreach ($files as $file) {
                if (is_file($file)) {
                    $totalSize += filesize($file);
                    
                    try {
                        $cacheData = unserialize(file_get_contents($file));
                        
                        if (!$cacheData || !isset($cacheData['expires']) || time() > $cacheData['expires']) {
                            $expiredFiles++;
                        } else {
                            $validFiles++;
                        }
                    } catch (Exception $e) {
                        $expiredFiles++;
                    }
                }
            }
            
            return [
                'total_files' => $totalFiles,
                'valid_files' => $validFiles,
                'expired_files' => $expiredFiles,
                'total_size' => $totalSize,
                'total_size_mb' => round($totalSize / 1024 / 1024, 2)
            ];
        } catch (Exception $e) {
            return [
                'total_files' => 0,
                'valid_files' => 0,
                'expired_files' => 0,
                'total_size' => 0,
                'total_size_mb' => 0
            ];
        }
    }
    
    /**
     * Cache intelligent pour les requêtes de base de données
     */
    public function remember($key, $callback, $ttl = null, $params = []) {
        // Essayer de récupérer du cache
        $cached = $this->get($key, $params);
        
        if ($cached !== null) {
            return $cached;
        }
        
        // Exécuter le callback et mettre en cache
        try {
            $data = $callback();
            $this->set($key, $data, $ttl, $params);
            return $data;
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Statistiques accueil / dashboard — toujours lues en direct (pas de cache fichier).
     * Le cache provoquait des compteurs obsolètes après TRUNCATE / import SQL.
     */
    public function getDashboardStats() {
        require_once __DIR__ . '/saas/DashboardStats.php';
        return DashboardStats::get();
    }
    
    /**
     * Cache pour les listes de patients
     */
    private function tenantCacheSuffix() {
        require_once __DIR__ . '/saas/TenantContext.php';
        TenantContext::bindFromSession();
        $tid = TenantContext::getTenantId();
        return $tid ? ('_t' . $tid) : '_t0';
    }

    public function getPatientsList($filters = [], $limit = 50) {
        $cacheKey = 'patients_list_' . md5(serialize($filters) . $limit . $this->tenantCacheSuffix());
        
        return $this->remember($cacheKey, function() use ($filters, $limit) {
            require_once __DIR__ . '/../config/db.php';
            require_once __DIR__ . '/saas/TenantScope.php';
            $db = getDB();
            
            try {
                $where = [];
                $params = [];
                TenantScope::appendWhere($db, 'patients', $where, $params);
                
                if (!empty($filters['statut']) && $filters['statut'] === 'supprime') {
                    $where[] = "statut = 'supprime'";
                } else {
                    $where[] = '(statut IS NULL OR statut <> \'supprime\')';
                    if (!empty($filters['statut'])) {
                        $where[] = 'statut = ?';
                        $params[] = $filters['statut'];
                    }
                }
                
                if (!empty($filters['search'])) {
                    $where[] = "(nom LIKE ? OR prenom LIKE ? OR numero_dossier LIKE ?)";
                    $searchTerm = '%' . $filters['search'] . '%';
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                
                $sql = "SELECT * FROM patients $whereClause ORDER BY date_creation DESC, nom, prenom LIMIT ?";
                $params[] = $limit;
                
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                return [];
            }
        }, 180); // Cache de 3 minutes
    }
    
    /**
     * Cache pour les listes de médecins
     */
    public function getMedecinsList($filters = []) {
        $cacheKey = 'medecins_list_' . md5(serialize($filters) . $this->tenantCacheSuffix());
        
        return $this->remember($cacheKey, function() use ($filters) {
            require_once __DIR__ . '/../config/database.php';
            require_once __DIR__ . '/saas/TenantScope.php';
            $database = new Database();
            $db = $database->getConnection();
            
            try {
                $where = [];
                $params = [];
                TenantScope::appendWhere($db, 'medecins', $where, $params);
                
                if (!empty($filters['specialite'])) {
                    $where[] = "specialite = ?";
                    $params[] = $filters['specialite'];
                }
                
                if (!empty($filters['statut'])) {
                    $where[] = "statut = ?";
                    $params[] = $filters['statut'];
                }
                
                $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
                
                $sql = "SELECT * FROM medecins $whereClause ORDER BY nom, prenom";
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                return [];
            }
        }, 300); // Cache de 5 minutes
    }
    
    /**
     * Invalider le cache lié aux patients (préfixe patients_list_ grâce au nouveau schéma de nommage)
     */
    public function invalidatePatientsCache() {
        $files = glob($this->cacheDir . 'patients_list_*.cache');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        // Invalider aussi le dashboard (les stats patients en font partie)
        $this->invalidateDashboardCache();
    }
    
    /**
     * Invalider le cache lié aux médecins (préfixe medecins_list_ grâce au nouveau schéma de nommage)
     */
    public function invalidateMedecinsCache() {
        $files = glob($this->cacheDir . 'medecins_list_*.cache');
        if ($files) {
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        // Invalider aussi le dashboard (le compteur médecins en fait partie)
        $this->invalidateDashboardCache();
    }
    
    /**
     * Invalider le cache du dashboard (tous les tenants + listes liées).
     */
    public function invalidateDashboardCache() {
        $patterns = [
            'dashboard_stats_*.cache',
            'patients_list_*.cache',
            'medecins_list_*.cache',
        ];
        foreach ($patterns as $pattern) {
            $files = glob($this->cacheDir . $pattern);
            if (!$files) {
                continue;
            }
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
?>







