<?php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/PeModelTrait.php';

class PeAccounting
{
    use PeModelTrait;

    /** @return list<array<string, mixed>> */
    public function getAccounts(string $search = ''): array
    {
        $pdo = getDB();
        $where = ["status = 'active'"];
        $params = [];
        if ($search !== '') {
            $where[] = '(account_number LIKE ? OR account_label LIKE ?)';
            $term = '%' . trim($search) . '%';
            array_push($params, $term, $term);
        }
        $this->peScope($pdo, 'pe_accounts', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_accounts WHERE ' . implode(' AND ', $where) . ' ORDER BY account_number ASC');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function getJournals(): array
    {
        $pdo = getDB();
        $where = ["status = 'active'"];
        $params = [];
        $this->peScope($pdo, 'pe_journals', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_journals WHERE ' . implode(' AND ', $where) . ' ORDER BY code ASC');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findAccountByNumber(string $number): ?array
    {
        $pdo = getDB();
        $where = ['account_number = ?', "status = 'active'"];
        $params = [$number];
        $this->peScope($pdo, 'pe_accounts', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_accounts WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findJournalByCode(string $code): ?array
    {
        $pdo = getDB();
        $where = ['code = ?'];
        $params = [$code];
        $this->peScope($pdo, 'pe_journals', $where, $params);
        $stmt = $pdo->prepare('SELECT * FROM pe_journals WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param list<array{account_number: string, debit?: float, credit?: float, label?: string}> $lines
     */
    public function createEntry(
        string $journalCode,
        string $label,
        array $lines,
        ?string $refType = null,
        ?int $refId = null,
        ?int $pharmacyId = null,
        bool $autoPost = true
    ): int {
        $tenantId = $this->peTenantId();
        if (!$tenantId || count($lines) < 2) {
            throw new InvalidArgumentException('Écriture invalide.');
        }

        $journal = $this->findJournalByCode($journalCode);
        if (!$journal) {
            throw new RuntimeException('Journal introuvable : ' . $journalCode);
        }

        $pdo = getDB();
        $entryNumber = $this->nextEntryNumber($pdo);

        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $resolvedLines = [];

        foreach ($lines as $line) {
            $account = $this->findAccountByNumber($line['account_number']);
            if (!$account) {
                throw new RuntimeException('Compte introuvable : ' . $line['account_number']);
            }
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            $totalDebit += $debit;
            $totalCredit += $credit;
            $resolvedLines[] = [
                'account_id' => (int) $account['id'],
                'label' => $line['label'] ?? $label,
                'debit' => $debit,
                'credit' => $credit,
            ];
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new RuntimeException('Écriture déséquilibrée (D≠C).');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("
                INSERT INTO pe_journal_entries (
                    tenant_id, pharmacy_id, journal_id, entry_number, entry_date, label,
                    reference_type, reference_id, total_debit, total_credit, status,
                    posted_at, posted_by, created_by
                ) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $status = $autoPost ? 'posted' : 'draft';
            $postedAt = $autoPost ? date('Y-m-d H:i:s') : null;
            $userId = $_SESSION['user_id'] ?? null;
            $stmt->execute([
                $tenantId, $pharmacyId, $journal['id'], $entryNumber, $label,
                $refType, $refId, $totalDebit, $totalCredit, $status,
                $postedAt, $autoPost ? $userId : null, $userId,
            ]);
            $entryId = (int) $pdo->lastInsertId();

            foreach ($resolvedLines as $rl) {
                $stmt = $pdo->prepare("
                    INSERT INTO pe_journal_entry_lines (tenant_id, journal_entry_id, account_id, line_label, debit, credit)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$tenantId, $entryId, $rl['account_id'], $rl['label'], $rl['debit'], $rl['credit']]);

                if ($autoPost) {
                    $delta = $rl['debit'] - $rl['credit'];
                    $pdo->prepare('UPDATE pe_accounts SET current_balance = current_balance + ? WHERE id = ?')
                        ->execute([$delta, $rl['account_id']]);
                }
            }

            if ($refType && $refId) {
                $stmt = $pdo->prepare("
                    INSERT INTO pe_accounting_links (tenant_id, source_type, source_id, journal_entry_id)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE journal_entry_id = VALUES(journal_entry_id)
                ");
                $stmt->execute([$tenantId, $refType, $refId, $entryId]);
            }

            $pdo->commit();
            $this->peAudit('entry_posted', 'pe_journal_entries', $entryId);

            require_once __DIR__ . '/PeHisFinanceBridge.php';
            PeHisFinanceBridge::syncJournalEntry($entryId);

            return $entryId;
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @return list<array<string, mixed>> */
    public function getEntries(int $page = 1, int $limit = 25): array
    {
        $offset = ($page - 1) * $limit;
        $pdo = getDB();
        $where = ["e.status = 'posted'"];
        $params = [];
        $this->peScope($pdo, 'pe_journal_entries', $where, $params, 'e');

        $sql = "
            SELECT e.*, j.code AS journal_code, j.name AS journal_name
            FROM pe_journal_entries e
            INNER JOIN pe_journals j ON j.id = e.journal_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY e.entry_date DESC, e.id DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function getBalance(): array
    {
        $pdo = getDB();
        $where = ["a.status = 'active'"];
        $params = [];
        $this->peScope($pdo, 'pe_accounts', $where, $params, 'a');

        $sql = "
            SELECT a.account_number, a.account_label, a.account_class, a.current_balance,
                   SUM(l.debit) AS total_debit, SUM(l.credit) AS total_credit
            FROM pe_accounts a
            LEFT JOIN pe_journal_entry_lines l ON l.account_id = a.id
            LEFT JOIN pe_journal_entries e ON e.id = l.journal_entry_id AND e.status = 'posted'
            WHERE " . implode(' AND ', $where) . "
            GROUP BY a.id
            ORDER BY a.account_number ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed> */
    public function getStats(): array
    {
        $pdo = getDB();
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return [];
        }

        $stats = [];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pe_accounts WHERE tenant_id = ? AND status = 'active'");
        $stmt->execute([$tenantId]);
        $stats['accounts'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM pe_journal_entries WHERE tenant_id = ? AND status = 'posted'");
        $stmt->execute([$tenantId]);
        $stats['entries'] = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(current_balance), 0) FROM pe_accounts
            WHERE tenant_id = ? AND account_number LIKE '5%' AND status = 'active'
        ");
        $stmt->execute([$tenantId]);
        $stats['treasury'] = (float) $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(current_balance), 0) FROM pe_accounts
            WHERE tenant_id = ? AND account_number LIKE '401%' AND status = 'active'
        ");
        $stmt->execute([$tenantId]);
        $stats['supplier_debt'] = abs((float) $stmt->fetchColumn());

        return $stats;
    }

    private function nextEntryNumber(PDO $pdo): string
    {
        $tenantId = $this->peTenantId();
        $year = date('Y');
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM pe_journal_entries
            WHERE tenant_id = ? AND entry_number LIKE ?
        ");
        $stmt->execute([$tenantId, 'EC' . $year . '%']);
        $count = (int) $stmt->fetchColumn();
        return 'EC' . $year . str_pad((string) ($count + 1), 6, '0', STR_PAD_LEFT);
    }
}
