<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeBank
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAccounts(): array
    {
        $pdo = getDB();
        $where = ["status = 'active'"];
        $params = [];
        $this->peScope($pdo, 'pe_bank_accounts', $where, $params);
        $sql = 'SELECT * FROM pe_bank_accounts WHERE ' . implode(' AND ', $where) . ' ORDER BY bank_name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function createAccount(array $data)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("
            INSERT INTO pe_bank_accounts (tenant_id, account_number, bank_name, label, current_balance, status)
            VALUES (?, ?, ?, ?, ?, 'active')
        ");
        $ok = $stmt->execute([
            $tenantId,
            $data['account_number'],
            $data['bank_name'],
            $data['label'] ?? null,
            (float) ($data['opening_balance'] ?? 0),
        ]);
        return $ok ? (int) $pdo->lastInsertId() : false;
    }

    /** @param array<string, mixed> $data */
    public function addMovement(array $data)
    {
        $tenantId = $this->peTenantId();
        $accountId = (int) ($data['bank_account_id'] ?? 0);
        if (!$tenantId || !$accountId) {
            return false;
        }
        $debit = (float) ($data['debit'] ?? 0);
        $credit = (float) ($data['credit'] ?? 0);
        $pdo = getDB();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pe_bank_movements (tenant_id, bank_account_id, movement_date, label, debit, credit, reference)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tenantId,
                $accountId,
                $data['movement_date'] ?? date('Y-m-d'),
                $data['label'],
                $debit,
                $credit,
                $data['reference'] ?? null,
            ]);
            $movId = (int) $pdo->lastInsertId();
            $pdo->prepare('UPDATE pe_bank_accounts SET current_balance = current_balance + ? - ? WHERE id = ? AND tenant_id = ?')
                ->execute([$credit, $debit, $accountId, $tenantId]);
            $pdo->commit();
            return $movId;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getMovements(int $accountId, int $limit = 50): array
    {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT * FROM pe_bank_movements
            WHERE bank_account_id = ? AND tenant_id = ?
            ORDER BY movement_date DESC, id DESC
            LIMIT {$limit}
        ");
        $stmt->execute([$accountId, $this->peTenantId()]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getVatPeriods(): array
    {
        $pdo = getDB();
        $where = ['1=1'];
        $params = [];
        $this->peScope($pdo, 'pe_vat_periods', $where, $params);
        $sql = 'SELECT * FROM pe_vat_periods WHERE ' . implode(' AND ', $where) . ' ORDER BY date_from DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function createVatPeriod(string $label, string $from, string $to)
    {
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return false;
        }
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(vat_amount), 0) FROM pe_sales
            WHERE tenant_id = ? AND status = 'completed' AND DATE(completed_at) BETWEEN ? AND ?
        ");
        $stmt->execute([$tenantId, $from, $to]);
        $collected = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO pe_vat_periods (tenant_id, period_label, date_from, date_to, vat_collected, status)
            VALUES (?, ?, ?, ?, ?, 'open')
        ");
        $ok = $stmt->execute([$tenantId, $label, $from, $to, $collected]);
        return $ok ? (int) $pdo->lastInsertId() : false;
    }
}
