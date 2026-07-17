<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeProduct
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 20, string $search = '', string $status = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $term = '%' . preg_replace('/\s+/', ' ', trim($search)) . '%';
            $where[] = '(p.name LIKE ? OR p.generic_name LIKE ? OR p.sku LIKE ? OR p.barcode_primary LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        } else {
            $where[] = "p.status != 'discontinued'";
        }

        $this->peScope($pdo, 'pe_products', $where, $params, 'p');

        $sql = "
            SELECT p.*,
                   c.name AS category_name,
                   l.name AS laboratory_name,
                   COALESCE(SUM(sl.quantity), 0) AS stock_total
            FROM pe_products p
            LEFT JOIN pe_product_categories c ON c.id = p.category_id
            LEFT JOIN pe_laboratories l ON l.id = p.laboratory_id
            LEFT JOIN pe_stock_levels sl ON sl.product_id = p.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.id
            ORDER BY p.name ASC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCount(string $search = '', string $status = ''): int
    {
        $pdo = getDB();
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($search !== '') {
            $term = '%' . preg_replace('/\s+/', ' ', trim($search)) . '%';
            $where[] = '(name LIKE ? OR generic_name LIKE ? OR sku LIKE ? OR barcode_primary LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        $this->peScope($pdo, 'pe_products', $where, $params);
        $sql = 'SELECT COUNT(*) FROM pe_products WHERE ' . implode(' AND ', $where);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['id = ?', 'deleted_at IS NULL'];
        $params = [$id];
        $this->peScope($pdo, 'pe_products', $where, $params);
        $sql = 'SELECT * FROM pe_products WHERE ' . implode(' AND ', $where) . ' LIMIT 1';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function findDetail(int $id): ?array
    {
        $product = $this->findById($id);
        if (!$product) {
            return null;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare('
            SELECT COALESCE(SUM(quantity), 0) AS stock_total,
                   COALESCE(SUM(available_qty), 0) AS stock_available
            FROM pe_stock_levels
            WHERE product_id = ? AND tenant_id = ?
        ');
        $stmt->execute([$id, $this->peTenantId()]);
        $stock = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['stock_total' => 0, 'stock_available' => 0];
        $product['stock_total'] = (int) ($stock['stock_total'] ?? 0);
        $product['stock_available'] = (int) ($stock['stock_available'] ?? 0);
        $product['barcodes'] = $this->getBarcodes($id);

        return $product;
    }

    /** @return list<array{barcode: string, is_primary: int}> */
    public function getBarcodes(int $productId): array
    {
        $pdo = getDB();
        $where = ['product_id = ?'];
        $params = [$productId];
        $this->peScope($pdo, 'pe_product_barcodes', $where, $params);
        $sql = 'SELECT barcode, is_primary FROM pe_product_barcodes WHERE '
            . implode(' AND ', $where)
            . ' ORDER BY is_primary DESC, barcode ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByBarcode(string $barcode): ?array
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        $pdo = getDB();
        $where = ['(p.barcode_primary = ? OR b.barcode = ?)', 'p.deleted_at IS NULL', "p.status = 'active'"];
        $params = [$barcode, $barcode];
        $this->peScope($pdo, 'pe_products', $where, $params, 'p');

        $sql = "
            SELECT p.*, COALESCE(SUM(sl.available_qty), 0) AS stock_available
            FROM pe_products p
            LEFT JOIN pe_product_barcodes b ON b.product_id = p.id
            LEFT JOIN pe_stock_levels sl ON sl.product_id = p.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.id
            LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }

        $pdo = getDB();
        $sku = trim((string) ($data['sku'] ?? ''));
        if ($sku === '') {
            $sku = $this->generateSku();
        }

        $stmt = $pdo->prepare("
            INSERT INTO pe_products (
                tenant_id, category_id, laboratory_id, sku, barcode_primary,
                name, generic_name, dci, dosage_form, strength, unit_label, pack_size,
                purchase_price, sale_price, wholesale_price, vat_rate,
                reorder_level, reorder_qty, requires_prescription, is_controlled, is_refrigerated, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $ok = $stmt->execute([
            $tenantId,
            $data['category_id'] ?: null,
            $data['laboratory_id'] ?: null,
            $sku,
            $data['barcode_primary'] ?: null,
            $data['name'],
            $data['generic_name'] ?: null,
            $data['dci'] ?: null,
            $data['dosage_form'] ?? 'tablet',
            $data['strength'] ?: null,
            $data['unit_label'] ?? 'unité',
            (int) ($data['pack_size'] ?? 1),
            (float) ($data['purchase_price'] ?? 0),
            (float) ($data['sale_price'] ?? 0),
            (float) ($data['wholesale_price'] ?? 0),
            (float) ($data['vat_rate'] ?? 0),
            (int) ($data['reorder_level'] ?? 0),
            (int) ($data['reorder_qty'] ?? 0),
            !empty($data['requires_prescription']) ? 1 : 0,
            !empty($data['is_controlled']) ? 1 : 0,
            !empty($data['is_refrigerated']) ? 1 : 0,
            $data['status'] ?? 'active',
        ]);

        if (!$ok) {
            return false;
        }

        $id = (int) $pdo->lastInsertId();
        if (!empty($data['barcode_primary'])) {
            $this->addBarcode($id, $data['barcode_primary'], true);
        }
        $this->peAudit('create', 'pe_products', $id, ['sku' => $sku]);
        return $id;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $existing = $this->findById($id);
        if (!$existing) {
            return false;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare("
            UPDATE pe_products SET
                category_id = ?, laboratory_id = ?, barcode_primary = ?,
                name = ?, generic_name = ?, dci = ?, dosage_form = ?, strength = ?,
                unit_label = ?, pack_size = ?, purchase_price = ?, sale_price = ?,
                wholesale_price = ?, vat_rate = ?, reorder_level = ?, reorder_qty = ?,
                requires_prescription = ?, is_controlled = ?, is_refrigerated = ?, status = ?
            WHERE id = ? AND tenant_id = ?
        ");

        $ok = $stmt->execute([
            $data['category_id'] ?: null,
            $data['laboratory_id'] ?: null,
            $data['barcode_primary'] ?: null,
            $data['name'],
            $data['generic_name'] ?: null,
            $data['dci'] ?: null,
            $data['dosage_form'] ?? 'tablet',
            $data['strength'] ?: null,
            $data['unit_label'] ?? 'unité',
            (int) ($data['pack_size'] ?? 1),
            (float) ($data['purchase_price'] ?? 0),
            (float) ($data['sale_price'] ?? 0),
            (float) ($data['wholesale_price'] ?? 0),
            (float) ($data['vat_rate'] ?? 0),
            (int) ($data['reorder_level'] ?? 0),
            (int) ($data['reorder_qty'] ?? 0),
            !empty($data['requires_prescription']) ? 1 : 0,
            !empty($data['is_controlled']) ? 1 : 0,
            !empty($data['is_refrigerated']) ? 1 : 0,
            $data['status'] ?? 'active',
            $id,
            $this->peTenantId(),
        ]);

        if ($ok) {
            $this->peAudit('update', 'pe_products', $id);
        }
        return $ok;
    }

    public function addBarcode(int $productId, string $barcode, bool $isPrimary = false): bool
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId || trim($barcode) === '') {
            return false;
        }
        $barcode = trim($barcode);
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO pe_product_barcodes (tenant_id, product_id, barcode, barcode_type, is_primary)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE is_primary = VALUES(is_primary), barcode_type = VALUES(barcode_type)
        ");
        return $stmt->execute([
            $tenantId,
            $productId,
            $barcode,
            $this->detectBarcodeType($barcode),
            $isPrimary ? 1 : 0,
        ]);
    }

    public function findProductIdByBarcode(string $barcode, ?int $excludeProductId = null): ?int
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return null;
        }
        $pdo = getDB();

        $stmt = $pdo->prepare('SELECT id FROM pe_products WHERE tenant_id = ? AND barcode_primary = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$tenantId, $barcode]);
        $id = $stmt->fetchColumn();
        if ($id !== false) {
            $id = (int) $id;
            if ($excludeProductId === null || $id !== $excludeProductId) {
                return $id;
            }
        }

        $stmt = $pdo->prepare('SELECT product_id FROM pe_product_barcodes WHERE tenant_id = ? AND barcode = ? LIMIT 1');
        $stmt->execute([$tenantId, $barcode]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return null;
        }
        $id = (int) $id;
        if ($excludeProductId !== null && $id === $excludeProductId) {
            return null;
        }
        return $id;
    }

    public function setPrimaryBarcode(int $productId, string $barcode): bool
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw new InvalidArgumentException('Code-barres vide.');
        }
        if (!$this->findById($productId)) {
            throw new InvalidArgumentException('Produit introuvable.');
        }
        $otherId = $this->findProductIdByBarcode($barcode, $productId);
        if ($otherId !== null) {
            throw new RuntimeException('Ce code-barres est déjà utilisé par un autre produit.');
        }

        $tenantId = $this->peTenantId();
        $pdo = getDB();
        $ownTx = !$pdo->inTransaction();
        if ($ownTx) {
            $pdo->beginTransaction();
        }
        try {
            $stmt = $pdo->prepare('UPDATE pe_product_barcodes SET is_primary = 0 WHERE product_id = ? AND tenant_id = ?');
            $stmt->execute([$productId, $tenantId]);

            $stmt = $pdo->prepare('UPDATE pe_products SET barcode_primary = ? WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$barcode, $productId, $tenantId]);

            $this->addBarcode($productId, $barcode, true);
            if ($ownTx) {
                $pdo->commit();
            }
            $this->peAudit('update_barcode', 'pe_products', $productId, ['barcode_primary' => $barcode]);
            return true;
        } catch (Throwable $e) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public function addSecondaryBarcode(int $productId, string $barcode): bool
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw new InvalidArgumentException('Code-barres vide.');
        }
        if (!$this->findById($productId)) {
            throw new InvalidArgumentException('Produit introuvable.');
        }
        $otherId = $this->findProductIdByBarcode($barcode, $productId);
        if ($otherId !== null) {
            throw new RuntimeException('Ce code-barres est déjà utilisé par un autre produit.');
        }
        $ok = $this->addBarcode($productId, $barcode, false);
        if ($ok) {
            $this->peAudit('add_barcode', 'pe_products', $productId, ['barcode' => $barcode]);
        }
        return $ok;
    }

    public function removeBarcode(int $productId, string $barcode): bool
    {
        $barcode = trim($barcode);
        if ($barcode === '' || !$this->findById($productId)) {
            return false;
        }
        $tenantId = $this->peTenantId();
        $pdo = getDB();

        $product = $this->findById($productId);
        $wasPrimary = ($product['barcode_primary'] ?? '') === $barcode;

        $stmt = $pdo->prepare('DELETE FROM pe_product_barcodes WHERE tenant_id = ? AND product_id = ? AND barcode = ?');
        $stmt->execute([$tenantId, $productId, $barcode]);
        if ($stmt->rowCount() === 0) {
            return false;
        }

        if ($wasPrimary) {
            $stmt = $pdo->prepare('SELECT barcode FROM pe_product_barcodes WHERE tenant_id = ? AND product_id = ? ORDER BY is_primary DESC, id ASC LIMIT 1');
            $stmt->execute([$tenantId, $productId]);
            $next = $stmt->fetchColumn();
            $nextPrimary = $next !== false ? (string) $next : null;

            $stmt = $pdo->prepare('UPDATE pe_products SET barcode_primary = ? WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$nextPrimary, $productId, $tenantId]);

            if ($nextPrimary !== null) {
                $stmt = $pdo->prepare('UPDATE pe_product_barcodes SET is_primary = 1 WHERE tenant_id = ? AND product_id = ? AND barcode = ?');
                $stmt->execute([$tenantId, $productId, $nextPrimary]);
            }
        }

        $this->peAudit('remove_barcode', 'pe_products', $productId, ['barcode' => $barcode]);
        return true;
    }

    private function detectBarcodeType(string $barcode): string
    {
        if (preg_match('/^\d{13}$/', $barcode)) {
            return 'EAN13';
        }
        if (preg_match('/^\d{8}$/', $barcode)) {
            return 'EAN8';
        }
        if (preg_match('/^\d{12}$/', $barcode)) {
            return 'UPC';
        }
        return 'CODE128';
    }

    /** @return list<array<string, mixed>> */
    public function getLowStock(int $limit = 10): array
    {
        $pdo = getDB();
        $where = ['p.deleted_at IS NULL', "p.status = 'active'"];
        $params = [];
        $this->peScope($pdo, 'pe_products', $where, $params, 'p');

        $sql = "
            SELECT p.id, p.sku, p.name, p.reorder_level,
                   COALESCE(SUM(sl.quantity), 0) AS stock_total
            FROM pe_products p
            LEFT JOIN pe_stock_levels sl ON sl.product_id = p.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.id
            HAVING stock_total <= p.reorder_level AND p.reorder_level > 0
            ORDER BY stock_total ASC
            LIMIT {$limit}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function getTopSelling(int $limit = 5, int $days = 30): array
    {
        $pdo = getDB();
        $where = ["s.status = 'completed'", 's.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'];
        $params = [$days];
        $this->peScope($pdo, 'pe_sales', $where, $params, 's');

        $sql = "
            SELECT p.id, p.name, SUM(sl.quantity) AS qty_sold, SUM(sl.line_total) AS revenue
            FROM pe_sale_lines sl
            INNER JOIN pe_sales s ON s.id = sl.sale_id
            INNER JOIN pe_products p ON p.id = sl.product_id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.id, p.name
            ORDER BY qty_sold DESC
            LIMIT {$limit}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function generateSku(): string
    {
        return 'PRD-' . strtoupper(substr(uniqid(), -8));
    }
}
