<?php

require_once __DIR__ . '/PeAccounting.php';

/**
 * Moteur d'écritures automatiques SYSCOHADA — ventes & achats PharmaPro.
 */
class PeAccountingEngine
{
    public static function ensureSeed(?int $tenantId = null): void
    {
        require_once __DIR__ . '/../../includes/saas/TenantContext.php';
        if ($tenantId === null) {
            TenantContext::bindFromSession();
            $tenantId = TenantContext::getTenantId();
        }
        if (!$tenantId) {
            return;
        }

        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM pe_accounts WHERE tenant_id = ?');
        $stmt->execute([(int) $tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            self::ensureSystemAccounts((int) $tenantId);
            return;
        }

        $accounts = [
            ['571000', 'Caisse principale', 5, 'asset'],
            ['521000', 'Banques', 5, 'asset'],
            ['411000', 'Clients', 4, 'asset'],
            ['401000', 'Fournisseurs', 4, 'liability'],
            ['310000', 'Stocks de marchandises', 3, 'asset'],
            ['601000', 'Achats de marchandises', 6, 'expense'],
            ['707000', 'Ventes de marchandises', 7, 'revenue'],
            ['709000', 'Remises accordées', 7, 'expense'],
            ['445710', 'TVA collectée', 4, 'liability'],
            ['445660', 'TVA déductible', 4, 'asset'],
            ['603000', 'Variations de stocks', 6, 'expense'],
            ['120000', 'Résultat de l\'exercice', 1, 'equity'],
        ];

        foreach ($accounts as [$num, $label, $class, $type]) {
            $pdo->prepare("
                INSERT INTO pe_accounts (tenant_id, account_number, account_label, account_class, account_type, is_system)
                VALUES (?, ?, ?, ?, ?, 1)
            ")->execute([(int) $tenantId, $num, $label, $class, $type]);
        }

        $journals = [
            ['VE', 'Journal des ventes', 'sales'],
            ['AC', 'Journal des achats', 'purchases'],
            ['CA', 'Journal de caisse', 'cash'],
            ['BQ', 'Journal de banque', 'bank'],
            ['OD', 'Opérations diverses', 'general'],
        ];

        foreach ($journals as [$code, $name, $type]) {
            $pdo->prepare("
                INSERT INTO pe_journals (tenant_id, code, name, journal_type, is_system)
                VALUES (?, ?, ?, ?, 1)
            ")->execute([(int) $tenantId, $code, $name, $type]);
        }
    }

    private static function ensureSystemAccounts(int $tenantId): void
    {
        $pdo = getDB();
        $required = [
            ['709000', 'Remises accordées', 7, 'expense'],
        ];
        foreach ($required as [$num, $label, $class, $type]) {
            $stmt = $pdo->prepare('SELECT id FROM pe_accounts WHERE tenant_id = ? AND account_number = ? LIMIT 1');
            $stmt->execute([$tenantId, $num]);
            if ($stmt->fetchColumn()) {
                continue;
            }
            $pdo->prepare("
                INSERT INTO pe_accounts (tenant_id, account_number, account_label, account_class, account_type, is_system)
                VALUES (?, ?, ?, ?, ?, 1)
            ")->execute([$tenantId, $num, $label, $class, $type]);
        }
    }

    public static function postSale(int $saleId): ?int
    {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM pe_sales WHERE id = ? LIMIT 1');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale || $sale['status'] !== 'completed') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM pe_accounting_links WHERE source_type = ? AND source_id = ? AND tenant_id = ?');
        $stmt->execute(['sale', $saleId, $sale['tenant_id']]);
        if ($stmt->fetchColumn()) {
            return null;
        }

        self::ensureSeed((int) $sale['tenant_id']);

        $accounting = new PeAccounting();
        $_SESSION['user_id'] = $_SESSION['user_id'] ?? null;

        $totalTtc = (float) $sale['total_ttc'];
        $subtotal = (float) $sale['subtotal_ht'];
        $vat = (float) $sale['vat_amount'];
        $discount = (float) ($sale['discount_amount'] ?? 0);
        $paymentMethod = self::getSalePaymentMethod($pdo, $saleId);

        $treasuryAccount = in_array($paymentMethod, ['bank', 'card'], true) ? '521000' : '571000';
        if ($paymentMethod === 'credit') {
            $treasuryAccount = '411000';
        }

        $lines = [
            ['account_number' => $treasuryAccount, 'debit' => $totalTtc, 'label' => 'Encaissement vente ' . $sale['sale_number']],
            ['account_number' => '707000', 'credit' => $subtotal, 'label' => 'Vente marchandises'],
        ];
        if ($vat > 0) {
            $lines[] = ['account_number' => '445710', 'credit' => $vat, 'label' => 'TVA collectée'];
        }
        if ($discount > 0) {
            $lines[] = ['account_number' => '709000', 'debit' => $discount, 'label' => 'Remise commerciale ' . $sale['sale_number']];
        }

        return $accounting->createEntry(
            'VE',
            'Vente POS ' . $sale['sale_number'],
            $lines,
            'sale',
            $saleId,
            (int) $sale['pharmacy_id']
        );
    }

    public static function postGoodsReceipt(int $receiptId): ?int
    {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT * FROM pe_goods_receipts WHERE id = ? LIMIT 1');
        $stmt->execute([$receiptId]);
        $receipt = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$receipt || $receipt['status'] !== 'validated') {
            return null;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM pe_accounting_links WHERE source_type = ? AND source_id = ? AND tenant_id = ?');
        $stmt->execute(['goods_receipt', $receiptId, $receipt['tenant_id']]);
        if ($stmt->fetchColumn()) {
            return null;
        }

        self::ensureSeed((int) $receipt['tenant_id']);

        $accounting = new PeAccounting();
        $subtotal = (float) $receipt['subtotal_ht'];
        $vat = (float) $receipt['vat_amount'];
        $totalTtc = (float) $receipt['total_ttc'];

        $lines = [
            ['account_number' => '310000', 'debit' => $subtotal, 'label' => 'Entrée stock'],
        ];
        if ($vat > 0) {
            $lines[] = ['account_number' => '445660', 'debit' => $vat, 'label' => 'TVA déductible'];
        }
        $lines[] = ['account_number' => '401000', 'credit' => $totalTtc, 'label' => 'Dette fournisseur'];

        return $accounting->createEntry(
            'AC',
            'Réception ' . $receipt['receipt_number'],
            $lines,
            'goods_receipt',
            $receiptId,
            (int) $receipt['pharmacy_id']
        );
    }

    private static function getSalePaymentMethod(PDO $pdo, int $saleId): string
    {
        $stmt = $pdo->prepare('SELECT payment_method FROM pe_sale_payments WHERE sale_id = ? LIMIT 1');
        $stmt->execute([$saleId]);
        return (string) ($stmt->fetchColumn() ?: 'cash');
    }
}
