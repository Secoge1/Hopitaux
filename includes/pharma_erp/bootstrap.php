<?php
/**
 * Bootstrap PharmaPro ERP — module premium indépendant.
 * Nécessite : init.php (session, Auth, TenantContext).
 */

require_once __DIR__ . '/../saas/PlatformTenantFeatures.php';
require_once __DIR__ . '/../saas/TenantContext.php';
require_once __DIR__ . '/../app_urls.php';
require_once __DIR__ . '/PharmaErpSchema.php';

if (!function_exists('pharma_erp_feature_enabled')) {
    function pharma_erp_feature_enabled(?int $tenantId = null): bool
    {
        return PlatformTenantFeatures::isEnabled(
            PlatformTenantFeatures::PHARMA_ERP_SUITE,
            $tenantId
        );
    }
}

if (!function_exists('pharma_erp_require_feature')) {
    /**
     * Bloque l'accès si l'admin plateforme n'a pas activé PharmaPro pour le tenant.
     */
    function pharma_erp_require_feature(?int $tenantId = null): void
    {
        if (pharma_erp_feature_enabled($tenantId)) {
            PharmaErpSchema::ensure();
            PharmaErpSchema::provisionDefaultPharmacy($tenantId);
            require_once __DIR__ . '/../../models/pharma_erp/PeAccountingEngine.php';
            PeAccountingEngine::ensureSeed($tenantId);
            return;
        }

        if (!function_exists('redirectWithMessage')) {
            require_once __DIR__ . '/../flash_messages.php';
        }

        redirectWithMessage(
            app_url('login.php?redirect=pharma_erp/'),
            'PharmaPro ERP n\'est pas activé pour votre établissement. Contactez l\'administrateur plateforme.',
            'warning'
        );
    }
}

if (!function_exists('pharma_erp_allowed_roles')) {
    /** @return list<string> */
    function pharma_erp_allowed_roles(): array
    {
        return ['admin', 'pharmacien', 'comptable', 'pharma_manager', 'pharma_cashier'];
    }
}

if (!function_exists('pharma_erp_user_can_access')) {
    function pharma_erp_user_can_access(): bool
    {
        if (!class_exists('Auth')) {
            require_once __DIR__ . '/../../config/Auth.php';
        }
        $auth = Auth::getInstance();
        if (!$auth->estConnecte()) {
            return false;
        }
        return $auth->aUnRole(pharma_erp_allowed_roles());
    }
}

if (!function_exists('pharma_erp_post_login_url')) {
    /**
     * Destination après connexion — PharmaPro est un produit autonome quand activé.
     */
    function pharma_erp_post_login_url(?string $redirect = null): string
    {
        if ($redirect !== null && $redirect !== '' && !preg_match('#^https?://#i', $redirect)) {
            return app_url(ltrim($redirect, '/'));
        }

        if (!class_exists('Auth')) {
            require_once __DIR__ . '/../../config/Auth.php';
        }
        $auth = Auth::getInstance();

        if (function_exists('saas_is_platform_admin') && saas_is_platform_admin()) {
            if (!pharma_erp_user_can_access() || !pharma_erp_feature_enabled()) {
                return app_url('admin_platform/index.php');
            }
        }

        if (pharma_erp_user_can_access() && pharma_erp_feature_enabled()) {
            if (pharma_erp_user_is_pharma_primary()) {
                return pharma_erp_url();
            }
        }

        return app_url('dashboard.php');
    }
}

if (!function_exists('pharma_erp_user_is_pharma_primary')) {
    /** Utilisateur dédié officine (pas admin hospitalier global). */
    function pharma_erp_user_is_pharma_primary(): bool
    {
        if (!class_exists('Auth')) {
            require_once __DIR__ . '/../../config/Auth.php';
        }
        $auth = Auth::getInstance();
        if ($auth->aUnRole(['pharma_manager', 'pharma_cashier'])) {
            return true;
        }
        if ($auth->aUnRole(['pharmacien', 'comptable']) && !$auth->aUnRole(['admin'])) {
            return true;
        }
        return false;
    }
}

if (!function_exists('pharma_erp_require_role')) {
    function pharma_erp_require_role(): void
    {
        pharma_erp_require_feature();

        if (!class_exists('Auth')) {
            require_once __DIR__ . '/../../config/Auth.php';
        }
        $auth = Auth::getInstance();
        $auth->requireAuth(app_url('login.php?redirect=' . rawurlencode('pharma_erp/')));

        if (!$auth->aUnRole(pharma_erp_allowed_roles())) {
            if (!function_exists('redirectWithMessage')) {
                require_once __DIR__ . '/../flash_messages.php';
            }
            redirectWithMessage(
                app_url('login.php?redirect=pharma_erp/'),
                'Accès PharmaPro ERP refusé pour votre rôle.',
                'danger'
            );
        }
    }
}

if (!function_exists('pharma_erp_url')) {
    function pharma_erp_url(string $path = ''): string
    {
        $base = 'pharma_erp/';
        $path = ltrim($path, '/');
        return app_url($base . $path);
    }
}
