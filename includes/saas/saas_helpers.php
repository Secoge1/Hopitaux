<?php
/**
 * Helpers SaaS — paiement, URLs, sanitization.
 */

if (!function_exists('saas_sanitize')) {
    function saas_sanitize(?string $value): string
    {
        return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('saas_get_payment_number')) {
    function saas_get_payment_number(): string
    {
        return defined('PAYMENT_MOBILE_NUMBER') ? PAYMENT_MOBILE_NUMBER : '+223 94 03 54 56';
    }
}

if (!function_exists('saas_get_payment_methods')) {
    function saas_get_payment_methods(): string
    {
        return defined('PAYMENT_MOBILE_METHODS') ? PAYMENT_MOBILE_METHODS : 'Orange Money, Wave';
    }
}

if (!function_exists('saas_payment_instructions_url')) {
    function saas_payment_instructions_url(string $ref): string
    {
        $path = 'payment_instructions.php?ref=' . urlencode($ref);
        if (function_exists('public_url')) {
            return public_url($path);
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return rtrim($base, '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('saas_subscribe_url')) {
    function saas_subscribe_url(string $plan = 'annual'): string
    {
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return $base . '/subscribe.php?plan=' . urlencode($plan);
    }
}

if (!function_exists('saas_format_amount')) {
    function saas_format_amount(int $amount): string
    {
        return number_format($amount, 0, ',', ' ') . ' FCFA';
    }
}

if (!function_exists('saas_is_whitelisted_page')) {
    function saas_is_whitelisted_page(): bool
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        if (strpos($script, '/admin_platform/') !== false) {
            return true;
        }
        $whitelist = [
            'renew.php', 'subscribe.php', 'tarifs.php', 'payment_instructions.php',
            'login.php', 'home.php', 'admin_tenants.php', 'logout.php',
            'migrate_saas_multitenant.php',
        ];
        return in_array(basename($script), $whitelist, true);
    }
}

if (!function_exists('saas_is_platform_admin')) {
    function saas_is_platform_admin(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION['is_platform_admin'])) {
            return true;
        }
        $allowed = defined('PLATFORM_ADMIN_USERNAMES') ? PLATFORM_ADMIN_USERNAMES : [];
        if (!is_array($allowed) || $allowed === []) {
            return false;
        }
        $username = $_SESSION['user_name'] ?? '';
        return in_array($username, $allowed, true);
    }
}

if (!function_exists('saas_require_platform_admin')) {
    function saas_require_platform_admin(): void
    {
        $auth = Auth::getInstance();
        $auth->requireAuth();
        if (!saas_is_platform_admin()) {
            $_SESSION['flash_message'] = 'Accès réservé à l\'administration plateforme Efficasante.';
            $_SESSION['flash_type'] = 'error';
            $base = defined('BASE_PATH') ? BASE_PATH : '';
            header('Location: ' . $base . '/dashboard.php');
            exit;
        }
    }
}

if (!function_exists('saas_require_tenant_context')) {
    /**
     * Bloque l'accès si un utilisateur métier est connecté sans tenant_id en session.
     */
    function saas_require_tenant_context(): void
    {
        if (saas_is_whitelisted_page()) {
            return;
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_connected'])) {
            return;
        }
        if (saas_is_platform_admin()) {
            return;
        }
        if (!empty($_SESSION['tenant_id'])) {
            return;
        }
        $_SESSION['flash_message'] = 'Votre compte n\'est associé à aucun établissement. Contactez le support.';
        $_SESSION['flash_type'] = 'error';
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/logout.php');
        exit;
    }
}

if (!function_exists('saas_parametre_valeur')) {
    /**
     * Lit un paramètre système (global puis surcharge tenant si session active).
     */
    function saas_parametre_valeur(string $cle, ?string $default = null): ?string
    {
        if (!function_exists('getDB')) {
            require_once __DIR__ . '/../../config/db.php';
        }
        try {
            $pdo = getDB();
            $tenantId = null;
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            if (!empty($_SESSION['tenant_id'])) {
                $tenantId = (int) $_SESSION['tenant_id'];
            }

            $hasTenantCol = false;
            $chk = $pdo->prepare(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'parametres_systeme' AND COLUMN_NAME = 'tenant_id'"
            );
            $chk->execute();
            $hasTenantCol = (bool) $chk->fetchColumn();

            if ($hasTenantCol && $tenantId) {
                $stmt = $pdo->prepare(
                    "SELECT valeur FROM parametres_systeme
                     WHERE cle = ? AND (tenant_id = ? OR tenant_id IS NULL)
                     ORDER BY tenant_id DESC LIMIT 1"
                );
                $stmt->execute([$cle, $tenantId]);
            } elseif ($hasTenantCol) {
                $stmt = $pdo->prepare(
                    "SELECT valeur FROM parametres_systeme WHERE cle = ? AND tenant_id IS NULL LIMIT 1"
                );
                $stmt->execute([$cle]);
            } else {
                $stmt = $pdo->prepare(
                    "SELECT valeur FROM parametres_systeme WHERE cle = ? LIMIT 1"
                );
                $stmt->execute([$cle]);
            }

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valeur', $row) && $row['valeur'] !== null && $row['valeur'] !== '') {
                return (string) $row['valeur'];
            }
        } catch (Throwable $e) {
            error_log('saas_parametre_valeur: ' . $e->getMessage());
        }

        return $default;
    }
}

if (!function_exists('saas_parametre_timezone')) {
    function saas_parametre_timezone(): string
    {
        return saas_parametre_valeur('timezone', 'Africa/Bamako') ?: 'Africa/Bamako';
    }
}

if (!function_exists('tenant_feature_enabled')) {
    /**
     * Fonctionnalité activée par l'admin plateforme pour l'établissement courant.
     */
    function tenant_feature_enabled(string $featureKey, ?int $tenantId = null): bool
    {
        require_once __DIR__ . '/PlatformTenantFeatures.php';
        return PlatformTenantFeatures::isEnabled($featureKey, $tenantId);
    }
}

if (!function_exists('payment_finance_sync_enabled')) {
    function payment_finance_sync_enabled(?int $tenantId = null): bool
    {
        require_once __DIR__ . '/PlatformTenantFeatures.php';
        return tenant_feature_enabled(PlatformTenantFeatures::PAYMENT_FINANCE_SYNC, $tenantId);
    }
}
