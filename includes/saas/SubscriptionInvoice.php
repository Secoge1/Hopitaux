<?php
/**
 * Facturation automatique des abonnements SaaS (après paiement confirmé).
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/SubscriptionPlan.php';
require_once __DIR__ . '/TenantSchema.php';
require_once __DIR__ . '/saas_helpers.php';

if (!function_exists('platform_name')) {
    require_once __DIR__ . '/../platform_brand.php';
}

class SubscriptionInvoice
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDB();
        TenantSchema::ensure();
    }

    /**
     * Génère ou récupère la facture d'une commande payée (idempotent).
     *
     * @return array{success:bool, invoice_id?:int, invoice_number?:string, message?:string}
     */
    public function ensureFromPaidOrder(int $orderId): array
    {
        $existing = $this->getByOrderId($orderId);
        if ($existing) {
            return [
                'success' => true,
                'invoice_id' => (int) $existing['id'],
                'invoice_number' => $existing['invoice_number'],
                'message' => 'Facture déjà émise',
            ];
        }

        $stmt = $this->pdo->prepare('SELECT * FROM subscription_orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            return ['success' => false, 'message' => 'Commande introuvable'];
        }
        if (($order['payment_status'] ?? '') !== 'paid') {
            return ['success' => false, 'message' => 'La commande n\'est pas payée'];
        }

        $invoiceNumber = $this->generateInvoiceNumber();
        $lineDescription = $this->buildLineDescription($order);
        $paymentMethod = $this->extractPaymentMethod($order);

        $stmt = $this->pdo->prepare("
            INSERT INTO subscription_invoices
            (subscription_order_id, invoice_number, amount_xof, currency, buyer_company, buyer_email,
             buyer_phone, license_type, order_type, ref_command, tenant_id, line_description,
             seller_name, seller_company, payment_method, issued_at, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'issued')
        ");
        $issuedAt = !empty($order['paid_at']) ? $order['paid_at'] : date('Y-m-d H:i:s');

        try {
            $stmt->execute([
                $orderId,
                $invoiceNumber,
                (int) $order['amount_xof'],
                $order['currency'] ?? 'XOF',
                $order['company_name'],
                $order['email'],
                $order['phone'] ?? null,
                SubscriptionPlan::normalizeSlug($order['license_type'] ?? 'annual'),
                $order['order_type'] ?? 'new',
                $order['ref_command'],
                !empty($order['tenant_id']) ? (int) $order['tenant_id'] : null,
                $lineDescription,
                platform_name(),
                platform_company(),
                $paymentMethod,
                $issuedAt,
            ]);
        } catch (PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                $existing = $this->getByOrderId($orderId);
                if ($existing) {
                    return [
                        'success' => true,
                        'invoice_id' => (int) $existing['id'],
                        'invoice_number' => $existing['invoice_number'],
                        'message' => 'Facture déjà émise',
                    ];
                }
            }
            error_log('SubscriptionInvoice::ensureFromPaidOrder: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return [
            'success' => true,
            'invoice_id' => (int) $this->pdo->lastInsertId(),
            'invoice_number' => $invoiceNumber,
            'message' => 'Facture générée',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listInvoices(int $limit = 100, ?string $search = null): array
    {
        $sql = "SELECT i.*, o.paid_at AS order_paid_at
                FROM subscription_invoices i
                INNER JOIN subscription_orders o ON o.id = i.subscription_order_id
                WHERE i.status = 'issued'";
        $params = [];
        if ($search !== null && trim($search) !== '') {
            $sql .= " AND (i.invoice_number LIKE ? OR i.buyer_company LIKE ? OR i.ref_command LIKE ? OR i.buyer_email LIKE ?)";
            $q = '%' . trim($search) . '%';
            $params = [$q, $q, $q, $q];
        }
        $sql .= ' ORDER BY i.issued_at DESC, i.id DESC LIMIT ' . max(1, min(500, $limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.*, o.paid_at AS order_paid_at, o.ipn_payload
             FROM subscription_invoices i
             INNER JOIN subscription_orders o ON o.id = i.subscription_order_id
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getByOrderId(int $orderId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_invoices WHERE subscription_order_id = ? LIMIT 1');
        $stmt->execute([$orderId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function countInvoices(): int
    {
        return (int) $this->pdo->query(
            "SELECT COUNT(*) FROM subscription_invoices WHERE status = 'issued'"
        )->fetchColumn();
    }

    /** Crée les factures manquantes pour les commandes déjà payées. */
    public function backfillMissing(): int
    {
        $rows = $this->pdo->query(
            "SELECT o.id FROM subscription_orders o
             LEFT JOIN subscription_invoices i ON i.subscription_order_id = o.id
             WHERE o.payment_status = 'paid' AND i.id IS NULL
             ORDER BY o.id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        $created = 0;
        foreach ($rows as $row) {
            $result = $this->ensureFromPaidOrder((int) $row['id']);
            if (!empty($result['success']) && ($result['message'] ?? '') === 'Facture générée') {
                $created++;
            }
        }
        return $created;
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'SUB-' . date('Ymd');
        $stmt = $this->pdo->prepare(
            "SELECT invoice_number FROM subscription_invoices
             WHERE invoice_number LIKE ? ORDER BY invoice_number DESC LIMIT 1"
        );
        $stmt->execute([$prefix . '%']);
        $last = (string) ($stmt->fetchColumn() ?: '');
        $seq = 1;
        if ($last !== '' && preg_match('/' . preg_quote($prefix, '/') . '(\d{4})$/', $last, $m)) {
            $seq = (int) $m[1] + 1;
        }
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    /** @param array<string, mixed> $order */
    private function buildLineDescription(array $order): string
    {
        $plan = SubscriptionPlan::get($order['license_type'] ?? 'annual');
        $platform = platform_name();
        $orderType = $order['order_type'] ?? 'new';

        $typeLabels = [
            'new' => 'Souscription',
            'renewal' => 'Renouvellement',
            'upgrade' => 'Mise à niveau',
        ];
        $typeLabel = $typeLabels[$orderType] ?? 'Abonnement';

        return $typeLabel . ' — ' . $plan['name_full'] . ' (' . $platform . ')';
    }

    /** @param array<string, mixed> $order */
    private function extractPaymentMethod(array $order): string
    {
        $payload = $order['ipn_payload'] ?? '';
        if ($payload !== '') {
            $data = json_decode($payload, true);
            if (is_array($data) && !empty($data['payment_method'])) {
                return (string) $data['payment_method'];
            }
        }
        return $order['payment_provider'] ?? 'mobile_money';
    }
}
