<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeSupplier
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 20, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(company_name LIKE ? OR code LIKE ? OR contact_name LIKE ?)';
            array_push($params, $term, $term, $term);
        }

        $this->peScope($pdo, 'pe_suppliers', $where, $params);
        $sql = 'SELECT * FROM pe_suppliers WHERE ' . implode(' AND ', $where) . ' ORDER BY company_name ASC LIMIT ? OFFSET ?';
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
            $where[] = '(company_name LIKE ? OR code LIKE ?)';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_suppliers', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_suppliers WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $data */
    public function create(array $data)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }

        $code = trim((string) ($data['code'] ?? ''));
        if ($code === '') {
            $code = 'FRN-' . strtoupper(substr(uniqid(), -6));
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO pe_suppliers (tenant_id, code, company_name, contact_name, phone, email, address, payment_terms_days, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $ok = $stmt->execute([
            $tenantId, $code, $data['company_name'], $data['contact_name'] ?? null,
            $data['phone'] ?? null, $data['email'] ?? null, $data['address'] ?? null,
            (int) ($data['payment_terms_days'] ?? 30),
        ]);

        if (!$ok) {
            return false;
        }
        $id = (int) $pdo->lastInsertId();
        $this->peAudit('create', 'pe_suppliers', $id);
        return $id;
    }

    public function findById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?', 'deleted_at IS NULL'];
        $params = [$id];
        $this->peScope($pdo, 'pe_suppliers', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_suppliers WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
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
            UPDATE pe_suppliers SET
                company_name = ?, contact_name = ?, phone = ?, email = ?, address = ?,
                payment_terms_days = ?, status = ?
            WHERE id = ? AND tenant_id = ?
        ");
        $ok = $stmt->execute([
            $data['company_name'],
            $data['contact_name'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            $data['address'] ?? null,
            (int) ($data['payment_terms_days'] ?? 30),
            $data['status'] ?? 'active',
            $id,
            $this->peTenantId(),
        ]);
        if ($ok) {
            $this->peAudit('update', 'pe_suppliers', $id);
        }
        return $ok;
    }
}
