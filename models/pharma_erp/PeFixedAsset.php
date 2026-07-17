<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeFixedAsset
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 25, string $status = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $this->peScope($pdo, 'pe_fixed_assets', $where, $params);
        $sql = 'SELECT * FROM pe_fixed_assets WHERE ' . implode(' AND ', $where) . ' ORDER BY acquisition_date DESC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCount(string $status = ''): int
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $this->peScope($pdo, 'pe_fixed_assets', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_fixed_assets WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            throw new RuntimeException('Tenant requis.');
        }

        $code = trim((string) ($data['asset_code'] ?? ''));
        if ($code === '') {
            $code = 'IMMO-' . strtoupper(substr(uniqid(), -6));
        }

        $cost = round((float) ($data['acquisition_cost'] ?? 0), 2);
        $residual = round((float) ($data['residual_value'] ?? 0), 2);
        $months = max(1, (int) ($data['useful_life_months'] ?? 60));

        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO pe_fixed_assets (
                tenant_id, pharmacy_id, asset_code, label, category, acquisition_date,
                acquisition_cost, useful_life_months, residual_value, net_book_value, notes, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $tenantId,
            !empty($data['pharmacy_id']) ? (int) $data['pharmacy_id'] : null,
            $code,
            $data['label'],
            $data['category'] ?? null,
            $data['acquisition_date'] ?? date('Y-m-d'),
            $cost,
            $months,
            $residual,
            $cost,
            $data['notes'] ?? null,
        ]);
        $id = (int) $pdo->lastInsertId();
        $this->peAudit('create', 'pe_fixed_assets', $id);
        return $id;
    }

    public function findById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_fixed_assets', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_fixed_assets WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function runDepreciation(int $id): bool
    {
        $asset = $this->findById($id);
        if (!$asset || ($asset['status'] ?? '') !== 'active') {
            return false;
        }

        $cost = (float) $asset['acquisition_cost'];
        $residual = (float) $asset['residual_value'];
        $months = max(1, (int) $asset['useful_life_months']);
        $monthly = round(max(0, ($cost - $residual) / $months), 2);
        $accumulated = round((float) $asset['accumulated_depreciation'] + $monthly, 2);
        $net = max($residual, round($cost - $accumulated, 2));

        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE pe_fixed_assets SET accumulated_depreciation = ?, net_book_value = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        return $stmt->execute([$accumulated, $net, $id, $this->peTenantId()]);
    }
}
