<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';
require_once __DIR__ . '/PeStock.php';
require_once __DIR__ . '/PePharmacy.php';
require_once __DIR__ . '/PeSale.php';

class PeReturn
{
    use PeModelTrait;

    /**
     * @param list<array{line_id: int, quantity: int}> $returnLines
     */
    public function createFromSale(int $saleId, array $returnLines, ?string $reason = null): array
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId || empty($returnLines)) {
            throw new InvalidArgumentException('Retour invalide.');
        }

        $saleModel = new PeSale();
        $sale = $saleModel->getById($saleId);
        if (!$sale || ($sale['status'] ?? '') !== 'completed') {
            throw new RuntimeException('Vente introuvable ou non éligible au retour.');
        }

        $pharmacyId = (int) $sale['pharmacy_id'];
        $pharmacyModel = new PePharmacy();
        $depositId = $pharmacyModel->getDefaultDepositId($pharmacyId);
        if (!$depositId) {
            throw new RuntimeException('Dépôt stock introuvable.');
        }

        $linesById = [];
        foreach ($sale['lines'] as $line) {
            $linesById[(int) $line['id']] = $line;
        }

        $pdo = getDB();
        $stockModel = new PeStock();
        $returnNumber = $this->nextReturnNumber($pdo, $pharmacyId);

        $pdo->beginTransaction();
        try {
            $totalRefund = 0.0;
            $prepared = [];

            foreach ($returnLines as $rl) {
                $lineId = (int) ($rl['line_id'] ?? 0);
                $qty = max(1, (int) ($rl['quantity'] ?? 0));
                if (!isset($linesById[$lineId])) {
                    continue;
                }
                $sl = $linesById[$lineId];
                $soldQty = (int) $sl['quantity'];
                $alreadyReturned = (int) ($sl['returned_quantity'] ?? 0);
                $maxReturn = $soldQty - $alreadyReturned;
                if ($qty > $maxReturn) {
                    throw new RuntimeException('Quantité retour trop élevée pour ' . ($sl['product_name'] ?? 'produit'));
                }

                $unitPrice = (float) $sl['unit_price'];
                $lineTotal = round($unitPrice * $qty, 2);
                $totalRefund += $lineTotal;

                $prepared[] = [
                    'sale_line' => $sl,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ];
            }

            if (empty($prepared)) {
                throw new InvalidArgumentException('Aucune ligne à retourner.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO pe_returns (tenant_id, pharmacy_id, sale_id, return_number, total_refund, status, reason, created_by)
                VALUES (?, ?, ?, ?, ?, 'completed', ?, ?)
            ");
            $stmt->execute([
                $tenantId, $pharmacyId, $saleId, $returnNumber, $totalRefund,
                $reason, $_SESSION['user_id'] ?? null,
            ]);
            $returnId = (int) $pdo->lastInsertId();

            foreach ($prepared as $pl) {
                $sl = $pl['sale_line'];
                $stockModel->stockIn([
                    'pharmacy_id' => $pharmacyId,
                    'deposit_id' => $depositId,
                    'product_id' => (int) $sl['product_id'],
                    'quantity' => $pl['qty'],
                    'unit_cost' => (float) ($sl['unit_cost'] ?? 0),
                    'lot_number' => 'RET-' . $returnNumber,
                    'expiry_date' => date('Y-m-d', strtotime('+2 years')),
                    'reference_type' => 'sale_return',
                    'reference_id' => $returnId,
                    'notes' => 'Retour vente ' . ($sale['sale_number'] ?? ''),
                ]);

                $stmt = $pdo->prepare("
                    INSERT INTO pe_return_lines (tenant_id, return_id, product_id, quantity, unit_price, line_total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $tenantId, $returnId, (int) $sl['product_id'], $pl['qty'], $pl['unit_price'], $pl['line_total'],
                ]);

                if ($this->columnExists($pdo, 'pe_sale_lines', 'returned_quantity')) {
                    $pdo->prepare('UPDATE pe_sale_lines SET returned_quantity = returned_quantity + ? WHERE id = ? AND tenant_id = ?')
                        ->execute([$pl['qty'], (int) $sl['id'], $tenantId]);
                }
            }

            $pdo->commit();
            $this->peAudit('sale_return', 'pe_returns', $returnId, [
                'number' => $returnNumber,
                'sale_id' => $saleId,
                'refund' => $totalRefund,
            ]);

            return [
                'id' => $returnId,
                'return_number' => $returnNumber,
                'total_refund' => $totalRefund,
            ];
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 25, string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ["r.status = 'completed'"];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(r.return_number LIKE ? OR s.sale_number LIKE ?)';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_returns', $where, $params, 'r');

        $sql = "
            SELECT r.*, s.sale_number, s.customer_name
            FROM pe_returns r
            INNER JOIN pe_sales s ON s.id = r.sale_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY r.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCount(string $search = ''): int
    {
        $pdo = getDB();
        $where = ["r.status = 'completed'"];
        $params = [];
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(r.return_number LIKE ? OR s.sale_number LIKE ?)';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_returns', $where, $params, 'r');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM pe_returns r
            INNER JOIN pe_sales s ON s.id = r.sale_id
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function getById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['r.id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_returns', $where, $params, 'r');
        $stmt = $pdo->prepare("
            SELECT r.*, s.sale_number, s.customer_name, s.total_ttc AS sale_total
            FROM pe_returns r
            INNER JOIN pe_sales s ON s.id = r.sale_id
            WHERE " . implode(' AND ', $where) . " LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT rl.*, p.name AS product_name FROM pe_return_lines rl LEFT JOIN pe_products p ON p.id = rl.product_id WHERE rl.return_id = ? AND rl.tenant_id = ?');
        $stmt->execute([$id, $this->peTenantId()]);
        $row['lines'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    private function nextReturnNumber(PDO $pdo, int $pharmacyId): string
    {
        $tenantId = $this->peTenantId();
        $type = 'sale_return';
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
                VALUES (?, ?, ?, 'R', 1)
            ")->execute([$tenantId, $pharmacyId, $type]);
            $num = 1;
            $pad = 6;
        } else {
            $num = (int) $seq['next_number'];
            $pad = (int) $seq['pad_length'];
            $pdo->prepare('UPDATE pe_document_sequences SET next_number = next_number + 1 WHERE id = ?')
                ->execute([$seq['id']]);
        }
        return 'R' . str_pad((string) $num, $pad, '0', STR_PAD_LEFT);
    }

    private function columnExists(PDO $pdo, string $table, string $column): bool
    {
        $stmt = $pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1"
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}
