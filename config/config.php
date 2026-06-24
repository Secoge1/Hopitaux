<?php
/**
 * Configuration Principale du Système
 * Inclut automatiquement la configuration monétaire FCFA
 */

// Configuration de la base de données - seulement si pas déjà définie
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', 'cp2640311p29_efficasante');
}
if (!defined('DB_USER')) {
    define('DB_USER', 'root');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', '');
}

// Configuration de la devise (FORCÉE EN FCFA) - seulement si pas déjà définie
if (!defined('CURRENCY_CODE')) {
    define('CURRENCY_CODE', 'XOF');
}
if (!defined('CURRENCY_SYMBOL')) {
    define('CURRENCY_SYMBOL', 'FCFA');
}
if (!defined('CURRENCY_DECIMALS')) {
    define('CURRENCY_DECIMALS', 0);
}
if (!defined('CURRENCY_NAME')) {
    define('CURRENCY_NAME', 'Franc CFA');
}
if (!defined('CURRENCY_FORMAT')) {
    define('CURRENCY_FORMAT', '%s %s'); // Format: "FCFA 1000"
}

// Configuration du système - nom lu depuis la DB si disponible (sans bloquer si MySQL indisponible)
if (!defined('SITE_NAME')) {
    $_site_name = 'Clinique et Hôpital';
    try {
        if (!function_exists('getDBSoft') && is_file(__DIR__ . '/db.php')) {
            require_once __DIR__ . '/db.php';
        }
        if (function_exists('getDBSoft')) {
            $_cfg_pdo = getDBSoft();
            if ($_cfg_pdo instanceof PDO) {
                $_cfg_stmt = $_cfg_pdo->query("SELECT valeur FROM parametres_systeme WHERE cle = 'nom_etablissement' LIMIT 1");
                if ($_cfg_stmt) {
                    $_cfg_row = $_cfg_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($_cfg_row && !empty($_cfg_row['valeur'])) {
                        $_site_name = $_cfg_row['valeur'];
                    }
                }
                unset($_cfg_stmt, $_cfg_row);
            }
            unset($_cfg_pdo);
        }
    } catch (Exception $e) {
        // Utiliser la valeur par défaut
    }
    define('SITE_NAME', $_site_name);
    unset($_site_name);
}
define('SITE_URL', 'http://localhost/Hopitaux');
define('SITE_EMAIL', 'contact@secogesarl.com');

// Marque plateforme SaaS (pages publiques, login) — distincte du nom/logo de chaque tenant abonné
if (!defined('PLATFORM_NAME')) {
    define('PLATFORM_NAME', 'Se.Santé');
}
if (!defined('PLATFORM_TAGLINE')) {
    define('PLATFORM_TAGLINE', 'Votre système de santé unique');
}
if (!defined('PLATFORM_LOGO')) {
    define('PLATFORM_LOGO', 'assets/images/brand/sesante-logo.png');
}
if (!defined('PLATFORM_COMPANY')) {
    define('PLATFORM_COMPANY', 'Secogesarl');
}
if (!defined('PLATFORM_VENDOR_NAME')) {
    define('PLATFORM_VENDOR_NAME', 'Secoge');
}
if (!defined('PLATFORM_VENDOR_URL')) {
    define('PLATFORM_VENDOR_URL', 'https://www.secogesarl.com');
}
if (!defined('PLATFORM_ADMIN_USERNAMES')) {
    define('PLATFORM_ADMIN_USERNAMES', ['admin']);
}
if (!defined('PAYMENT_MOBILE_NUMBER')) {
    define('PAYMENT_MOBILE_NUMBER', '+223 94 03 54 56');
}
if (!defined('PAYMENT_MOBILE_METHODS')) {
    define('PAYMENT_MOBILE_METHODS', 'Orange Money, Wave');
}

// Configuration des chemins
define('ROOT_PATH', __DIR__ . '/..');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// Configuration des permissions
define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt']);

// Configuration de sécurité
define('SESSION_TIMEOUT', 3600); // 1 heure
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// Configuration des exports
define('PDF_MEMORY_LIMIT', '256M');
define('PDF_MAX_EXECUTION_TIME', 300);

// Configuration des emails
define('SMTP_HOST', 'localhost');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_ENCRYPTION', 'tls');

// Configuration des logs
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_FILE', LOGS_PATH . '/system.log');
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB

// Configuration des sessions
define('SESSION_NAME', 'CLINIQUE_SESSION');
define('SESSION_COOKIE_LIFETIME', 0);
define('SESSION_COOKIE_PATH', '/');
define('SESSION_COOKIE_DOMAIN', '');
if (!defined('SESSION_COOKIE_SECURE')) {
    $efficasanteHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && (string) $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
    );
    define('SESSION_COOKIE_SECURE', $efficasanteHttps);
}
define('SESSION_COOKIE_HTTPONLY', true);

// Configuration des cookies
define('COOKIE_LIFETIME', 86400 * 30); // 30 jours
define('COOKIE_PATH', '/');
define('COOKIE_DOMAIN', '');
if (!defined('COOKIE_SECURE')) {
    if (!isset($efficasanteHttps)) {
        $efficasanteHttps = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && (string) $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on')
        );
    }
    define('COOKIE_SECURE', $efficasanteHttps);
}
define('COOKIE_HTTPONLY', true);

// Configuration des uploads
define('UPLOAD_DIR', UPLOADS_PATH);
define('UPLOAD_TEMP_DIR', UPLOADS_PATH . '/temp');
define('UPLOAD_BACKUP_DIR', UPLOADS_PATH . '/backup');

// Configuration des rapports
define('REPORT_TEMPLATE_DIR', ROOT_PATH . '/templates/reports');
define('REPORT_OUTPUT_DIR', UPLOADS_PATH . '/reports');
define('REPORT_CACHE_DIR', ROOT_PATH . '/cache/reports');

// Configuration des notifications
define('NOTIFICATION_EMAIL', true);
define('NOTIFICATION_SMS', false);
define('NOTIFICATION_PUSH', false);

// Configuration des sauvegardes
define('BACKUP_ENABLED', true);
define('BACKUP_FREQUENCY', 'daily'); // daily, weekly, monthly
define('BACKUP_RETENTION', 30); // jours
define('BACKUP_DIR', ROOT_PATH . '/backups');

// Configuration des mises à jour
define('AUTO_UPDATE_ENABLED', false);
define('UPDATE_CHECK_FREQUENCY', 'weekly');
define('UPDATE_NOTIFICATION_EMAIL', true);

// Configuration des performances
define('CACHE_ENABLED', true);
define('CACHE_TTL', 3600); // 1 heure
define('CACHE_DIR', ROOT_PATH . '/cache');

// Configuration des erreurs
define('DISPLAY_ERRORS', false);
define('LOG_ERRORS', true);
define('ERROR_REPORTING', E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Configuration des timeouts
define('REQUEST_TIMEOUT', 30);
define('DATABASE_TIMEOUT', 10);
define('EXTERNAL_API_TIMEOUT', 15);

// Configuration des limites
define('MAX_RECORDS_PER_PAGE', 50);
define('MAX_EXPORT_RECORDS', 1000);
define('MAX_SEARCH_RESULTS', 100);

// Configuration des formats de date
define('DATE_FORMAT', 'd/m/Y');
define('DATETIME_FORMAT', 'd/m/Y H:i');
define('TIME_FORMAT', 'H:i');
define('TIMEZONE', 'Africa/Bamako');

// Configuration des langues
define('DEFAULT_LANGUAGE', 'fr');
define('AVAILABLE_LANGUAGES', ['fr', 'en']);
define('LANGUAGE_DIR', ROOT_PATH . '/languages');

// Configuration des thèmes
define('DEFAULT_THEME', 'default');
define('AVAILABLE_THEMES', ['default', 'dark', 'light']);
define('THEME_DIR', ROOT_PATH . '/themes');

// Configuration des modules
define('MODULES_ENABLED', [
    'patients' => true,
    'consultations' => true,
    'rendez_vous' => true,
    'paiements' => true,
    'laboratoire' => true,
    'utilisateurs' => true,
    'rapports' => true,
    'parametres' => true
]);

// Rôles et permissions — source unique : includes/roles.php
require_once __DIR__ . '/../includes/roles.php';
if (!defined('ROLES_AVAILABLE')) {
    define('ROLES_AVAILABLE', APP_ROLE_LABELS);
}
if (!defined('PERMISSIONS')) {
    define('PERMISSIONS', app_permissions_legacy_map());
}

// Inclure automatiquement la configuration monétaire FCFA
require_once __DIR__ . '/CurrencyConfig.php';

// Inclure le helper monétaire global seulement si les fonctions n'existent pas déjà
if (!function_exists('formatFCFA')) {
    require_once __DIR__ . '/../includes/currency_helper.php';
}

// Définir FCFA_ACTIVE si ce n'est pas déjà fait
if (!defined('FCFA_ACTIVE')) {
    define('FCFA_ACTIVE', true);
}

// Vérifier que FCFA est actif
if (!FCFA_ACTIVE) {
    die('ERREUR: Configuration monétaire FCFA non chargée !');
}

// Log de confirmation de la configuration
error_log('Configuration système chargée - Devise: ' . CURRENCY_SYMBOL . ' (' . CURRENCY_CODE . ')');

// Fonction de configuration automatique
function autoConfigureSystem() {
    // Créer les dossiers nécessaires
    $directories = [
        UPLOADS_PATH,
        UPLOADS_PATH . '/logos',
        UPLOADS_PATH . '/patients',
        UPLOADS_PATH . '/reports',
        LOGS_PATH,
        CACHE_DIR,
        BACKUP_DIR
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
    
    // Vérifier les permissions
    foreach ($directories as $dir) {
        if (!is_writable($dir)) {
            error_log("ATTENTION: Le dossier $dir n'est pas accessible en écriture");
        }
    }
}

// Exécuter la configuration automatique
autoConfigureSystem();
?>


