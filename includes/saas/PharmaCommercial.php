<?php
/**
 * Parcours commercial PharmaPro — démo, activation automatique, contact public.
 */

require_once __DIR__ . '/PharmaSubscriptionPlan.php';
require_once __DIR__ . '/PlatformTenantFeatures.php';
require_once __DIR__ . '/TenantSchema.php';

class PharmaCommercial
{
    public static function brandName(): string
    {
        return 'PharmaPro ERP';
    }

    public static function brandTagline(): string
    {
        return 'ERP de gestion de pharmacie — moderne, intuitif, rentable';
    }

    public static function normalizeProductLine(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        if (in_array($value, ['pharma', 'pharma_erp', 'pharmapro', 'officine'], true)) {
            return PharmaSubscriptionPlan::PRODUCT_LINE;
        }
        return 'clinical';
    }

    public static function isPharmaProductLine(?string $productLine): bool
    {
        return self::normalizeProductLine($productLine) === PharmaSubscriptionPlan::PRODUCT_LINE;
    }

    public static function isPharmaOrder(array $order): bool
    {
        return self::isPharmaProductLine($order['product_line'] ?? 'clinical');
    }

    public static function landingUrl(): string
    {
        if (function_exists('public_url')) {
            return public_url('tarifs_pharma.php');
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return rtrim($base, '/') . '/tarifs_pharma.php';
    }

    public static function demoLoginUrl(): string
    {
        if (function_exists('public_url')) {
            return public_url('login.php?demo_try=1&product=pharma&redirect=pharma_erp/');
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return rtrim($base, '/') . '/login.php?demo_try=1&product=pharma&redirect=pharma_erp/';
    }

    public static function subscribeUrl(string $planSlug): string
    {
        if (function_exists('public_url')) {
            return public_url('subscribe.php?product=pharma&plan=' . urlencode($planSlug));
        }
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        return rtrim($base, '/') . '/subscribe.php?product=pharma&plan=' . urlencode($planSlug);
    }

    public static function qrCodeImageUrl(?string $targetUrl = null): string
    {
        $target = $targetUrl ?: self::demoLoginUrl();
        return 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&margin=8&data='
            . rawurlencode($target);
    }

    public static function contactEmail(): string
    {
        if (defined('PHARMA_CONTACT_EMAIL')) {
            return (string) PHARMA_CONTACT_EMAIL;
        }
        return defined('SITE_EMAIL') ? (string) SITE_EMAIL : 'contact@secogesarl.com';
    }

    /** @return list<string> */
    public static function contactPhones(): array
    {
        if (defined('PHARMA_CONTACT_PHONES') && is_array(PHARMA_CONTACT_PHONES)) {
            return PHARMA_CONTACT_PHONES;
        }
        return ['+223 00 00 00 00'];
    }

    public static function contactWebsite(): string
    {
        if (defined('PHARMA_CONTACT_WEBSITE')) {
            return (string) PHARMA_CONTACT_WEBSITE;
        }
        if (defined('PLATFORM_VENDOR_URL')) {
            return (string) PLATFORM_VENDOR_URL;
        }
        return 'https://www.secogesarl.com';
    }

    public static function activateSuiteForTenant(int $tenantId, ?int $enabledBy = null, ?string $notes = null): void
    {
        PlatformTenantFeatures::setEnabled(
            $tenantId,
            PlatformTenantFeatures::PHARMA_ERP_SUITE,
            true,
            $enabledBy,
            $notes ?: 'Activation PharmaPro ERP'
        );
    }

    public static function syncTenantPlan(int $tenantId, string $planSlug, ?PDO $pdo = null): void
    {
        $planSlug = PharmaSubscriptionPlan::normalizeSlug($planSlug);
        $plan = PharmaSubscriptionPlan::get($planSlug);
        $pdo = $pdo ?? getDB();
        $pdo->prepare('UPDATE tenants SET license_type = ?, max_users = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$planSlug, (int) $plan['max_users'], $tenantId]);
    }

    /**
     * Tenant démo dédié PharmaPro — 15 jours, module ERP pré-activé.
     *
     * @return array{tenant_id: int, tenant_key: string, username: string, password: string}
     */
    public static function ensurePharmaDemoTenant(): array
    {
        TenantSchema::ensure();
        $tenantKey = 'DEMO-PHARMA';
        $pdo = getDB();

        $tenant = $pdo->query(
            "SELECT * FROM tenants WHERE tenant_key = " . $pdo->quote($tenantKey) . " LIMIT 1"
        )->fetch(PDO::FETCH_ASSOC);

        if (!$tenant) {
            $expires = date('Y-m-d', strtotime('+15 days'));
            $stmt = $pdo->prepare("
                INSERT INTO tenants (tenant_key, company_name, email, license_type, max_users, expires_at, status, is_demo)
                VALUES (?, 'Démo PharmaPro ERP', 'demo-pharma@efficasante.local', 'starter', 5, ?, 'active', 1)
            ");
            $stmt->execute([$tenantKey, $expires]);
            $tenantId = (int) $pdo->lastInsertId();
        } else {
            $tenantId = (int) $tenant['id'];
            $expires = date('Y-m-d', strtotime('+15 days'));
            $pdo->prepare("UPDATE tenants SET expires_at = ?, status = 'active', is_demo = 1 WHERE id = ?")
                ->execute([$expires, $tenantId]);
        }

        self::activateSuiteForTenant($tenantId, null, 'Tenant démo PharmaPro');

        $username = 'pharmademo';
        $password = 'demo123';
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $user = $pdo->prepare('SELECT id FROM utilisateurs WHERE nom_utilisateur = ? AND tenant_id = ? LIMIT 1');
        $user->execute([$username, $tenantId]);
        if (!$user->fetch()) {
            $pdo->prepare("
                INSERT INTO utilisateurs (tenant_id, nom_utilisateur, email, mot_de_passe, role, statut)
                VALUES (?, ?, 'demo-pharma@efficasante.local', ?, 'pharmacien', 'actif')
            ")->execute([$tenantId, $username, $hash]);
        } else {
            $pdo->prepare("
                UPDATE utilisateurs SET mot_de_passe = ?, statut = 'actif', role = 'pharmacien'
                WHERE nom_utilisateur = ? AND tenant_id = ?
            ")->execute([$hash, $username, $tenantId]);
        }

        return [
            'tenant_id' => $tenantId,
            'tenant_key' => $tenantKey,
            'username' => $username,
            'password' => $password,
        ];
    }
}
