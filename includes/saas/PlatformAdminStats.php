<?php
/**
 * Statistiques tableau de bord — administration plateforme SaaS.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/SubscriptionPlan.php';

class PlatformAdminStats
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDB();
    }

    /** @return array<string, mixed> */
    public function getDashboardStats(): array
    {
        $tenants = $this->pdo->query(
            "SELECT status, license_type, expires_at FROM tenants"
        )->fetchAll(PDO::FETCH_ASSOC);

        $total = count($tenants);
        $active = 0;
        $expiringSoon = 0;
        $annual = 0;
        $lifetime = 0;
        $today = new DateTime('today');
        $soonLimit = (clone $today)->modify('+30 days');

        foreach ($tenants as $t) {
            if (($t['status'] ?? '') === 'active') {
                $active++;
            }
            if (($t['license_type'] ?? '') === 'lifetime') {
                $lifetime++;
            } else {
                $annual++;
            }
            if (!empty($t['expires_at']) && ($t['status'] ?? '') === 'active') {
                try {
                    $exp = new DateTime($t['expires_at']);
                    if ($exp >= $today && $exp <= $soonLimit) {
                        $expiringSoon++;
                    }
                } catch (Exception $e) {
                    // ignore invalid dates
                }
            }
        }

        $pendingRow = $this->pdo->query(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount_xof), 0) AS total
             FROM subscription_orders WHERE payment_status = 'pending'"
        )->fetch(PDO::FETCH_ASSOC);

        $revenueMonth = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount_xof), 0) FROM subscription_orders
             WHERE payment_status = 'paid'
               AND paid_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
        )->fetchColumn();

        $revenueTotal = (int) $this->pdo->query(
            "SELECT COALESCE(SUM(amount_xof), 0) FROM subscription_orders WHERE payment_status = 'paid'"
        )->fetchColumn();

        $paidOrders = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM subscription_orders WHERE payment_status = 'paid'"
        )->fetchColumn();

        $invoicesCount = 0;
        if ($this->tableExists('subscription_invoices')) {
            $invoicesCount = (int) $this->pdo->query(
                "SELECT COUNT(*) FROM subscription_invoices WHERE status = 'issued'"
            )->fetchColumn();
        }

        return [
            'tenants_total' => $total,
            'tenants_active' => $active,
            'tenants_expiring_soon' => $expiringSoon,
            'tenants_annual' => $annual,
            'tenants_lifetime' => $lifetime,
            'pending_count' => (int) ($pendingRow['cnt'] ?? 0),
            'pending_amount' => (int) ($pendingRow['total'] ?? 0),
            'revenue_month' => $revenueMonth,
            'revenue_total' => $revenueTotal,
            'paid_orders' => $paidOrders,
            'invoices_count' => $invoicesCount,
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array<int, array> */
    public function getRecentPendingOrders(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM subscription_orders
             WHERE payment_status = 'pending'
             ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array> */
    public function getExpiringTenants(int $limit = 5): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.*,
                (SELECT COUNT(*) FROM utilisateurs u WHERE u.tenant_id = t.id AND u.statut = 'actif') AS users_count
             FROM tenants t
             WHERE t.status = 'active'
               AND t.expires_at IS NOT NULL
               AND t.expires_at BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
             ORDER BY t.expires_at ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array> */
    public function getRecentPaidOrders(int $limit = 8): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM subscription_orders
             WHERE payment_status = 'paid'
             ORDER BY paid_at DESC, updated_at DESC LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
