<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';
require_once __DIR__ . '/PeStock.php';
require_once __DIR__ . '/PeAccountingEngine.php';

class PePurchase
{
    use PeModelTrait;

    /** @param list<array{product_id: int, quantity: int, unit_cost?: float, vat_rate?: float}> $lines */
    public function createOrder(int $pharmacyId, int $supplierId, array $lines, ?string $notes = null): array
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId || empty($lines)) {
            throw new InvalidArgumentException('Commande invalide.');
        }

        $pdo = getDB();
        $orderNumber = $this->nextNumber($pdo, $pharmacyId, 'purchase_order', 'BC');

        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            $vatTotal = 0.0;
            $prepared = [];

            foreach ($lines as $line) {
                $productId = (int) $line['product_id'];
                $qty = max(1, (int) $line['quantity']);
                $stmt = $pdo->prepare('SELECT * FROM pe_products WHERE id = ? AND tenant_id = ? LIMIT 1');
                $stmt->execute([$productId, $tenantId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$product) {
                    throw new RuntimeException('Produit introuvable.');
                }
                $unitCost = (float) ($line['unit_cost'] ?? $product['purchase_price']);
                $vatRate = (float) ($line['vat_rate'] ?? $product['vat_rate']);
                $lineTotal = round($unitCost * $qty, 2);
                $subtotal += $lineTotal;
                $vatTotal += round($lineTotal * $vatRate / 100, 2);
                $prepared[] = compact('product', 'qty', 'unitCost', 'vatRate', 'lineTotal');
            }

            $totalTtc = round($subtotal + $vatTotal, 2);

            $stmt = $pdo->prepare("
                INSERT INTO pe_purchase_orders (
                    tenant_id, pharmacy_id, supplier_id, order_number, status, order_date,
                    subtotal_ht, vat_amount, total_ttc, notes, created_by
                ) VALUES (?, ?, ?, ?, 'ordered', CURDATE(), ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId, $pharmacyId, $supplierId, $orderNumber,
                $subtotal, $vatTotal, $totalTtc, $notes, $_SESSION['user_id'] ?? null,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            foreach ($prepared as $pl) {
                $stmt = $pdo->prepare("
                    INSERT INTO pe_purchase_order_lines (
                        tenant_id, purchase_order_id, product_id, product_name,
                        quantity_ordered, unit_cost, vat_rate, line_total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $tenantId, $orderId, $pl['product']['id'], $pl['product']['name'],
                    $pl['qty'], $pl['unitCost'], $pl['vatRate'], $pl['lineTotal'],
                ]);
            }

            $pdo->commit();
            $this->peAudit('po_created', 'pe_purchase_orders', $orderId, ['number' => $orderNumber]);

            return ['id' => $orderId, 'order_number' => $orderNumber, 'total_ttc' => $totalTtc];
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function findOrder(int $id): ?array
    {
        $pdo = getDB();
        $where = ['po.id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_purchase_orders', $where, $params, 'po');

        $stmt = $pdo->prepare("
            SELECT po.*, s.company_name AS supplier_name
            FROM pe_purchase_orders po
            INNER JOIN pe_suppliers s ON s.id = po.supplier_id
            WHERE " . implode(' AND ', $where) . " LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return list<array<string, mixed>> */
    public function getOrderLines(int $orderId): array
    {
        $pdo = getDB();
        $where = ['purchase_order_id = ?'];
        $params = [$orderId];
        $this->peScope($pdo, 'pe_purchase_order_lines', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_purchase_order_lines WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function getOrders(int $page = 1, int $limit = 20, string $status = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'po.status = ?';
            $params[] = $status;
        }
        $this->peScope($pdo, 'pe_purchase_orders', $where, $params, 'po');

        $sql = "
            SELECT po.*, s.company_name AS supplier_name
            FROM pe_purchase_orders po
            INNER JOIN pe_suppliers s ON s.id = po.supplier_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY po.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getOrdersCount(string $status = ''): int
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }
        $this->peScope($pdo, 'pe_purchase_orders', $where, $params);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_purchase_orders WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Réception marchandise — stock + facture fournisseur + écriture comptable.
     *
     * @param list<array{line_id: int, quantity: int, lot_number?: string, expiry_date?: string}> $receiptLines
     */
    public function receiveGoods(
        int $orderId,
        int $depositId,
        array $receiptLines,
        ?string $supplierInvoiceNumber = null
    ): array {
        $tenantId = $this->peTenantId();
        $order = $this->findOrder($orderId);
        if (!$order) {
            throw new RuntimeException('Commande introuvable.');
        }

        $pdo = getDB();
        $stockModel = new PeStock();
        $receiptNumber = $this->nextNumber($pdo, (int) $order['pharmacy_id'], 'goods_receipt', 'BR');
        $orderLines = $this->getOrderLines($orderId);
        $linesById = [];
        foreach ($orderLines as $ol) {
            $linesById[(int) $ol['id']] = $ol;
        }

        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            $vatTotal = 0.0;
            $validatedLines = [];

            foreach ($receiptLines as $rl) {
                $lineId = (int) $rl['line_id'];
                $qty = max(1, (int) $rl['quantity']);
                if (!isset($linesById[$lineId])) {
                    continue;
                }
                $ol = $linesById[$lineId];
                $remaining = (int) $ol['quantity_ordered'] - (int) $ol['quantity_received'];
                if ($qty > $remaining) {
                    throw new RuntimeException('Quantité reçue supérieure au reste à livrer pour ' . $ol['product_name']);
                }
                $lineTotal = round((float) $ol['unit_cost'] * $qty, 2);
                $vatLine = round($lineTotal * (float) $ol['vat_rate'] / 100, 2);
                $subtotal += $lineTotal;
                $vatTotal += $vatLine;
                $validatedLines[] = [
                    'order_line' => $ol,
                    'qty' => $qty,
                    'lot_number' => trim($rl['lot_number'] ?? '') ?: 'LOT-' . date('Ymd'),
                    'expiry_date' => $rl['expiry_date'] ?? date('Y-m-d', strtotime('+2 years')),
                    'line_total' => $lineTotal,
                    'vat_rate' => (float) $ol['vat_rate'],
                ];
            }

            if (empty($validatedLines)) {
                throw new InvalidArgumentException('Aucune ligne à réceptionner.');
            }

            $totalTtc = round($subtotal + $vatTotal, 2);

            $stmt = $pdo->prepare("
                INSERT INTO pe_goods_receipts (
                    tenant_id, pharmacy_id, deposit_id, supplier_id, purchase_order_id,
                    receipt_number, receipt_date, subtotal_ht, vat_amount, total_ttc,
                    status, validated_by, validated_at, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, 'validated', ?, NOW(), ?)
            ");
            $stmt->execute([
                $tenantId, $order['pharmacy_id'], $depositId, $order['supplier_id'], $orderId,
                $receiptNumber, $subtotal, $vatTotal, $totalTtc,
                $_SESSION['user_id'] ?? null, $_SESSION['user_id'] ?? null,
            ]);
            $receiptId = (int) $pdo->lastInsertId();

            foreach ($validatedLines as $vl) {
                $ol = $vl['order_line'];
                $stockModel->stockIn([
                    'pharmacy_id' => (int) $order['pharmacy_id'],
                    'deposit_id' => $depositId,
                    'product_id' => (int) $ol['product_id'],
                    'quantity' => $vl['qty'],
                    'unit_cost' => (float) $ol['unit_cost'],
                    'lot_number' => $vl['lot_number'],
                    'expiry_date' => $vl['expiry_date'],
                    'supplier_id' => (int) $order['supplier_id'],
                    'reference_type' => 'goods_receipt',
                    'reference_id' => $receiptId,
                    'notes' => 'Réception ' . $receiptNumber,
                ]);

                $stmt = $pdo->prepare("
                    INSERT INTO pe_goods_receipt_lines (
                        tenant_id, goods_receipt_id, product_id, lot_number, expiry_date,
                        quantity, unit_cost, vat_rate, line_total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $tenantId, $receiptId, $ol['product_id'], $vl['lot_number'], $vl['expiry_date'],
                    $vl['qty'], $ol['unit_cost'], $vl['vat_rate'], $vl['line_total'],
                ]);

                $stmt = $pdo->prepare('UPDATE pe_purchase_order_lines SET quantity_received = quantity_received + ? WHERE id = ?');
                $stmt->execute([$vl['qty'], $ol['id']]);
            }

            $this->updateOrderStatus($pdo, $orderId);

            $invNumber = $supplierInvoiceNumber ?: ('FF-' . $receiptNumber);
            $stmt = $pdo->prepare("
                INSERT INTO pe_supplier_invoices (
                    tenant_id, supplier_id, goods_receipt_id, invoice_number, invoice_date,
                    due_date, amount_ht, vat_amount, amount_ttc, status
                ) VALUES (?, ?, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $tenantId, $order['supplier_id'], $receiptId, $invNumber,
                $subtotal, $vatTotal, $totalTtc,
            ]);

            $stmt = $pdo->prepare('UPDATE pe_suppliers SET current_balance = current_balance + ? WHERE id = ? AND tenant_id = ?');
            $stmt->execute([$totalTtc, $order['supplier_id'], $tenantId]);

            $pdo->commit();

            PeAccountingEngine::postGoodsReceipt($receiptId);

            $this->peAudit('goods_received', 'pe_goods_receipts', $receiptId, ['number' => $receiptNumber]);

            return [
                'id' => $receiptId,
                'receipt_number' => $receiptNumber,
                'total_ttc' => $totalTtc,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    private function updateOrderStatus(PDO $pdo, int $orderId): void
    {
        $stmt = $pdo->prepare("
            SELECT SUM(quantity_ordered) AS ordered, SUM(quantity_received) AS received
            FROM pe_purchase_order_lines WHERE purchase_order_id = ?
        ");
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $status = 'partial';
        if ((int) $row['received'] >= (int) $row['ordered']) {
            $status = 'received';
        } elseif ((int) $row['received'] === 0) {
            $status = 'ordered';
        }
        $pdo->prepare('UPDATE pe_purchase_orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
    }

    private function nextNumber(PDO $pdo, int $pharmacyId, string $type, string $prefix): string
    {
        $tenantId = $this->peTenantId();
        $stmt = $pdo->prepare("
            SELECT id, next_number, pad_length FROM pe_document_sequences
            WHERE tenant_id = ? AND pharmacy_id = ? AND document_type = ? LIMIT 1 FOR UPDATE
        ");
        $stmt->execute([$tenantId, $pharmacyId, $type]);
        $seq = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$seq) {
            $pdo->prepare("
                INSERT INTO pe_document_sequences (tenant_id, pharmacy_id, document_type, prefix, next_number)
                VALUES (?, ?, ?, ?, 1)
            ")->execute([$tenantId, $pharmacyId, $type, $prefix]);
            $num = 1;
            $pad = 6;
        } else {
            $num = (int) $seq['next_number'];
            $pad = (int) $seq['pad_length'];
            $pdo->prepare('UPDATE pe_document_sequences SET next_number = next_number + 1 WHERE id = ?')->execute([$seq['id']]);
        }
        return $prefix . str_pad((string) $num, $pad, '0', STR_PAD_LEFT);
    }
}
