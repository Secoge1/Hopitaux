<?php
/**
 * Commandes d'abonnement + activation après paiement Mobile Money.
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/SubscriptionPlan.php';
require_once __DIR__ . '/PharmaSubscriptionPlan.php';
require_once __DIR__ . '/PharmaCommercial.php';
require_once __DIR__ . '/SubscriptionService.php';
require_once __DIR__ . '/SubscriptionInvoice.php';
require_once __DIR__ . '/TenantSchema.php';
require_once __DIR__ . '/saas_helpers.php';

class SubscriptionCheckout
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getDB();
        TenantSchema::ensure();
    }

    public static function calculateAmount(string $planSlug, string $orderType = 'new', string $productLine = 'clinical'): int
    {
        if (PharmaCommercial::isPharmaProductLine($productLine)) {
            return PharmaSubscriptionPlan::calculateAmount($planSlug, $orderType);
        }
        $plan = SubscriptionPlan::get($planSlug);
        if ($orderType === 'renewal') {
            return (int) ($plan['renewal_price_xof'] ?? $plan['price_xof']);
        }
        return (int) $plan['price_xof'];
    }

    private function resolvePlan(string $planSlug, string $productLine = 'clinical'): array
    {
        if (PharmaCommercial::isPharmaProductLine($productLine)) {
            return PharmaSubscriptionPlan::get($planSlug);
        }
        return SubscriptionPlan::get($planSlug);
    }

    /**
     * @param array $data license_type, company_name, email, phone?, nom_utilisateur?, password?, nom_complet?, tenant_id?, order_type?
     */
    public function createOrder(array $data): array
    {
        $productLine = PharmaCommercial::normalizeProductLine($data['product_line'] ?? 'clinical');
        $planSlug = PharmaCommercial::isPharmaProductLine($productLine)
            ? PharmaSubscriptionPlan::normalizeSlug($data['license_type'] ?? $data['plan'] ?? '')
            : SubscriptionPlan::normalizeSlug($data['license_type'] ?? $data['plan'] ?? '');
        $plan = $this->resolvePlan($planSlug, $productLine);
        $orderType = $data['order_type'] ?? 'new';
        if (!in_array($orderType, ['new', 'renewal', 'upgrade'], true)) {
            $orderType = 'new';
        }

        if (!empty($data['tenant_id']) && $orderType === 'new') {
            $stmt = $this->pdo->prepare('SELECT license_type FROM tenants WHERE id = ?');
            $stmt->execute([(int) $data['tenant_id']]);
            $current = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($current) {
                if (PharmaCommercial::isPharmaProductLine($productLine)) {
                    $currentSlug = PharmaSubscriptionPlan::normalizeSlug($current['license_type'] ?? '');
                    if (PharmaSubscriptionPlan::planRank($planSlug) > PharmaSubscriptionPlan::planRank($currentSlug)) {
                        $orderType = 'upgrade';
                    }
                } else {
                    $currentSlug = SubscriptionPlan::normalizeSlug($current['license_type'] ?? '');
                    if (SubscriptionPlan::planRank($planSlug) > SubscriptionPlan::planRank($currentSlug)) {
                        $orderType = 'upgrade';
                    }
                }
            }
        }

        $amount = self::calculateAmount(
            $planSlug,
            $orderType === 'renewal' ? 'renewal' : 'new',
            $productLine
        );
        if ($amount <= 0) {
            return ['success' => false, 'message' => 'Montant invalide pour ce type de licence.'];
        }

        $prefix = PharmaCommercial::isPharmaProductLine($productLine)
            ? ($orderType === 'renewal' ? 'PHR-R' : ($orderType === 'upgrade' ? 'PHR-U' : 'PHR'))
            : ($orderType === 'renewal' ? 'RENEW' : ($orderType === 'upgrade' ? 'UPG' : 'EFS'));
        $ref = $prefix . '-' . strtoupper($planSlug) . '-' . time() . '-' . bin2hex(random_bytes(3));

        $passwordPlain = !empty($data['password']) ? (string) $data['password'] : null;
        $passwordHash = $passwordPlain !== null ? password_hash($passwordPlain, PASSWORD_DEFAULT) : null;

        $hasProductLine = $this->tableHasColumn('subscription_orders', 'product_line');
        if ($hasProductLine) {
            $stmt = $this->pdo->prepare("
                INSERT INTO subscription_orders
                (ref_command, order_type, product_line, license_type, amount_xof, company_name, email, phone,
                 nom_utilisateur, password_hash, password_initial, nom_complet, tenant_id, payment_status, payment_provider)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'mobile_money')
            ");
            $stmt->execute([
                $ref,
                $orderType,
                $productLine,
                $planSlug,
                $amount,
                saas_sanitize($data['company_name'] ?? ''),
                saas_sanitize($data['email'] ?? ''),
                saas_sanitize($data['phone'] ?? ''),
                saas_sanitize($data['nom_utilisateur'] ?? ''),
                $passwordHash,
                $passwordPlain,
                saas_sanitize($data['nom_complet'] ?? ''),
                !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null,
            ]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO subscription_orders
                (ref_command, order_type, license_type, amount_xof, company_name, email, phone,
                 nom_utilisateur, password_hash, password_initial, nom_complet, tenant_id, payment_status, payment_provider)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'mobile_money')
            ");
            $stmt->execute([
                $ref,
                $orderType,
                $planSlug,
                $amount,
                saas_sanitize($data['company_name'] ?? ''),
                saas_sanitize($data['email'] ?? ''),
                saas_sanitize($data['phone'] ?? ''),
                saas_sanitize($data['nom_utilisateur'] ?? ''),
                $passwordHash,
                $passwordPlain,
                saas_sanitize($data['nom_complet'] ?? ''),
                !empty($data['tenant_id']) ? (int) $data['tenant_id'] : null,
            ]);
        }

        $orderId = (int) $this->pdo->lastInsertId();
        $isLifetime = PharmaCommercial::isPharmaProductLine($productLine)
            ? PharmaSubscriptionPlan::isLifetime($planSlug)
            : SubscriptionPlan::isLifetime($planSlug);
        $typeLabel = $isLifetime ? 'licence à vie' : 'abonnement annuel';
        $brand = PharmaCommercial::isPharmaProductLine($productLine)
            ? PharmaCommercial::brandName()
            : (function_exists('platform_name') ? platform_name() : (defined('PLATFORM_NAME') ? PLATFORM_NAME : 'Se.Santé'));

        return [
            'success' => true,
            'order_id' => $orderId,
            'ref_command' => $ref,
            'amount' => $amount,
            'plan' => $plan,
            'product_line' => $productLine,
            'item_name' => $brand . ' — ' . ($plan['name'] ?? 'Plan') . ' (' . $typeLabel . ')',
        ];
    }

    public function getMobileMoneyInstructionsUrl(int $orderId): array
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Commande introuvable.'];
        }
        if ($order['payment_status'] !== 'pending') {
            return ['success' => false, 'message' => 'Cette commande a déjà été traitée.'];
        }
        return [
            'success' => true,
            'redirect_url' => saas_payment_instructions_url($order['ref_command']),
            'ref_command' => $order['ref_command'],
        ];
    }

    public function markPaidManually(int $orderId, string $paymentMethod = ''): array
    {
        $order = $this->getOrder($orderId);
        if (!$order) {
            return ['success' => false, 'message' => 'Commande introuvable.'];
        }
        if ($order['payment_status'] === 'paid') {
            $invoice = (new SubscriptionInvoice())->ensureFromPaidOrder((int) $order['id']);
            return [
                'success' => true,
                'message' => 'Déjà activée',
                'tenant_id' => $order['tenant_id'],
                'order_id' => (int) $order['id'],
                'invoice_id' => $invoice['invoice_id'] ?? null,
                'invoice_number' => $invoice['invoice_number'] ?? null,
            ];
        }
        return $this->markPaidFromIpn([
            'ref_command' => $order['ref_command'],
            'final_item_price' => (int) $order['amount_xof'],
            'payment_method' => $paymentMethod !== '' ? $paymentMethod : saas_get_payment_methods(),
            'manual_confirmation' => true,
        ]);
    }

    public function getOrderByRef(string $ref): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_orders WHERE ref_command = ?');
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getOrder(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM subscription_orders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function markPaidFromIpn(array $ipnData): array
    {
        $ref = $ipnData['ref_command'] ?? '';
        if ($ref === '') {
            return ['success' => false, 'message' => 'ref_command manquant'];
        }

        $order = $this->getOrderByRef($ref);
        if (!$order) {
            return ['success' => false, 'message' => 'Commande inconnue: ' . $ref];
        }
        if ($order['payment_status'] === 'paid') {
            $invoice = (new SubscriptionInvoice())->ensureFromPaidOrder((int) $order['id']);
            return [
                'success' => true,
                'message' => 'Déjà activée',
                'tenant_id' => $order['tenant_id'],
                'order_id' => (int) $order['id'],
                'invoice_id' => $invoice['invoice_id'] ?? null,
                'invoice_number' => $invoice['invoice_number'] ?? null,
            ];
        }

        $paidAmount = (int) ($ipnData['final_item_price'] ?? $ipnData['item_price'] ?? 0);
        if ($paidAmount > 0 && $paidAmount < (int) $order['amount_xof']) {
            return [
                'success' => false,
                'message' => 'Montant reçu insuffisant (' . $paidAmount . ' < ' . $order['amount_xof'] . ')',
            ];
        }

        $this->pdo->beginTransaction();
        try {
            $lockStmt = $this->pdo->prepare(
                'SELECT * FROM subscription_orders WHERE id = ? FOR UPDATE'
            );
            $lockStmt->execute([$order['id']]);
            $lockedOrder = $lockStmt->fetch(PDO::FETCH_ASSOC);
            if (!$lockedOrder || $lockedOrder['payment_status'] !== 'pending') {
                $this->pdo->rollBack();
                return ['success' => false, 'message' => 'Commande déjà traitée ou annulée'];
            }

            $stmt = $this->pdo->prepare("
                UPDATE subscription_orders SET payment_status = 'paid', paid_at = NOW(), ipn_payload = ?
                WHERE id = ? AND payment_status = 'pending'
            ");
            $stmt->execute([json_encode($ipnData, JSON_UNESCAPED_UNICODE), $order['id']]);

            $tenantId = $this->activateSubscription($lockedOrder, false);
            $this->pdo->prepare('UPDATE subscription_orders SET tenant_id = ? WHERE id = ?')
                ->execute([$tenantId, $order['id']]);

            $uStmt = $this->pdo->prepare(
                "SELECT id FROM utilisateurs WHERE tenant_id = ? AND role = 'admin' ORDER BY id DESC LIMIT 1"
            );
            $uStmt->execute([$tenantId]);
            $userId = (int) $uStmt->fetchColumn();
            if ($userId > 0) {
                $this->pdo->prepare('UPDATE subscription_orders SET user_id = ? WHERE id = ?')
                    ->execute([$userId, $order['id']]);
            }

            $this->pdo->commit();

            $this->initTenantParameters($tenantId, $lockedOrder['company_name']);
            TenantSchema::finalizeIsolation();

            $invoice = (new SubscriptionInvoice())->ensureFromPaidOrder((int) $order['id']);

            return [
                'success' => true,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'order_id' => (int) $order['id'],
                'invoice_id' => $invoice['invoice_id'] ?? null,
                'invoice_number' => $invoice['invoice_number'] ?? null,
            ];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('SubscriptionCheckout: ' . $e->getMessage());
            if ((int) ($e->errorInfo[1] ?? 0) === 1205) {
                return [
                    'success' => false,
                    'message' => 'La base de données est occupée (verrou). Réessayez dans quelques secondes.',
                ];
            }
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            error_log('SubscriptionCheckout: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function activateSubscription(array $order, bool $initParams = true): int
    {
        $productLine = PharmaCommercial::normalizeProductLine($order['product_line'] ?? 'clinical');
        $isPharma = PharmaCommercial::isPharmaProductLine($productLine);
        $planSlug = $isPharma
            ? PharmaSubscriptionPlan::normalizeSlug($order['license_type'])
            : SubscriptionPlan::normalizeSlug($order['license_type']);
        $plan = $this->resolvePlan($planSlug, $productLine);
        $subSvc = SubscriptionService::getInstance();
        $isLifetime = $isPharma
            ? PharmaSubscriptionPlan::isLifetime($planSlug)
            : SubscriptionPlan::isLifetime($planSlug);

        if (!empty($order['tenant_id'])) {
            $tenantId = (int) $order['tenant_id'];
            if ($isPharma) {
                PharmaCommercial::syncTenantPlan($tenantId, $planSlug, $this->pdo);
            } else {
                $subSvc->syncTenantToPlan($tenantId, $planSlug, $this->pdo);
            }

            if ($isLifetime) {
                $this->pdo->prepare("
                    UPDATE tenants SET status = 'active', expires_at = NULL, license_type = 'lifetime',
                           is_demo = 0, auto_renew = 0, updated_at = NOW() WHERE id = ?
                ")->execute([$tenantId]);
            } else {
                $row = $this->pdo->prepare('SELECT expires_at FROM tenants WHERE id = ?');
                $row->execute([$tenantId]);
                $current = $row->fetch(PDO::FETCH_ASSOC);
                $baseTs = max(strtotime($current['expires_at'] ?? 'today'), time());
                $newExpires = date('Y-m-d', strtotime('+1 year', $baseTs));
                $this->pdo->prepare("
                    UPDATE tenants SET status = 'active', expires_at = ?, license_type = ?,
                           is_demo = 0, auto_renew = 1, updated_at = NOW() WHERE id = ?
                ")->execute([$newExpires, $planSlug, $tenantId]);
            }
            if ($isPharma) {
                PharmaCommercial::activateSuiteForTenant(
                    $tenantId,
                    null,
                    'Activation automatique — abonnement PharmaPro confirmé'
                );
            }
            return $tenantId;
        }

        $tenantPrefix = $isPharma ? 'PHR' : 'EFS';
        $tenantKey = $tenantPrefix . '-' . strtoupper(bin2hex(random_bytes(4))) . '-' . strtoupper(bin2hex(random_bytes(2)));
        $expiresAt = $isLifetime ? null : date('Y-m-d', strtotime('+1 year'));

        $stmt = $this->pdo->prepare("
            INSERT INTO tenants (tenant_key, company_name, email, phone, license_type, max_users, expires_at, status, is_demo, auto_renew)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0, ?)
        ");
        $stmt->execute([
            $tenantKey,
            $order['company_name'],
            $order['email'],
            $order['phone'],
            $planSlug,
            $plan['max_users'],
            $expiresAt,
            $isLifetime ? 0 : 1,
        ]);
        $tenantId = (int) $this->pdo->lastInsertId();
        if ($isPharma) {
            PharmaCommercial::syncTenantPlan($tenantId, $planSlug, $this->pdo);
        } else {
            $subSvc->syncTenantToPlan($tenantId, $planSlug, $this->pdo);
        }

        $username = $order['nom_utilisateur'] ?: $this->generateUsername($order['email']);
        $this->pdo->prepare("
            INSERT INTO utilisateurs (tenant_id, nom_utilisateur, email, mot_de_passe, role, statut)
            VALUES (?, ?, ?, ?, 'admin', 'actif')
        ")->execute([
            $tenantId,
            $username,
            $order['email'],
            $order['password_hash'] ?: password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT),
        ]);

        $this->persistTenantAdminPassword($tenantId, $order);

        if ($initParams) {
            $this->initTenantParameters($tenantId, $order['company_name']);
        }

        if ($isPharma) {
            PharmaCommercial::activateSuiteForTenant(
                $tenantId,
                null,
                'Activation automatique — abonnement PharmaPro confirmé'
            );
        }

        return $tenantId;
    }

    private function persistTenantAdminPassword(int $tenantId, array $order): void
    {
        if (!$this->tableHasColumn('tenants', 'admin_login_password')) {
            return;
        }
        $plain = trim((string) ($order['password_initial'] ?? ''));
        if ($plain === '') {
            return;
        }
        $this->pdo->prepare('UPDATE tenants SET admin_login_password = ? WHERE id = ?')
            ->execute([$plain, $tenantId]);
    }

    private function initTenantParameters(int $tenantId, string $companyName): void
    {
        if (!$this->tableHasColumn('parametres_systeme', 'tenant_id')) {
            return;
        }
        $defaults = [
            'nom_etablissement' => $companyName,
            'devise' => 'XOF',
            'timezone' => 'Africa/Bamako',
        ];
        foreach ($defaults as $cle => $valeur) {
            try {
                $check = $this->pdo->prepare(
                    'SELECT id FROM parametres_systeme WHERE cle = ? AND tenant_id = ? LIMIT 1'
                );
                $check->execute([$cle, $tenantId]);
                if (!$check->fetch()) {
                    $this->pdo->prepare(
                        'INSERT INTO parametres_systeme (tenant_id, cle, valeur) VALUES (?, ?, ?)'
                    )->execute([$tenantId, $cle, $valeur]);
                }
            } catch (PDOException $e) {
                error_log('initTenantParameters: ' . $e->getMessage());
            }
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function generateUsername(string $email): string
    {
        $base = preg_replace('/[^a-z0-9]/', '', strtolower(explode('@', $email)[0] ?? 'client'));
        $base = substr($base ?: 'client', 0, 20);
        return $base . '_' . random_int(100, 999);
    }

    /** @return array<int, array> */
    public function listPendingOrders(): array
    {
        return $this->pdo->query(
            "SELECT * FROM subscription_orders WHERE payment_status = 'pending' ORDER BY created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<int, array> */
    public function listAllTenants(): array
    {
        $hasAdminPwd = $this->tableHasColumn('tenants', 'admin_login_password');
        $hasOrderPwd = $this->tableHasColumn('subscription_orders', 'password_initial');
        $adminPwdExpr = $hasAdminPwd ? 't.admin_login_password' : 'NULL';
        $orderPwdExpr = $hasOrderPwd
            ? "(SELECT so.password_initial FROM subscription_orders so
                WHERE so.tenant_id = t.id AND so.payment_status = 'paid' AND so.password_initial IS NOT NULL
                ORDER BY so.paid_at DESC, so.id DESC LIMIT 1)"
            : 'NULL';

        return $this->pdo->query(
            "SELECT t.*,
                (SELECT COUNT(*) FROM utilisateurs u WHERE u.tenant_id = t.id AND u.statut = 'actif') AS users_count,
                (SELECT u.nom_utilisateur FROM utilisateurs u
                    WHERE u.tenant_id = t.id AND u.role = 'admin' AND u.statut = 'actif'
                    ORDER BY u.id ASC LIMIT 1) AS admin_username,
                (SELECT u.email FROM utilisateurs u
                    WHERE u.tenant_id = t.id AND u.role = 'admin' AND u.statut = 'actif'
                    ORDER BY u.id ASC LIMIT 1) AS admin_email,
                COALESCE({$adminPwdExpr}, {$orderPwdExpr}) AS admin_password_stored
             FROM tenants t ORDER BY t.created_at DESC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}
