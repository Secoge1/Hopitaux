<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';
require_once __DIR__ . '/PeStock.php';
require_once __DIR__ . '/PePharmacy.php';

class PeInventory
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 20): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        $this->peScope($pdo, 'pe_inventories', $where, $params, 'i');

        $sql = "
            SELECT i.*, d.name AS deposit_name
            FROM pe_inventories i
            LEFT JOIN pe_deposits d ON d.id = i.deposit_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY i.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(int $depositId, ?int $pharmacyId = null)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }
        $pharmacyModel = new PePharmacy();
        $pharmacy = $pharmacyId ? ['id' => $pharmacyId] : $pharmacyModel->getDefault();
        if (!$pharmacy) {
            return false;
        }

        $pdo = getDB();
        $number = 'INV-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pe_inventories (tenant_id, pharmacy_id, deposit_id, inventory_number, status, started_at, created_by)
                VALUES (?, ?, ?, ?, 'counting', NOW(), ?)
            ");
            $stmt->execute([
                $tenantId,
                (int) $pharmacy['id'],
                $depositId,
                $number,
                $_SESSION['user_id'] ?? null,
            ]);
            $inventoryId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("
                SELECT sl.product_id, sl.lot_id, COALESCE(SUM(sl.quantity), 0) AS system_qty
                FROM pe_stock_levels sl
                WHERE sl.tenant_id = ? AND sl.deposit_id = ?
                GROUP BY sl.product_id, sl.lot_id
            ");
            $stmt->execute([$tenantId, $depositId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $lineStmt = $pdo->prepare("
                INSERT INTO pe_inventory_lines (tenant_id, inventory_id, product_id, lot_id, system_qty, counted_qty)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($rows as $row) {
                $sysQty = (int) $row['system_qty'];
                if ($sysQty <= 0) {
                    continue;
                }
                $lineStmt->execute([
                    $tenantId,
                    $inventoryId,
                    (int) $row['product_id'],
                    $row['lot_id'] ? (int) $row['lot_id'] : null,
                    $sysQty,
                    $sysQty,
                ]);
            }

            $pdo->commit();
            $this->peAudit('create', 'pe_inventories', $inventoryId);
            return $inventoryId;
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
        $where = ['i.id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_inventories', $where, $params, 'i');
        $sql = "
            SELECT i.*, d.name AS deposit_name
            FROM pe_inventories i
            LEFT JOIN pe_deposits d ON d.id = i.deposit_id
            WHERE " . implode(' AND ', $where) . " LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function getLines(int $inventoryId): array
    {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT l.*, p.name AS product_name, p.sku
            FROM pe_inventory_lines l
            INNER JOIN pe_products p ON p.id = l.product_id
            WHERE l.inventory_id = ? AND l.tenant_id = ?
            ORDER BY p.name ASC
        ");
        $stmt->execute([$inventoryId, $this->peTenantId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateLineCount(int $lineId, int $countedQty): bool
    {
        $pdo = getDB();
        $stmt = $pdo->prepare('UPDATE pe_inventory_lines SET counted_qty = ? WHERE id = ? AND tenant_id = ?');
        return $stmt->execute([max(0, $countedQty), $lineId, $this->peTenantId()]);
    }

    public function validate(int $inventoryId): bool
    {
        $inv = $this->findById($inventoryId);
        if (!$inv || $inv['status'] !== 'counting') {
            return false;
        }

        $pdo = getDB();
        $tenantId = $this->peTenantId();
        $stockModel = new PeStock();
        $lines = $this->getLines($inventoryId);

        $pdo->beginTransaction();
        try {
            foreach ($lines as $line) {
                $variance = (int) $line['counted_qty'] - (int) $line['system_qty'];
                if ($variance === 0) {
                    continue;
                }
                $productId = (int) $line['product_id'];
                $depositId = (int) $inv['deposit_id'];
                $pharmacyId = (int) $inv['pharmacy_id'];
                if ($variance > 0) {
                    $stockModel->stockIn([
                        'product_id' => $productId,
                        'deposit_id' => $depositId,
                        'quantity' => $variance,
                        'pharmacy_id' => $pharmacyId,
                        'reference_type' => 'inventory',
                        'reference_id' => $inventoryId,
                    ]);
                } else {
                    $lotId = !empty($line['lot_id']) ? (int) $line['lot_id'] : null;
                    $stockModel->stockOut($productId, $depositId, abs($variance), $pharmacyId, 'inventory', $inventoryId, $lotId);
                }
            }

            $stmt = $pdo->prepare("
                UPDATE pe_inventories SET status = 'validated', validated_at = NOW(), validated_by = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'] ?? null, $inventoryId, $tenantId]);
            $pdo->commit();
            $this->peAudit('validate', 'pe_inventories', $inventoryId);
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
