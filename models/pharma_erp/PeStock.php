<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeStock
{
    use PeModelTrait;

    /**
     * Entrée stock avec lot (FEFO).
     *
     * @param array<string, mixed> $data
     */
    public function stockIn(array $data): bool
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }

        $pdo = getDB();
        if ($pdo->inTransaction()) {
            $this->applyStockIn($pdo, $data);
            return true;
        }

        $pdo->beginTransaction();
        try {
            $this->applyStockIn($pdo, $data);
            $pdo->commit();
            $this->peAudit('stock_in', 'pe_stock_movements', null, [
                'product_id' => (int) $data['product_id'],
                'qty' => (int) $data['quantity'],
            ]);
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $data */
    private function applyStockIn(PDO $pdo, array $data): void
    {
        $tenantId = $this->peTenantId();
        $productId = (int) $data['product_id'];
        $depositId = (int) $data['deposit_id'];
        $qty = (int) $data['quantity'];
        $lotNumber = trim((string) ($data['lot_number'] ?? 'DEFAULT'));
        $expiryDate = $data['expiry_date'] ?? date('Y-m-d', strtotime('+2 years'));
        $unitCost = (float) ($data['unit_cost'] ?? 0);
        $pharmacyId = (int) $data['pharmacy_id'];
        $refType = $data['reference_type'] ?? 'manual_in';

        $lotId = $this->findOrCreateLot($pdo, $tenantId, $productId, $depositId, $lotNumber, $expiryDate, $unitCost, $qty, $data['supplier_id'] ?? null);
        $this->adjustStockLevel($pdo, $tenantId, $productId, $depositId, $lotId, $qty);

        $stmt = $pdo->prepare("
            INSERT INTO pe_stock_movements (
                tenant_id, pharmacy_id, product_id, deposit_id, lot_id,
                movement_type, quantity, unit_cost, reference_type, reference_id, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, 'in', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenantId, $pharmacyId, $productId, $depositId, $lotId,
            $qty, $unitCost, $refType, $data['reference_id'] ?? null,
            $data['notes'] ?? null, $_SESSION['user_id'] ?? null,
        ]);
    }

    /**
     * Sortie stock (vente, ajustement).
     */
    public function stockOut(int $productId, int $depositId, int $qty, int $pharmacyId, string $refType, ?int $refId = null, ?int $lotId = null): bool
    {
        $pdo = getDB();
        if ($pdo->inTransaction()) {
            $this->applyStockOut($pdo, $productId, $depositId, $qty, $pharmacyId, $refType, $refId, $lotId);
            return true;
        }

        $pdo->beginTransaction();
        try {
            $this->applyStockOut($pdo, $productId, $depositId, $qty, $pharmacyId, $refType, $refId, $lotId);
            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    private function applyStockOut(
        PDO $pdo, int $productId, int $depositId, int $qty, int $pharmacyId,
        string $refType, ?int $refId = null, ?int $lotId = null
    ): void {
        $tenantId = $this->peTenantId();
        if (!$tenantId || $qty <= 0) {
            throw new RuntimeException('Sortie stock invalide.');
        }

        if ($lotId === null) {
            $lotId = $this->pickLotFefo($pdo, $productId, $depositId, $qty);
        }

        $this->adjustStockLevel($pdo, $tenantId, $productId, $depositId, $lotId, -$qty);
        $unitCost = $this->getLotCost($pdo, $lotId);

        $stmt = $pdo->prepare("
            INSERT INTO pe_stock_movements (
                tenant_id, pharmacy_id, product_id, deposit_id, lot_id,
                movement_type, quantity, unit_cost, reference_type, reference_id, created_by
            ) VALUES (?, ?, ?, ?, ?, 'sale', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $tenantId, $pharmacyId, $productId, $depositId, $lotId,
            -$qty, $unitCost, $refType, $refId, $_SESSION['user_id'] ?? null,
        ]);
    }

    /** @return list<array<string, mixed>> */
    public function getMovements(int $page = 1, int $limit = 30): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        $this->peScope($pdo, 'pe_stock_movements', $where, $params, 'm');

        $sql = "
            SELECT m.*, p.name AS product_name, p.sku, d.name AS deposit_name
            FROM pe_stock_movements m
            INNER JOIN pe_products p ON p.id = m.product_id
            INNER JOIN pe_deposits d ON d.id = m.deposit_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY m.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function getExpiryAlerts(int $days = 90, int $limit = 15): array
    {
        $pdo = getDB();
        $where = ["l.status = 'active'", 'l.current_qty > 0', 'l.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)'];
        $params = [$days];
        $this->peScope($pdo, 'pe_lots', $where, $params, 'l');

        $sql = "
            SELECT l.*, p.name AS product_name, p.sku, d.name AS deposit_name
            FROM pe_lots l
            INNER JOIN pe_products p ON p.id = l.product_id
            INNER JOIN pe_deposits d ON d.id = l.deposit_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY l.expiry_date ASC
            LIMIT {$limit}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStockValue(): float
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        $this->peScope($pdo, 'pe_stock_levels', $where, $params, 'sl');

        $sql = "
            SELECT COALESCE(SUM(sl.quantity * COALESCE(l.unit_cost, p.purchase_price, 0)), 0)
            FROM pe_stock_levels sl
            INNER JOIN pe_products p ON p.id = sl.product_id
            LEFT JOIN pe_lots l ON l.id = sl.lot_id
            WHERE " . implode(' AND ', $where) . "
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }

    private function findOrCreateLot(
        PDO $pdo, int $tenantId, int $productId, int $depositId,
        string $lotNumber, string $expiryDate, float $unitCost, int $qty, ?int $supplierId
    ): int {
        $stmt = $pdo->prepare("
            SELECT id, current_qty FROM pe_lots
            WHERE tenant_id = ? AND product_id = ? AND deposit_id = ? AND lot_number = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $productId, $depositId, $lotNumber]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            $stmt = $pdo->prepare('UPDATE pe_lots SET current_qty = current_qty + ?, unit_cost = ? WHERE id = ?');
            $stmt->execute([$qty, $unitCost, $existing['id']]);
            return (int) $existing['id'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO pe_lots (tenant_id, product_id, deposit_id, lot_number, expiry_date, supplier_id, unit_cost, initial_qty, current_qty)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenantId, $productId, $depositId, $lotNumber, $expiryDate, $supplierId, $unitCost, $qty, $qty]);
        return (int) $pdo->lastInsertId();
    }

    private function adjustStockLevel(PDO $pdo, int $tenantId, int $productId, int $depositId, ?int $lotId, int $delta): void
    {
        $stmt = $pdo->prepare("
            SELECT id, quantity FROM pe_stock_levels
            WHERE tenant_id = ? AND product_id = ? AND deposit_id = ? AND lot_id <=> ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $productId, $depositId, $lotId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $newQty = (int) $row['quantity'] + $delta;
            if ($newQty < 0) {
                throw new RuntimeException('Stock insuffisant.');
            }
            $stmt = $pdo->prepare('UPDATE pe_stock_levels SET quantity = ? WHERE id = ?');
            $stmt->execute([$newQty, $row['id']]);
        } else {
            if ($delta < 0) {
                throw new RuntimeException('Stock insuffisant.');
            }
            $stmt = $pdo->prepare("
                INSERT INTO pe_stock_levels (tenant_id, product_id, deposit_id, lot_id, quantity)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tenantId, $productId, $depositId, $lotId, $delta]);
        }

        if ($lotId) {
            $stmt = $pdo->prepare('UPDATE pe_lots SET current_qty = current_qty + ? WHERE id = ?');
            $stmt->execute([$delta, $lotId]);
        }
    }

    private function pickLotFefo(PDO $pdo, int $productId, int $depositId, int $qty): int
    {
        $stmt = $pdo->prepare("
            SELECT id, current_qty FROM pe_lots
            WHERE product_id = ? AND deposit_id = ? AND status = 'active' AND current_qty >= ?
            ORDER BY expiry_date ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$productId, $depositId, $qty]);
        $lot = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$lot) {
            throw new RuntimeException('Lot disponible insuffisant pour ce produit.');
        }
        return (int) $lot['id'];
    }

    private function getLotCost(PDO $pdo, int $lotId): float
    {
        $stmt = $pdo->prepare('SELECT unit_cost FROM pe_lots WHERE id = ?');
        $stmt->execute([$lotId]);
        return (float) ($stmt->fetchColumn() ?: 0);
    }
}
