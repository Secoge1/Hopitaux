<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PePromotion
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getActive(): array
    {
        $pdo = getDB();
        $where = ["status = 'active'", '(starts_at IS NULL OR starts_at <= NOW())', '(ends_at IS NULL OR ends_at >= NOW())'];
        $params = [];
        $this->peScope($pdo, 'pe_promotions', $where, $params);
        $sql = 'SELECT * FROM pe_promotions WHERE ' . implode(' AND ', $where) . ' ORDER BY name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function getAll(): array
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        $this->peScope($pdo, 'pe_promotions', $where, $params);
        $sql = 'SELECT * FROM pe_promotions WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCode(string $code): ?array
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }
        foreach ($this->getActive() as $promo) {
            if (strcasecmp($promo['code'], $code) === 0) {
                return $promo;
            }
        }
        return null;
    }

    public function calculateDiscount(array $promo, float $subtotal): float
    {
        if ($subtotal < (float) $promo['min_amount']) {
            return 0.0;
        }
        if ($promo['discount_type'] === 'fixed') {
            return min($subtotal, (float) $promo['discount_value']);
        }
        return round($subtotal * (float) $promo['discount_value'] / 100, 2);
    }

    /** @param array<string, mixed> $data */
    public function create(array $data)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }
        $pdo = getDB();
        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = 'PROMO-' . strtoupper(substr(uniqid(), -5));
        }
        $stmt = $pdo->prepare("
            INSERT INTO pe_promotions (tenant_id, code, name, discount_type, discount_value, min_amount, starts_at, ends_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ok = $stmt->execute([
            $tenantId,
            $code,
            $data['name'],
            $data['discount_type'] ?? 'percent',
            (float) ($data['discount_value'] ?? 0),
            (float) ($data['min_amount'] ?? 0),
            $data['starts_at'] ?: null,
            $data['ends_at'] ?: null,
            $data['status'] ?? 'active',
        ]);
        if (!$ok) {
            return false;
        }
        $id = (int) $pdo->lastInsertId();
        $this->peAudit('create', 'pe_promotions', $id);
        return $id;
    }

    public function addLoyaltyPoints(string $phone, int $points, ?string $name = null, ?int $saleId = null): void
    {
        $phone = trim($phone);
        if ($phone === '' || $points === 0) {
            return;
        }
        $tenantId = $this->peTenantId();
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT id FROM pe_loyalty_accounts WHERE tenant_id = ? AND customer_phone = ? LIMIT 1');
        $stmt->execute([$tenantId, $phone]);
        $accountId = $stmt->fetchColumn();
        if (!$accountId) {
            $pdo->prepare('INSERT INTO pe_loyalty_accounts (tenant_id, customer_phone, customer_name, points_balance, lifetime_points) VALUES (?, ?, ?, ?, ?)')
                ->execute([$tenantId, $phone, $name, max(0, $points), max(0, $points)]);
            $accountId = (int) $pdo->lastInsertId();
        } else {
            $accountId = (int) $accountId;
            $pdo->prepare('UPDATE pe_loyalty_accounts SET points_balance = points_balance + ?, lifetime_points = lifetime_points + ?, customer_name = COALESCE(?, customer_name) WHERE id = ?')
                ->execute([$points, max(0, $points), $name, $accountId]);
        }
        $pdo->prepare('INSERT INTO pe_loyalty_transactions (tenant_id, account_id, sale_id, points_delta, reason) VALUES (?, ?, ?, ?, ?)')
            ->execute([$tenantId, $accountId, $saleId, $points, 'Vente POS']);
    }
}
