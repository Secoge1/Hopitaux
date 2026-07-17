<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PePharmacy
{
    use PeModelTrait;

    public function getDefault(): ?array
    {
        $pdo = getDB();
        $where = ['deleted_at IS NULL'];
        $params = [];
        $this->peScope($pdo, 'pe_pharmacies', $where, $params);
        $sql = 'SELECT * FROM pe_pharmacies WHERE ' . implode(' AND ', $where)
            . ' ORDER BY is_default DESC, id ASC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function getAll(): array
    {
        $pdo = getDB();
        $where = ['deleted_at IS NULL'];
        $params = [];
        $this->peScope($pdo, 'pe_pharmacies', $where, $params);
        $sql = 'SELECT * FROM pe_pharmacies WHERE ' . implode(' AND ', $where) . ' ORDER BY name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDefaultDepositId(int $pharmacyId): ?int
    {
        $pdo = getDB();
        $where = ['pharmacy_id = ?', 'deleted_at IS NULL', 'status = \'active\''];
        $params = [$pharmacyId];
        $this->peScope($pdo, 'pe_deposits', $where, $params);
        $sql = 'SELECT id FROM pe_deposits WHERE ' . implode(' AND ', $where)
            . ' ORDER BY is_default DESC, id ASC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** @return list<array<string, mixed>> */
    public function getDeposits(?int $pharmacyId = null): array
    {
        $pdo = getDB();
        $where = ['deleted_at IS NULL', "status = 'active'"];
        $params = [];
        if ($pharmacyId) {
            $where[] = 'pharmacy_id = ?';
            $params[] = $pharmacyId;
        }
        $this->peScope($pdo, 'pe_deposits', $where, $params);
        $sql = 'SELECT * FROM pe_deposits WHERE ' . implode(' AND ', $where)
            . ' ORDER BY is_default DESC, name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getDefaultRegisterId(int $pharmacyId): ?int
    {
        $pdo = getDB();
        $where = ['pharmacy_id = ?', 'deleted_at IS NULL'];
        $params = [$pharmacyId];
        $this->peScope($pdo, 'pe_cash_registers', $where, $params);
        $sql = 'SELECT id FROM pe_cash_registers WHERE ' . implode(' AND ', $where)
            . ' ORDER BY is_default DESC, id ASC LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            throw new RuntimeException('Tenant requis.');
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = 'OFF-' . strtoupper(substr(uniqid(), -5));
        }

        $isDefault = !empty($data['is_default']) ? 1 : 0;
        $pdo = getDB();
        if ($isDefault) {
            $pdo->prepare('UPDATE pe_pharmacies SET is_default = 0 WHERE tenant_id = ?')->execute([$tenantId]);
        }

        $stmt = $pdo->prepare("
            INSERT INTO pe_pharmacies (tenant_id, code, name, address, city, phone, email, is_default, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $tenantId, $code, $data['name'], $data['address'] ?? null, $data['city'] ?? null,
            $data['phone'] ?? null, $data['email'] ?? null, $isDefault,
        ]);
        $pharmacyId = (int) $pdo->lastInsertId();

        $pdo->prepare("
            INSERT INTO pe_deposits (tenant_id, pharmacy_id, code, name, is_default, status)
            VALUES (?, ?, 'DEP-01', 'Dépôt principal', 1, 'active')
        ")->execute([$tenantId, $pharmacyId]);

        $pdo->prepare("
            INSERT INTO pe_cash_registers (tenant_id, pharmacy_id, code, name, is_default, status)
            VALUES (?, ?, 'CAISSE-01', 'Caisse principale', 1, 'active')
        ")->execute([$tenantId, $pharmacyId]);

        $this->peAudit('create', 'pe_pharmacies', $pharmacyId);
        return $pharmacyId;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $pdo = getDB();
        if (!empty($data['is_default'])) {
            $pdo->prepare('UPDATE pe_pharmacies SET is_default = 0 WHERE tenant_id = ?')->execute([$this->peTenantId()]);
        }

        $stmt = $pdo->prepare("
            UPDATE pe_pharmacies SET
                name = ?, address = ?, city = ?, phone = ?, email = ?,
                is_default = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        return $stmt->execute([
            $data['name'] ?? $existing['name'],
            $data['address'] ?? $existing['address'],
            $data['city'] ?? $existing['city'],
            $data['phone'] ?? $existing['phone'],
            $data['email'] ?? $existing['email'],
            !empty($data['is_default']) ? 1 : (int) ($existing['is_default'] ?? 0),
            $data['status'] ?? $existing['status'] ?? 'active',
            $id, $this->peTenantId(),
        ]);
    }

    public function findById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?', 'deleted_at IS NULL'];
        $params = [$id];
        $this->peScope($pdo, 'pe_pharmacies', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_pharmacies WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
