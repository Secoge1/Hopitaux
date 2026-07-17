<?php

require_once __DIR__ . '/PeModelTrait.php';
require_once __DIR__ . '/PeAccounting.php';

class PeReporting
{
    use PeModelTrait;

    /**
     * Grand livre — mouvements par compte sur une période.
     *
     * @return list<array<string, mixed>>
     */
    public function getGrandLivre(?string $dateFrom = null, ?string $dateTo = null, ?int $accountId = null): array
    {
        $pdo = getDB();
        $where = ["e.status = 'posted'"];
        $params = [];

        if ($dateFrom) {
            $where[] = 'e.entry_date >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where[] = 'e.entry_date <= ?';
            $params[] = $dateTo;
        }
        if ($accountId) {
            $where[] = 'l.account_id = ?';
            $params[] = $accountId;
        }

        $this->peScope($pdo, 'pe_journal_entries', $where, $params, 'e');

        $sql = "
            SELECT e.entry_date, e.entry_number, e.label AS entry_label,
                   j.code AS journal_code,
                   a.account_number, a.account_label,
                   l.line_label, l.debit, l.credit
            FROM pe_journal_entry_lines l
            INNER JOIN pe_journal_entries e ON e.id = l.journal_entry_id
            INNER JOIN pe_journals j ON j.id = e.journal_id
            INNER JOIN pe_accounts a ON a.id = l.account_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.account_number ASC, e.entry_date ASC, e.id ASC, l.id ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed> */
    public function getBilan(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $pdo = getDB();
        $tenantId = $this->peTenantId();
        if (!$tenantId) {
            return [];
        }

        $accounts = (new PeAccounting())->getAccounts();
        $byClass = [
            'actif' => 0.0,
            'passif' => 0.0,
            'capitaux' => 0.0,
            'produits' => 0.0,
            'charges' => 0.0,
        ];
        $details = [];

        foreach ($accounts as $acc) {
            $balance = (float) $acc['current_balance'];
            $class = (int) $acc['account_class'];
            $type = (string) $acc['account_type'];
            $details[] = [
                'number' => $acc['account_number'],
                'label' => $acc['account_label'],
                'class' => $class,
                'type' => $type,
                'balance' => $balance,
            ];

            if ($class >= 1 && $class <= 2) {
                $byClass['capitaux'] += $balance;
            } elseif ($class === 3 || ($type === 'asset' && str_starts_with($acc['account_number'], '3'))) {
                $byClass['actif'] += $balance;
            } elseif ($class === 4 || $type === 'liability' || $type === 'asset') {
                if ($type === 'asset' && !str_starts_with($acc['account_number'], '3')) {
                    $byClass['actif'] += $balance;
                } else {
                    $byClass['passif'] += abs($balance);
                }
            } elseif ($class >= 6 && $class <= 7) {
                if ($type === 'revenue' || $class === 7) {
                    $byClass['produits'] += abs($balance);
                } else {
                    $byClass['charges'] += abs($balance);
                }
            }
        }

        $resultat = $byClass['produits'] - $byClass['charges'];
        $totalActif = $byClass['actif'];
        $totalPassif = $byClass['passif'] + $byClass['capitaux'] + $resultat;

        return [
            'actif' => $totalActif,
            'passif' => $totalPassif,
            'capitaux' => $byClass['capitaux'],
            'produits' => $byClass['produits'],
            'charges' => $byClass['charges'],
            'resultat' => $resultat,
            'equilibre' => abs($totalActif - $totalPassif) < 1.0,
            'details' => $details,
            'date_from' => $dateFrom ?? date('Y-01-01'),
            'date_to' => $dateTo ?? date('Y-m-d'),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function getGrandLivreGrouped(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $lines = $this->getGrandLivre($dateFrom, $dateTo);
        $grouped = [];
        foreach ($lines as $line) {
            $key = $line['account_number'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'account_number' => $line['account_number'],
                    'account_label' => $line['account_label'],
                    'lines' => [],
                    'total_debit' => 0.0,
                    'total_credit' => 0.0,
                ];
            }
            $grouped[$key]['lines'][] = $line;
            $grouped[$key]['total_debit'] += (float) $line['debit'];
            $grouped[$key]['total_credit'] += (float) $line['credit'];
        }
        return array_values($grouped);
    }
}
