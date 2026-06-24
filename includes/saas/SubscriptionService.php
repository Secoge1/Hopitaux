<?php
/**
 * Service d'abonnement runtime — validation licence, quotas, garde pages.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/SubscriptionPlan.php';
require_once __DIR__ . '/TenantSchema.php';
require_once __DIR__ . '/TenantContext.php';

class SubscriptionService
{
    private static ?self $instance = null;

    private ?int $tenantId = null;
    private ?array $tenantRow = null;
    private ?string $planSlug = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function ensureSchema(): void
    {
        TenantSchema::ensure();
    }

    public function bindTenant(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenantRow = null;
        $this->planSlug = null;
    }

    public function loadForSession(): void
    {
        TenantContext::bindFromSession();
        $tenantId = TenantContext::getTenantId();
        if ($tenantId) {
            $this->bindTenant($tenantId);
        }
    }

    public function getTenantId(): ?int
    {
        return $this->tenantId;
    }

    public function getTenantRow(): ?array
    {
        if ($this->tenantRow !== null || !$this->tenantId) {
            return $this->tenantRow;
        }
        $this->ensureSchema();
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM tenants WHERE id = ?');
        $stmt->execute([$this->tenantId]);
        $this->tenantRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $this->tenantRow;
    }

    public function getPlanSlug(): string
    {
        if ($this->planSlug !== null) {
            return $this->planSlug;
        }
        $row = $this->getTenantRow();
        if (!$row) {
            $this->planSlug = SubscriptionPlan::ANNUAL;
            return $this->planSlug;
        }
        $this->planSlug = SubscriptionPlan::normalizeSlug($row['license_type'] ?? SubscriptionPlan::ANNUAL);
        return $this->planSlug;
    }

    public function getPlan(): array
    {
        return SubscriptionPlan::get($this->getPlanSlug());
    }

    public function isLifetime(): bool
    {
        return SubscriptionPlan::isLifetime($this->getPlanSlug());
    }

    public function isDemoTenant(): bool
    {
        $row = $this->getTenantRow();
        if (!$row) {
            return false;
        }
        if (!empty($row['is_demo'])) {
            return true;
        }
        $key = strtoupper($row['tenant_key'] ?? '');
        return strpos($key, 'DEMO-') === 0;
    }

    public function hasModule(string $moduleKey): bool
    {
        return SubscriptionPlan::hasModule($this->getPlanSlug(), $moduleKey);
    }

    public function getMaxUsers(): int
    {
        $plan = $this->getPlan();
        $row = $this->getTenantRow();
        if ($row && isset($row['max_users']) && (int) $row['max_users'] > 0) {
            return (int) $row['max_users'];
        }
        return (int) $plan['max_users'];
    }

    public function checkUserLimit(): bool
    {
        if (!$this->tenantId) {
            return false;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) AS c FROM utilisateurs WHERE tenant_id = ? AND statut = 'actif'"
        );
        $stmt->execute([$this->tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ((int) ($row['c'] ?? 0)) < $this->getMaxUsers();
    }

    public function syncTenantToPlan(int $tenantId, string $planSlug, ?PDO $pdo = null): void
    {
        $planSlug = SubscriptionPlan::normalizeSlug($planSlug);
        $plan = SubscriptionPlan::get($planSlug);

        $pdo = $pdo ?? getDB();
        $stmt = $pdo->prepare(
            'UPDATE tenants SET license_type = ?, max_users = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$planSlug, $plan['max_users'], $tenantId]);

        if ($this->tenantId === $tenantId) {
            $this->tenantRow = null;
            $this->planSlug = null;
        }
    }

    /**
     * Vérifie si le tenant est actif et non expiré.
     * @return array{valid: bool, message: string}
     */
    public function checkTenantStatus(): array
    {
        $row = $this->getTenantRow();
        if (!$row) {
            return ['valid' => true, 'message' => ''];
        }
        if (($row['status'] ?? '') !== 'active') {
            return ['valid' => false, 'message' => 'Votre licence n\'est plus active. Contactez le support ou renouvelez.'];
        }
        if ($this->isLifetime()) {
            return ['valid' => true, 'message' => ''];
        }
        if (!empty($row['expires_at']) && strtotime($row['expires_at']) < strtotime('today')) {
            $plan = $this->getPlan();
            $renewal = (int) ($plan['renewal_price_xof'] ?? $plan['price_xof']);
            return ['valid' => false, 'message' => 'Votre abonnement a expiré. Renouvelez pour continuer (' . SubscriptionPlan::formatPrice($renewal) . '/an).'];
        }
        return ['valid' => true, 'message' => ''];
    }

    public function requireActiveTenant(bool $redirect = true): void
    {
        $check = $this->checkTenantStatus();
        if ($check['valid']) {
            return;
        }
        if (!$redirect) {
            throw new RuntimeException($check['message']);
        }
        $_SESSION['flash_message'] = $check['message'];
        $_SESSION['flash_type'] = 'error';
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/renew.php');
        exit;
    }

    public function guardCurrentPage(): void
    {
        require_once __DIR__ . '/saas_helpers.php';
        if (saas_is_whitelisted_page()) {
            return;
        }
        $page = basename($_SERVER['PHP_SELF'] ?? '');
        $map = SubscriptionPlan::getPageModuleMap();
        if (isset($map[$page])) {
            $this->requireModule($map[$page]);
        }
    }

    public function requireModule(string $moduleKey, bool $jsonResponse = false): void
    {
        if ($this->hasModule($moduleKey)) {
            return;
        }
        $message = SubscriptionPlan::getModuleLabel($moduleKey) . ' n\'est pas inclus dans votre licence.';
        if ($jsonResponse) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'warning';
        $base = defined('BASE_PATH') ? BASE_PATH : '';
        header('Location: ' . $base . '/tarifs.php');
        exit;
    }

    /**
     * Crée une licence démo 15 jours (abonnement annuel).
     */
    public function ensureDemoTenant(): array
    {
        $this->ensureSchema();
        $tenantKey = 'DEMO-ANNUAL';
        $pdo = getDB();

        $tenant = $pdo->query("SELECT * FROM tenants WHERE tenant_key = '{$tenantKey}' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

        if (!$tenant) {
            $expires = date('Y-m-d', strtotime('+15 days'));
            $stmt = $pdo->prepare("
                INSERT INTO tenants (tenant_key, company_name, email, license_type, max_users, expires_at, status, is_demo)
                VALUES (?, 'Démo Efficasante', 'demo@efficasante.local', 'starter', 5, ?, 'active', 1)
            ");
            $stmt->execute([$tenantKey, $expires]);
            $tenantId = (int) $pdo->lastInsertId();
        } else {
            $tenantId = (int) $tenant['id'];
            $expires = date('Y-m-d', strtotime('+15 days'));
            $pdo->prepare("UPDATE tenants SET expires_at = ?, status = 'active', is_demo = 1 WHERE id = ?")
                ->execute([$expires, $tenantId]);
        }

        $username = 'demo';
        $user = $pdo->prepare('SELECT id FROM utilisateurs WHERE nom_utilisateur = ? AND tenant_id = ? LIMIT 1');
        $user->execute([$username, $tenantId]);
        if (!$user->fetch()) {
            $hash = password_hash('demo123', PASSWORD_DEFAULT);
            $pdo->prepare("
                INSERT INTO utilisateurs (tenant_id, nom_utilisateur, email, mot_de_passe, role, statut)
                VALUES (?, ?, 'demo@efficasante.local', ?, 'admin', 'actif')
            ")->execute([$tenantId, $username, $hash]);
        }

        return [
            'tenant_id' => $tenantId,
            'tenant_key' => $tenantKey,
            'username' => $username,
            'password' => 'demo123',
        ];
    }
}
