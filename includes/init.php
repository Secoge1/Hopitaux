<?php
/**
 * Fichier d'Initialisation des Systèmes Avancés
 * Inclure ce fichier au début de chaque page pour activer toutes les fonctionnalités
 */

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}
if (PHP_SAPI !== 'cli' && !headers_sent() && !defined('APP_SKIP_HTML_CONTENT_TYPE')) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Charger la configuration de base de données en premier
require_once __DIR__ . '/../config/db.php';

if (
    defined('APP_FORCE_HTTPS') && APP_FORCE_HTTPS
    && PHP_SAPI !== 'cli'
    && !headers_sent()
    && empty($_SERVER['HTTPS'])
    && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) !== 'https'
) {
    $host = $_SERVER['HTTP_HOST'] ?? 'pharmasmart.secogesarl.com';
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    header('Location: https://' . $host . $uri, true, 301);
    exit;
}

// Fuseau horaire — réglé après chargement SaaS (paramètres par tenant)

// Charger SystemParameters pour accès global aux paramètres du système
require_once __DIR__ . '/../config/SystemParameters.php';
// Charger header_logo qui définit getNomEtablissement() et les helpers de logo
require_once __DIR__ . '/header_logo.php';
require_once __DIR__ . '/app_urls.php';

// Charger la configuration avancée
require_once __DIR__ . '/../config/AdvancedConfig.php';

// Charger la classe d'authentification
require_once __DIR__ . '/../config/Auth.php';

// SaaS multi-tenant — schéma + garde abonnement
require_once __DIR__ . '/saas/SubscriptionPlan.php';
require_once __DIR__ . '/saas/SubscriptionService.php';
require_once __DIR__ . '/saas/TenantSchema.php';
require_once __DIR__ . '/saas/TenantContext.php';
require_once __DIR__ . '/saas/saas_helpers.php';

TenantSchema::ensure();

require_once __DIR__ . '/medecin_profil.php';

date_default_timezone_set(saas_parametre_timezone());

if (!saas_is_whitelisted_page()) {
    $saasService = SubscriptionService::getInstance();
    $saasService->loadForSession();
    if (isset($_SESSION['user_connected']) && $_SESSION['user_connected'] === true) {
        saas_require_tenant_context();
        $saasService->guardCurrentPage();
    }
}

// Initialiser la configuration
$advancedConfig = AdvancedConfig::getInstance();

// Initialiser tous les systèmes avancés
$advancedConfig->initializeAdvancedSystems();

// Exécuter la maintenance automatique si nécessaire
$advancedConfig->runScheduledMaintenance();

// Nettoyer automatiquement les données anciennes
$advancedConfig->runAutoCleanup();

// Fonction pour obtenir les notifications de l'utilisateur connecté
function getUserNotifications($userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return [];
    }
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $pdo = getDB();
        
        require_once __DIR__ . '/saas/TenantScope.php';
        $where = ['user_id = ?'];
        $params = [$userId];
        TenantScope::appendWhere($pdo, 'notifications', $where, $params);
        $sql = 'SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . ' ORDER BY date_creation DESC LIMIT 10';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

// Fonction pour obtenir le nombre de notifications non lues
function getUnreadNotificationCount($userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return 0;
    }
    
    try {
        require_once __DIR__ . '/../config/db.php';
        $pdo = getDB();
        
        require_once __DIR__ . '/saas/TenantScope.php';
        $where = ['user_id = ?', 'lu = FALSE'];
        $params = [$userId];
        TenantScope::appendWhere($pdo, 'notifications', $where, $params);
        $sql = 'SELECT COUNT(*) as count FROM notifications WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = $stmt->fetch();
        return $result ? $result['count'] : 0;
    } catch (Exception $e) {
        return 0;
    }
}

// Fonction pour obtenir les statistiques du dashboard avec cache
function getDashboardStats() {
    try {
        require_once __DIR__ . '/CacheSystem.php';
        $cache = CacheSystem::getInstance();
        return $cache->getDashboardStats();
    } catch (Exception $e) {
        // Fallback vers les statistiques directes
        return getDirectDashboardStats();
    }
}

// Fonction de fallback pour les statistiques directes
function getDirectDashboardStats() {
    try {
        require_once __DIR__ . '/saas/DashboardStats.php';
        return DashboardStats::get();
    } catch (Exception $e) {
        return [
            'patients' => 0,
            'consultations_aujourd_hui' => 0,
            'rdv_aujourd_hui' => 0,
            'analyses_en_cours' => 0,
            'paiements_en_attente' => 0,
            'paiements_total' => 0,
            'medecins_actifs' => 0,
            'utilisateurs_actifs' => 0,
            'last_updated' => date('Y-m-d H:i:s'),
            'error' => $e->getMessage(),
        ];
    }
}

// Fonction pour obtenir les statistiques système
function getSystemStats() {
    try {
        return $advancedConfig->getSystemStats();
    } catch (Exception $e) {
        return [];
    }
}

// Fonction pour vérifier l'état de santé du système
function getSystemHealth() {
    try {
        return $advancedConfig->getSystemHealth();
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'issues' => ['Erreur lors de la vérification: ' . $e->getMessage()],
            'recommendations' => []
        ];
    }
}

// Fonction pour créer une notification
function createNotification($type, $titre, $message, $module = null, $lien = null, $userId = null) {
    if (!$userId && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return false;
    }
    
    try {
        require_once __DIR__ . '/NotificationSystem.php';
        $notifications = NotificationSystem::getInstance();
        return $notifications->createNotification($userId, $type, $titre, $message, $module, $lien);
    } catch (Exception $e) {
        return false;
    }
}

// Fonction pour créer une notification pour un rôle
function createNotificationForRole($role, $type, $titre, $message, $module = null, $lien = null) {
    try {
        require_once __DIR__ . '/NotificationSystem.php';
        $notifications = NotificationSystem::getInstance();
        return $notifications->createNotificationForRole($role, $type, $titre, $message, $module, $lien);
    } catch (Exception $e) {
        return false;
    }
}

// Fonction pour valider et nettoyer les entrées
function sanitizeInput($input, $type = 'string') {
    try {
        require_once __DIR__ . '/SecuritySystem.php';
        $security = SecuritySystem::getInstance();
        return $security->sanitizeInput($input, $type);
    } catch (Exception $e) {
        // Fallback simple
        if (is_array($input)) {
            return array_map('htmlspecialchars', $input);
        }
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }
}

// Fonction pour valider un mot de passe
function validatePassword($password) {
    try {
        require_once __DIR__ . '/SecuritySystem.php';
        $security = SecuritySystem::getInstance();
        return $security->validatePassword($password);
    } catch (Exception $e) {
        return ['valid' => false, 'message' => 'Erreur de validation'];
    }
}

// Fonction pour hacher un mot de passe
function hashPassword($password) {
    try {
        require_once __DIR__ . '/SecuritySystem.php';
        $security = SecuritySystem::getInstance();
        return $security->hashPassword($password);
    } catch (Exception $e) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

// Fonction pour vérifier un mot de passe
function verifyPassword($password, $hash) {
    try {
        require_once __DIR__ . '/SecuritySystem.php';
        $security = SecuritySystem::getInstance();
        return $security->verifyPassword($password, $hash);
    } catch (Exception $e) {
        return password_verify($password, $hash);
    }
}

// Fonction pour générer un token CSRF
function generateCSRFToken() {
    try {
        require_once __DIR__ . '/SecuritySystem.php';
        $security = SecuritySystem::getInstance();
        return $security->generateCSRFToken();
    } catch (Exception $e) {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Fonction pour valider un token CSRF
function validateCSRFToken($token) {
    try {
        require_once __DIR__ . '/SecuritySystem.php';
        $security = SecuritySystem::getInstance();
        return $security->validateCSRFToken($token);
    } catch (Exception $e) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Fonction pour exécuter la maintenance manuellement
function runMaintenance() {
    try {
        require_once __DIR__ . '/MaintenanceSystem.php';
        $maintenance = MaintenanceSystem::getInstance();
        return $maintenance->runFullMaintenance();
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Fonction pour nettoyer le cache
function clearCache() {
    try {
        require_once __DIR__ . '/CacheSystem.php';
        $cache = CacheSystem::getInstance();
        return $cache->clear();
    } catch (Exception $e) {
        return false;
    }
}

// Fonction pour obtenir les informations de l'utilisateur connecté
function getCurrentUser() {
    if (isset($_SESSION['user_id'])) {
        try {
            require_once __DIR__ . '/../config/db.php';
            require_once __DIR__ . '/saas/TenantScope.php';
            $db = getDB();
            $sql = 'SELECT * FROM utilisateurs WHERE id = ?' . TenantScope::andOwnedByTenant($db, 'utilisateurs');
            $stmt = $db->prepare($sql);
            $stmt->execute(TenantScope::paramsForId($db, 'utilisateurs', (int) $_SESSION['user_id']));
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    return null;
}

// Fonction pour vérifier les permissions de l'utilisateur
function hasPermission($permission) {
    $user = getCurrentUser();
    
    if (!$user) {
        return false;
    }
    
    // Permissions basées sur le rôle
    $permissions = [
        'admin' => ['all'],
        'medecin' => ['patients_read', 'patients_write', 'consultations_read', 'consultations_write', 'rendez_vous_read', 'rendez_vous_write'],
        'sage_femme' => ['patients_read', 'patients_write', 'consultations_read', 'consultations_write', 'rendez_vous_read', 'rendez_vous_write', 'analyses_read'],
        'secretaire' => ['patients_read', 'patients_write', 'rendez_vous_read', 'rendez_vous_write', 'paiements_read', 'paiements_write'],
        'infirmier' => ['patients_read', 'analyses_read', 'analyses_write'],
        'major' => ['analyses_read', 'analyses_write'],
        'comptable' => ['paiements_read', 'paiements_write', 'finances_read', 'finances_write']
    ];
    
    $userRole = $user['role'];
    
    if (!isset($permissions[$userRole])) {
        return false;
    }
    
    return in_array('all', $permissions[$userRole]) || in_array($permission, $permissions[$userRole]);
}

// Fonction pour rediriger avec message
function redirectWithMessage($url, $message, $type = 'info') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header("Location: $url");
    exit;
}

// Fonction pour afficher les messages flash
function displayFlashMessages() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        $alertClass = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info'
        ];
        
        $iconClass = [
            'success' => 'fa-check-circle',
            'error' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle'
        ];
        
        $class = $alertClass[$type] ?? 'alert-info';
        $icon = $iconClass[$type] ?? 'fa-info-circle';
        
        return "<div class='alert $class alert-dismissible fade show' role='alert'>
                    <i class='fas $icon me-2'></i>
                    $message
                    <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                </div>";
    }
    
    return '';
}

// Fonction pour formater un montant en FCFA (wrapper)
function formatFCFAWrapper($amount) {
    try {
        require_once __DIR__ . '/currency_helper.php';
        return formatFCFA($amount);
    } catch (Exception $e) {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}

// Fonction pour logger une action
function logAction($action, $details = '') {
    try {
        require_once __DIR__ . '/../config/SystemLogs.php';
        $logs = new SystemLogs();
        $userId = $_SESSION['user_id'] ?? null;
        $logs->addLog($action, $details, $userId);
    } catch (Exception $e) {
        // En cas d'erreur, on continue
    }
}

// --- Bannière d'installation PWA (tous les smartphones) ---
require_once __DIR__ . '/pwa.php';
if (pwa_is_mobile_device()) {
    ob_start(function ($html) {
        return pwa_inject_install_banner($html);
    });
}

// --- Mode mobile PWA (mobile_layout + barre basse) — pas wptouch ---
if (is_file(__DIR__ . '/mobile_layout.php')) {
    require_once __DIR__ . '/mobile_layout.php';
}

if (!defined('IS_MOBILE_LAYOUT')) {
    $cookiePath = function_exists('mobile_layout_cookie_path')
        ? mobile_layout_cookie_path()
        : '/';
    $quitMobile = isset($_GET['mobile']) && $_GET['mobile'] === '0';
    if ($quitMobile && !headers_sent()) {
        setcookie('efficasante_mobile', '', time() - 3600, $cookiePath);
        setcookie('efficasante_pwa_standalone', '', time() - 3600, $cookiePath);
        unset($_SESSION['mobile_mode']);
    }

    $mobileParam = isset($_GET['mobile']) && $_GET['mobile'] === '1';
    $mobileCookie = !$quitMobile
        && isset($_COOKIE['efficasante_mobile']) && $_COOKIE['efficasante_mobile'] === '1';
    $pwaStandalone = !$quitMobile
        && isset($_COOKIE['efficasante_pwa_standalone']) && $_COOKIE['efficasante_pwa_standalone'] === '1';
    $mobileAuto = !$quitMobile && !$mobileParam
        && function_exists('pwa_is_mobile_device') && pwa_is_mobile_device();

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $pharmaStandalone = (strpos($script, '/pharma_erp/') !== false)
        || (defined('PHARMA_ERP_STANDALONE') && PHARMA_ERP_STANDALONE);
    if ($pharmaStandalone && !defined('PHARMA_ERP_STANDALONE')) {
        define('PHARMA_ERP_STANDALONE', true);
    }

    define('IS_MOBILE_LAYOUT', !$pharmaStandalone && ($mobileParam || $mobileCookie || $pwaStandalone || $mobileAuto));

    if (IS_MOBILE_LAYOUT && !headers_sent()) {
        $_SESSION['mobile_mode'] = true;
        setcookie('efficasante_mobile', '1', time() + 86400 * 30, $cookiePath);
    }
}

// Chemin de base URL de l'application (identique à efficasante_web_base_path dans header_logo.php)
if (!defined('BASE_PATH')) {
    if (function_exists('efficasante_web_base_path')) {
        define('BASE_PATH', efficasante_web_base_path());
    } else {
        define('BASE_PATH', '');
    }
}

// Fallback OB si le chrome PWA n'a pas été rendu par app_layout
if (defined('IS_MOBILE_LAYOUT') && IS_MOBILE_LAYOUT && function_exists('mobile_layout_inject_html') && !defined('PHARMA_ERP_STANDALONE')) {
    ob_start(static function ($html) {
        try {
            return mobile_layout_inject_html($html);
        } catch (Throwable $e) {
            error_log('[mobile_layout] injection OB : ' . $e->getMessage());
            return $html;
        }
    });
}

// Initialisation terminée
?>
