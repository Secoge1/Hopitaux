<?php
/**
 * SYSTÈME DE GESTION DE CACHE
 * À inclure au début de chaque page PHP
 * Usage: require_once __DIR__ . '/config/cache_manager.php';
 */

class CacheManager {
    private static $version = null;
    
    /**
     * Initialise le système anti-cache
     */
    public static function init() {
        // 1. Désactiver TOUT le cache du navigateur pour les pages PHP
        self::disableBrowserCache();
        
        // 2. Vider le cache PHP opcache si activé
        self::clearOpCache();
        
        // 3. Charger/générer la version pour les assets (CSS/JS)
        self::loadVersion();
    }
    
    /**
     * Désactive complètement le cache du navigateur
     */
    private static function disableBrowserCache() {
        // Headers pour empêcher la mise en cache
        header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");
        header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date dans le passé
        
        // Header pour forcer la revalidation
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
        
        // ETag dynamique basé sur le timestamp actuel
        header("ETag: \"" . md5(microtime()) . "\"");
    }
    
    /**
     * Vide le cache PHP OpCache
     */
    private static function clearOpCache() {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        
        if (function_exists('opcache_invalidate')) {
            // Invalider le fichier actuel
            @opcache_invalidate(__FILE__, true);
        }
    }
    
    /**
     * Charge ou génère une version pour les assets
     */
    private static function loadVersion() {
        $versionFile = __DIR__ . '/cache_version.txt';
        
        // Si le fichier n'existe pas ou a plus de 5 minutes, régénérer
        if (!file_exists($versionFile) || (time() - filemtime($versionFile)) > 300) {
            self::$version = time();
            @file_put_contents($versionFile, self::$version);
        } else {
            self::$version = @file_get_contents($versionFile);
        }
    }
    
    /**
     * Retourne la version actuelle pour le cache-busting
     */
    public static function getVersion() {
        if (self::$version === null) {
            self::loadVersion();
        }
        return self::$version;
    }
    
    /**
     * Retourne une URL avec version pour cache-busting
     * Usage: CacheManager::asset('assets/css/style.css')
     */
    public static function asset($path) {
        $version = self::getVersion();
        $separator = (strpos($path, '?') !== false) ? '&' : '?';
        return $path . $separator . 'v=' . $version;
    }
    
    /**
     * Force le rechargement du cache (à appeler après une modification)
     */
    public static function refresh() {
        $versionFile = __DIR__ . '/cache_version.txt';
        self::$version = time();
        @file_put_contents($versionFile, self::$version);
        self::clearOpCache();
    }
    
    /**
     * Retourne un timestamp unique pour chaque requête
     */
    public static function getTimestamp() {
        return microtime(true);
    }
}

// Auto-initialisation
CacheManager::init();

?>
