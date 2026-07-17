<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeSupplierInvoice
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAll(int $page = 1, int $limit = 25, string $status = '', string $search = ''): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'si.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(si.invoice_number LIKE ? OR s.company_name LIKE ?)';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_supplier_invoices', $where, $params, 'si');

        $sql = "
            SELECT si.*, s.company_name AS supplier_name, gr.receipt_number
            FROM pe_supplier_invoices si
            INNER JOIN pe_suppliers s ON s.id = si.supplier_id
            LEFT JOIN pe_goods_receipts gr ON gr.id = si.goods_receipt_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY si.invoice_date DESC, si.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getCount(string $status = '', string $search = ''): int
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        if ($status !== '') {
            $where[] = 'si.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $term = '%' . trim($search) . '%';
            $where[] = '(si.invoice_number LIKE ? OR s.company_name LIKE ?)';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_supplier_invoices', $where, $params, 'si');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM pe_supplier_invoices si
            INNER JOIN pe_suppliers s ON s.id = si.supplier_id
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        $pdo = getDB();
        $where = ['si.id = ?'];
        $params = [$id];
        $this->peScope($pdo, 'pe_supplier_invoices', $where, $params, 'si');
        $stmt = $pdo->prepare("
            SELECT si.*, s.company_name AS supplier_name, s.phone AS supplier_phone, gr.receipt_number
            FROM pe_supplier_invoices si
            INNER JOIN pe_suppliers s ON s.id = si.supplier_id
            LEFT JOIN pe_goods_receipts gr ON gr.id = si.goods_receipt_id
            WHERE " . implode(' AND ', $where) . " LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function recordPayment(int $id, float $amount, ?string $reference = null): bool
    {
        $invoice = $this->findById($id);
        if (!$invoice || ($invoice['status'] ?? '') === 'cancelled') {
            return false;
        }

        $amount = round(max(0, $amount), 2);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Montant invalide.');
        }

        $pdo = getDB();
        $tenantId = $this->peTenantId();
        $newPaid = round((float) $invoice['amount_paid'] + $amount, 2);
        $total = (float) $invoice['amount_ttc'];
        if ($newPaid > $total + 0.01) {
            throw new InvalidArgumentException('Montant supérieur au reste dû.');
        }

        $status = 'partial';
        if ($newPaid >= $total - 0.01) {
            $status = 'paid';
            $newPaid = $total;
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE pe_supplier_invoices SET amount_paid = ?, status = ? WHERE id = ? AND tenant_id = ?')
                ->execute([$newPaid, $status, $id, $tenantId]);

            $pdo->prepare('UPDATE pe_suppliers SET current_balance = GREATEST(0, current_balance - ?) WHERE id = ? AND tenant_id = ?')
                ->execute([$amount, (int) $invoice['supplier_id'], $tenantId]);

            $pdo->commit();
            $this->peAudit('supplier_invoice_payment', 'pe_supplier_invoices', $id, [
                'amount' => $amount,
                'reference' => $reference,
                'status' => $status,
            ]);
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return array{pending: int, partial: int, paid: int, total_due: float} */
    public function getSummary(): array
    {
        $pdo = getDB();
        $where = ["status IN ('pending','partial')"];
        $params = [];
        $this->peScope($pdo, 'pe_supplier_invoices', $where, $params);
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                SUM(CASE WHEN status = 'partial' THEN 1 ELSE 0 END) AS partial,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                COALESCE(SUM(amount_ttc - amount_paid), 0) AS total_due
            FROM pe_supplier_invoices
            WHERE " . implode(' AND ', $where)
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'partial' => (int) ($row['partial'] ?? 0),
            'paid' => (int) ($row['paid'] ?? 0),
            'total_due' => (float) ($row['total_due'] ?? 0),
        ];
    }
}
