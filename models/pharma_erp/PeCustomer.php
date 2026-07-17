<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeCustomer
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 25, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR company_name LIKE ? OR phone LIKE ? OR code LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term);
        }
        $this->peScope($pdo, 'pe_customers', $where, $params);
        $sql = 'SELECT * FROM pe_customers WHERE ' . implode(' AND ', $where) . ' ORDER BY last_name ASC, first_name ASC LIMIT ? OFFSET ?';
        $params[] = $limit;
        $params[] = $offset;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCount(string $search = ''): int
    {
        $pdo = getDB();
        $where = ['deleted_at IS NULL'];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(first_name LIKE ? OR last_name LIKE ? OR phone LIKE ? OR code LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        $this->peScope($pdo, 'pe_customers', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_customers WHERE ' . implode(' AND ', $where));
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

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = 'CLI-' . strtoupper(substr(uniqid(), -6));
        }

        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pe_customers (
                    tenant_id, code, first_name, last_name, company_name, phone, email,
                    address, city, birth_date, notes, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $tenantId, $code,
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
                $data['company_name'] ?? null,
                $data['phone'] ?? null,
                $data['email'] ?? null,
                $data['address'] ?? null,
                $data['city'] ?? null,
                $data['birth_date'] ?? null,
                $data['notes'] ?? null,
            ]);
            $customerId = (int) $pdo->lastInsertId();

            $phone = trim((string) ($data['phone'] ?? ''));
            if ($phone !== '') {
                $this->syncLoyaltyAccount($pdo, $customerId, $phone, $this->displayName($data));
            }

            $pdo->commit();
            $this->peAudit('create', 'pe_customers', $customerId);
            return $customerId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function findById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?', 'deleted_at IS NULL'];
        $params = [$id];
        $this->peScope($pdo, 'pe_customers', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_customers WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByPhone(string $phone): ?array
    {
        $phone = trim($phone);
        if ($phone === '') {
            return null;
        }
        $pdo = getDB();
        $where = ['phone = ?', 'deleted_at IS NULL', "status = 'active'"];
        $params = [$phone];
        $this->peScope($pdo, 'pe_customers', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_customers WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if (!$this->findById($id)) {
            return false;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE pe_customers SET
                first_name = ?, last_name = ?, company_name = ?, phone = ?, email = ?,
                address = ?, city = ?, birth_date = ?, notes = ?, status = ?, updated_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ");
        $ok = $stmt->execute([
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['company_name'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['birth_date'] ?? null,
            $data['notes'] ?? null,
            $data['status'] ?? 'active',
            $id, $this->peTenantId(),
        ]);
        if ($ok && !empty($data['phone'])) {
            $this->syncLoyaltyAccount($pdo, $id, (string) $data['phone'], $this->displayName($data));
        }
        return $ok;
    }

    /** @param array<string, mixed> $data */
    public function displayName(array $data): string
    {
        if (!empty($data['company_name'])) {
            return (string) $data['company_name'];
        }
        return trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')) ?: 'Client';
    }

    private function syncLoyaltyAccount(PDO $pdo, int $customerId, string $phone, string $name): void
    {
        $tenantId = $this->peTenantId();
        $stmt = $pdo->prepare('SELECT id FROM pe_loyalty_accounts WHERE tenant_id = ? AND customer_phone = ? LIMIT 1');
        $stmt->execute([$tenantId, $phone]);
        $loyaltyId = $stmt->fetchColumn();
        if (!$loyaltyId) {
            $pdo->prepare("
                INSERT INTO pe_loyalty_accounts (tenant_id, customer_phone, customer_name)
                VALUES (?, ?, ?)
            ")->execute([$tenantId, $phone, $name]);
            $loyaltyId = (int) $pdo->lastInsertId();
        } else {
            $pdo->prepare('UPDATE pe_loyalty_accounts SET customer_name = ? WHERE id = ?')
                ->execute([$name, $loyaltyId]);
        }
        $pdo->prepare('UPDATE pe_customers SET loyalty_account_id = ? WHERE id = ? AND tenant_id = ?')
            ->execute([(int) $loyaltyId, $customerId, $tenantId]);
    }
}
