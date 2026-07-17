<?php
/**
 * Détection d'environnement (local, clinique, PharmaPro production).
 */

if (!function_exists('app_http_host')) {
    function app_http_host(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        return preg_replace('/:\d+$/', '', $host) ?: 'localhost';
    }
}

if (!function_exists('app_is_pharma_production_host')) {
    function app_is_pharma_production_host(): bool
    {
        if (defined('APP_PHARMA_HOST')) {
            return (bool) APP_PHARMA_HOST;
        }

        $host = app_http_host();
        $known = [
            'pharma.secogesarl.com',
            'pharmasmart.secogesarl.com', // alias / ancien nom
        ];

        return in_array($host, $known, true);
    }
}

if (!function_exists('app_load_environment')) {
    function app_load_environment(): void
    {
        static $loaded = false;
        if ($loaded) {
            return;
        }
        $loaded = true;

        if (!app_is_pharma_production_host()) {
            return;
        }

        $secrets = __DIR__ . '/db.pharma.production.php';
        if (is_file($secrets)) {
            require_once $secrets;
        }

        $profile = __DIR__ . '/pharma.production.php';
        if (is_file($profile)) {
            require_once $profile;
        }
    }
}

if (!function_exists('app_pharma_public_entry_url')) {
    /** Page d'accueil publique sur le domaine PharmaPro. */
    function app_pharma_public_entry_url(): string
    {
        if (function_exists('public_url')) {
            return public_url('tarifs_pharma.php');
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return rtrim($base, '/') . '/tarifs_pharma.php';
    }
}

if (!function_exists('app_pharma_app_entry_url')) {
    /** Entrée applicative après connexion sur le domaine PharmaPro. */
    function app_pharma_app_entry_url(): string
    {
        if (function_exists('pharma_erp_url')) {
            return pharma_erp_url();
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return rtrim($base, '/') . '/pharma_erp/';
    }
}
