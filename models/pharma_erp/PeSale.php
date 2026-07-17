<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';
require_once __DIR__ . '/PeStock.php';

class PeSale
{
    use PeModelTrait;

    /**
     * @param list<array{product_id: int, quantity: int, unit_price?: float}> $lines
     * @param array<string, mixed> $payment
     */
    public function createSale(
        int $pharmacyId,
        int $registerId,
        int $depositId,
        array $lines,
        array $payment,
        ?string $customerName = null
    ): array {
        $tenantId = $this->peTenantId();
        if (!$tenantId || empty($lines)) {
            throw new InvalidArgumentException('Vente invalide.');
        }

        $pdo = getDB();
        $stockModel = new PeStock();
        $saleNumber = $this->nextDocumentNumber($pdo, $pharmacyId, 'sale');

        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            $vatTotal = 0.0;
            $profitTotal = 0.0;
            $preparedLines = [];

            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $qty = max(1, (int) $line['quantity']);

                $stmt = $pdo->prepare('SELECT * FROM pe_products WHERE id = ? AND tenant_id = ? LIMIT 1');
                $stmt->execute([$productId, $tenantId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    throw new RuntimeException('Produit introuvable.');
                }

                $unitPrice = (float) $product['sale_price'];
                if (!empty($line['unit_price']) && class_exists('Auth')) {
                    require_once __DIR__ . '/../../config/Auth.php';
                    if (Auth::getInstance()->aUnRole(['admin'])) {
                        $unitPrice = (float) $line['unit_price'];
                    }
                }
                $vatRate = (float) $product['vat_rate'];
                $lineTotal = round($unitPrice * $qty, 2);
                $unitCost = (float) $product['purchase_price'];
                $lineProfit = round(($unitPrice - $unitCost) * $qty, 2);

                $subtotal += $lineTotal;
                $vatTotal += round($lineTotal * $vatRate / 100, 2);
                $profitTotal += $lineProfit;

                $preparedLines[] = [
                    'product' => $product,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'unit_cost' => $unitCost,
                    'vat_rate' => $vatRate,
                    'line_total' => $lineTotal,
                    'line_profit' => $lineProfit,
                ];
            }

            $discount = (float) ($payment['discount'] ?? 0);
            if (!empty($payment['promo_code'])) {
                require_once __DIR__ . '/PePromotion.php';
                $promoModel = new PePromotion();
                $promo = $promoModel->findByCode((string) $payment['promo_code']);
                if ($promo) {
                    $discount += $promoModel->calculateDiscount($promo, $subtotal);
                }
            }
            $totalTtc = max(0, round($subtotal + $vatTotal - $discount, 2));
            $amountPaid = (float) ($payment['amount'] ?? $totalTtc);
            $change = max(0, round($amountPaid - $totalTtc, 2));

            $stmt = $pdo->prepare("
                INSERT INTO pe_sales (
                    tenant_id, pharmacy_id, cash_register_id, sale_number,
                    customer_name, subtotal_ht, discount_amount, vat_amount, total_ttc,
                    amount_paid, change_amount, profit_amount, status, created_by, completed_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            $stmt->execute([
                $tenantId, $pharmacyId, $registerId, $saleNumber,
                $customerName, $subtotal, $discount, $vatTotal, $totalTtc,
                $amountPaid, $change, $profitTotal, $_SESSION['user_id'] ?? null,
            ]);
            $saleId = (int) $pdo->lastInsertId();

            foreach ($preparedLines as $pl) {
                $stockModel->stockOut(
                    (int) $pl['product']['id'],
                    $depositId,
                    $pl['qty'],
                    $pharmacyId,
                    'sale',
                    $saleId
                );

                $stmt = $pdo->prepare("
                    INSERT INTO pe_sale_lines (
                        tenant_id, sale_id, product_id, product_name, quantity,
                        unit_price, unit_cost, vat_rate, line_total, line_profit
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $tenantId, $saleId, $pl['product']['id'], $pl['product']['name'], $pl['qty'],
                    $pl['unit_price'], $pl['unit_cost'], $pl['vat_rate'], $pl['line_total'], $pl['line_profit'],
                ]);
            }

            $method = $payment['method'] ?? 'cash';
            $stmt = $pdo->prepare("
                INSERT INTO pe_sale_payments (tenant_id, sale_id, payment_method, amount, reference, provider)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId, $saleId, $method, $amountPaid,
                $payment['reference'] ?? null, $payment['provider'] ?? null,
            ]);

            require_once __DIR__ . '/PeAccountingEngine.php';
            PeAccountingEngine::postSale($saleId);

            if (!empty($payment['loyalty_phone'])) {
                require_once __DIR__ . '/PePromotion.php';
                $points = (int) floor($totalTtc / 1000);
                if ($points > 0) {
                    (new PePromotion())->addLoyaltyPoints(
                        (string) $payment['loyalty_phone'],
                        $points,
                        $customerName,
                        $saleId
                    );
                }
            }

            $pdo->commit();
            $this->peAudit('sale_completed', 'pe_sales', $saleId, ['number' => $saleNumber, 'total' => $totalTtc]);

            return [
                'id' => $saleId,
                'sale_number' => $saleNumber,
                'total_ttc' => $totalTtc,
                'change_amount' => $change,
            ];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed> */
    public function getDashboardStats(int $days = 30): array
    {
        $pdo = getDB();
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return [];
        }

        $stats = [];

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pe_products WHERE tenant_id = ? AND deleted_at IS NULL AND status = 'active'");
        $stmt->execute([$tenantId]);
        $stats['products_active'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_ttc), 0), COALESCE(SUM(profit_amount), 0), COUNT(*)
            FROM pe_sales WHERE tenant_id = ? AND status = 'completed' AND DATE(completed_at) = CURDATE()
        ");
        $stmt->execute([$tenantId]);
        $todayRow = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0, 0];
        $stats['sales_today'] = (float) $todayRow[0];
        $stats['profit_today'] = (float) $todayRow[1];
        $stats['transactions_today'] = (int) $todayRow[2];

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_ttc), 0), COALESCE(SUM(profit_amount), 0), COUNT(*)
            FROM pe_sales WHERE tenant_id = ? AND status = 'completed'
            AND completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$tenantId, $days]);
        $periodRow = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0, 0];
        $stats['sales_period'] = (float) $periodRow[0];
        $stats['profit_period'] = (float) $periodRow[1];
        $stats['transactions_period'] = (int) $periodRow[2];

        $stockModel = new PeStock();
        $stats['stock_value'] = $stockModel->getStockValue();

        $productModel = new PeProduct();
        $stats['low_stock'] = count($productModel->getLowStock(100));
        $stats['expiry_alerts'] = count($stockModel->getExpiryAlerts(90, 100));

        return $stats;
    }

    /** @return list<array<string, mixed>> */
    public function getRecentSales(int $limit = 10): array
    {
        $pdo = getDB();
        $where = ["s.status = 'completed'"];
        $params = [];
        $this->peScope($pdo, 'pe_sales', $where, $params, 's');

        $sql = "
            SELECT s.*, cr.name AS register_name
            FROM pe_sales s
            LEFT JOIN pe_cash_registers cr ON cr.id = s.cash_register_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.completed_at DESC
            LIMIT {$limit}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array{date: string, total: float, profit: float}> */
    public function getSalesTrend(int $days = 14): array
    {
        $pdo = getDB();
        $where = ["status = 'completed'", 'completed_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)'];
        $params = [$days - 1];
        $this->peScope($pdo, 'pe_sales', $where, $params);

        $sql = "
            SELECT DATE(completed_at) AS sale_date,
                   COALESCE(SUM(total_ttc), 0) AS total,
                   COALESCE(SUM(profit_amount), 0) AS profit
            FROM pe_sales
            WHERE " . implode(' AND ', $where) . "
            GROUP BY DATE(completed_at)
            ORDER BY sale_date ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn($r) => [
            'date' => $r['sale_date'],
            'total' => (float) $r['total'],
            'profit' => (float) $r['profit'],
        ], $rows);
    }

    private function nextDocumentNumber(PDO $pdo, int $pharmacyId, string $type): string
    {
        $tenantId = $this->peTenantId();
        $stmt = $pdo->prepare("
            SELECT id, prefix, next_number, pad_length FROM pe_document_sequences
            WHERE tenant_id = ? AND pharmacy_id = ? AND document_type = ?
            LIMIT 1 FOR UPDATE
        ");
        $stmt->execute([$tenantId, $pharmacyId, $type]);
        $seq = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$seq) {
            $pdo->prepare("
                INSERT INTO pe_document_sequences (tenant_id, pharmacy_id, document_type, prefix, next_number)
                VALUES (?, ?, ?, 'V', 1)
            ")->execute([$tenantId, $pharmacyId, $type]);
            $prefix = 'V';
            $num = 1;
            $pad = 6;
        } else {
            $prefix = $seq['prefix'];
            $num = (int) $seq['next_number'];
            $pad = (int) $seq['pad_length'];
            $pdo->prepare('UPDATE pe_document_sequences SET next_number = next_number + 1 WHERE id = ?')
                ->execute([$seq['id']]);
        }

        return $prefix . str_pad((string) $num, $pad, '0', STR_PAD_LEFT);
    }

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 25, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ["s.status = 'completed'"];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(s.sale_number LIKE ? OR s.customer_name LIKE ?)';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_sales', $where, $params, 's');

        $sql = "
            SELECT s.*, cr.name AS register_name
            FROM pe_sales s
            LEFT JOIN pe_cash_registers cr ON cr.id = s.cash_register_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY s.completed_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCount(string $search = ''): int
    {
        $pdo = getDB();
        $where = ["status = 'completed'"];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(sale_number LIKE ? OR customer_name LIKE ?)';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_sales', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_sales WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['s.id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_sales', $where, $params, 's');
        $sql = "
            SELECT s.*, cr.name AS register_name
            FROM pe_sales s
            LEFT JOIN pe_cash_registers cr ON cr.id = s.cash_register_id
            WHERE " . implode(' AND ', $where) . " LIMIT 1
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT * FROM pe_sale_lines WHERE sale_id = ? AND tenant_id = ? ORDER BY id ASC');
        $stmt->execute([$id, $this->peTenantId()]);
        $sale['lines'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmt = $pdo->prepare('SELECT * FROM pe_sale_payments WHERE sale_id = ? AND tenant_id = ?');
        $stmt->execute([$id, $this->peTenantId()]);
        $sale['payments'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $sale;
    }
}
