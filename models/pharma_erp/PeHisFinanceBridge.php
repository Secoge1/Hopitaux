<?php

/**
 * Pont PharmaPro → module Finances HIS (ecritures_comptables).
 * Actif lorsque payment_finance_sync est activé pour le tenant.
 */
class PeHisFinanceBridge
{
    public static function isEnabled(?int $tenantId = null): bool
    {
        if (!function_exists('payment_finance_sync_enabled')) {
            require_once __DIR__ . '/../../includes/saas/saas_helpers.php';
        }
        return payment_finance_sync_enabled($tenantId);
    }

    /**
     * Réplique une écriture PharmaPro vers le module Finances HIS.
     *
     * @return list<int> IDs ecritures_comptables créées
     */
    public static function syncJournalEntry(int $journalEntryId): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM pe_journal_entries WHERE id = ? LIMIT 1');
        $stmt->execute([$journalEntryId]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$entry || $entry['status'] !== 'posted') {
            return [];
        }

        $stmt = $pdo->prepare('SELECT 1 FROM pe_his_ecriture_map WHERE journal_entry_id = ? LIMIT 1');
        $stmt->execute([$journalEntryId]);
        if ($stmt->fetchColumn()) {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT l.debit, l.credit, l.line_label, a.account_number, a.account_label, a.account_type
            FROM pe_journal_entry_lines l
            INNER JOIN pe_accounts a ON a.id = l.account_id
            WHERE l.journal_entry_id = ?
            ORDER BY l.id ASC
        ");
        $stmt->execute([$journalEntryId]);
        $lines = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($lines) < 2) {
            return [];
        }

        $debits = array_values(array_filter($lines, fn($l) => (float) $l['debit'] > 0));
        $credits = array_values(array_filter($lines, fn($l) => (float) $l['credit'] > 0));
        if (empty($debits) || empty($credits)) {
            return [];
        }

        require_once __DIR__ . '/../../models/Finances.php';
        $finances = new Finances();
        $created = [];
        $splitIndex = 0;

        if (count($credits) === 1) {
            $creditLine = $credits[0];
            $creditId = self::resolveHisCompteId($finances, $creditLine);
            foreach ($debits as $debitLine) {
                $amount = round((float) $debitLine['debit'], 2);
                if ($amount <= 0) {
                    continue;
                }
                $debitId = self::resolveHisCompteId($finances, $debitLine);
                $ecritureId = self::createHisEcriture($finances, $entry, $debitId, $creditId, $amount, $debitLine['line_label'] ?? $entry['label']);
                if ($ecritureId) {
                    self::storeMap($pdo, (int) $entry['tenant_id'], $journalEntryId, $ecritureId, $splitIndex++, $amount);
                    $created[] = $ecritureId;
                }
            }
        } else {
            $debitLine = $debits[0];
            $debitId = self::resolveHisCompteId($finances, $debitLine);
            foreach ($credits as $creditLine) {
                $amount = round((float) $creditLine['credit'], 2);
                if ($amount <= 0) {
                    continue;
                }
                $creditId = self::resolveHisCompteId($finances, $creditLine);
                $ecritureId = self::createHisEcriture($finances, $entry, $debitId, $creditId, $amount, $creditLine['line_label'] ?? $entry['label']);
                if ($ecritureId) {
                    self::storeMap($pdo, (int) $entry['tenant_id'], $journalEntryId, $ecritureId, $splitIndex++, $amount);
                    $created[] = $ecritureId;
                }
            }
        }

        if (!empty($created)) {
            $pdo->prepare('UPDATE pe_accounting_links SET ecriture_comptable_id = ? WHERE journal_entry_id = ? AND tenant_id = ?')
                ->execute([$created[0], $journalEntryId, $entry['tenant_id']]);
        }

        return $created;
    }

    private static function resolveHisCompteId(Finances $finances, array $line): int
    {
        $number = (string) $line['account_number'];
        $label = (string) $line['account_label'];
        $type = self::mapAccountType((string) $line['account_type']);

        $existing = self::findHisCompteByNumber($number);
        if ($existing) {
            return (int) $existing['id'];
        }

        $short = rtrim($number, '0');
        if ($short !== $number) {
            $existing = self::findHisCompteByNumber($short);
            if ($existing) {
                return (int) $existing['id'];
            }
        }

        $id = $finances->createCompte([
            'numero_compte' => $number,
            'libelle' => $label,
            'nom_compte' => $label,
            'type_compte' => $type,
            'statut' => 'actif',
            'description' => 'Compte PharmaPro ERP — sync auto',
        ]);

        if (!$id) {
            throw new RuntimeException('Impossible de créer le compte HIS : ' . $number);
        }

        return (int) $id;
    }

    private static function findHisCompteByNumber(string $number): ?array
    {
        $pdo = getDB();
        require_once __DIR__ . '/../../includes/saas/TenantScope.php';
        $where = ['numero_compte = ?'];
        $params = [$number];
        TenantScope::appendWhere($pdo, 'comptes_comptables', $where, $params);
        $stmt = $pdo->prepare('SELECT id, numero_compte FROM comptes_comptables WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private static function createHisEcriture(
        Finances $finances,
        array $entry,
        int $debitId,
        int $creditId,
        float $amount,
        string $label
    ): ?int {
        try {
            return (int) $finances->createEcriture([
                'date_ecriture' => $entry['entry_date'],
                'compte_debit_id' => $debitId,
                'compte_credit_id' => $creditId,
                'montant' => $amount,
                'libelle' => '[PharmaPro] ' . $label,
                'reference' => $entry['entry_number'],
                'valide' => 1,
                'cree_par' => $_SESSION['user_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('PeHisFinanceBridge: ' . $e->getMessage());
            return null;
        }
    }

    private static function storeMap(PDO $pdo, int $tenantId, int $journalEntryId, int $ecritureId, int $splitIndex, float $amount): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO pe_his_ecriture_map (tenant_id, journal_entry_id, ecriture_comptable_id, split_index, amount)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$tenantId, $journalEntryId, $ecritureId, $splitIndex, $amount]);
    }

    private static function mapAccountType(string $peType): string
    {
        switch ($peType) {
            case 'liability':
                return 'passif';
            case 'equity':
                return 'capitaux';
            case 'revenue':
                return 'produit';
            case 'expense':
                return 'charge';
            default:
                return 'actif';
        }
    }

    /** @return list<array<string, mixed>> */
    public static function getSyncStatus(int $journalEntryId): array
    {
        $pdo = getDB();
        $stmt = $pdo->prepare("
            SELECT m.*, e.numero_ecriture, e.libelle
            FROM pe_his_ecriture_map m
            LEFT JOIN ecritures_comptables e ON e.id = m.ecriture_comptable_id
            WHERE m.journal_entry_id = ?
            ORDER BY m.split_index ASC
        ");
        $stmt->execute([$journalEntryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
