<?php
/**
 * API REST PharmaPro ERP — Application mobile (caisse, stock, dashboard).
 *
 * Routes via ?path= :
 *   POST login
 *   GET  dashboard
 *   GET  products
 *   GET  products/barcode/{code}  → ?path=products/barcode&code=
 *   POST sales
 *   GET  sales/recent
 *   GET  stock/alerts
 *   GET  features
 *   GET  products/detail       → ?path=products/detail&id=
 *   POST products/barcode/primary  → { product_id, barcode }
 *   POST products/barcode/add      → { product_id, barcode }
 *   POST products/barcode/remove   → { product_id, barcode }
 *   GET  reports/bilan         → date_from, date_to
 *   GET  reports/grand_livre   → date_from, date_to
 *   GET  reports/bilan/pdf
 *   GET  reports/grand_livre/pdf
 */
define('EFFICASANTE_ROOT', realpath(__DIR__ . '/../../..'));

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once EFFICASANTE_ROOT . '/config/db.php';
require_once EFFICASANTE_ROOT . '/includes/init.php';
require_once EFFICASANTE_ROOT . '/includes/pharma_erp/bootstrap.php';
require_once EFFICASANTE_ROOT . '/includes/saas/PlatformTenantFeatures.php';

try {
    $pdoBoot = getDB();
    $pdoBoot->exec("CREATE TABLE IF NOT EXISTS api_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    // Ignorer si la table existe déjà ou si la connexion échoue au boot
}

function pe_api_json($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function pe_api_error(string $message, int $code = 400): void
{
    pe_api_json(['success' => false, 'error' => $message], $code);
}

function pe_api_bearer(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\s+(.+)$/i', $auth, $m)) {
        return trim($m[1]);
    }
    return null;
}

function pe_api_user(): array
{
    $token = pe_api_bearer();
    if (!$token) {
        pe_api_error('Token manquant', 401);
    }
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT user_id FROM api_tokens WHERE token = ? AND expires_at > NOW()');
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        pe_api_error('Token invalide ou expiré', 401);
    }
    $stmt = $pdo->prepare('SELECT id, nom_utilisateur, email, role, tenant_id FROM utilisateurs WHERE id = ? AND statut = \'actif\'');
    $stmt->execute([(int) $row['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        pe_api_error('Utilisateur inactif', 401);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_connected'] = true;
    $_SESSION['user_role'] = $user['role'];
    if (!empty($user['tenant_id'])) {
        require_once EFFICASANTE_ROOT . '/includes/saas/TenantContext.php';
        TenantContext::setTenantId((int) $user['tenant_id']);
        $_SESSION['tenant_id'] = (int) $user['tenant_id'];
    }

    if (!pharma_erp_feature_enabled((int) ($user['tenant_id'] ?? 0))) {
        pe_api_error('PharmaPro ERP non activé pour cet établissement', 403);
    }

    pharma_erp_require_feature((int) ($user['tenant_id'] ?? 0));

    $roles = pharma_erp_allowed_roles();
    if (!in_array($user['role'], $roles, true)) {
        pe_api_error('Rôle non autorisé pour PharmaPro', 403);
    }

    return $user;
}

function pe_api_do_login(): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    if ($email === '' || $password === '') {
        pe_api_error('Email et mot de passe requis', 400);
    }

    require_once EFFICASANTE_ROOT . '/config/database.php';
    require_once EFFICASANTE_ROOT . '/models/Utilisateur.php';
    $db = (new Database())->getConnection();
    if (!$db) {
        pe_api_error('Erreur base de données', 500);
    }
    $user = (new Utilisateur($db))->authentifier($email, $password);
    if (!$user) {
        pe_api_error('Identifiants incorrects', 401);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    if (!empty($user['tenant_id'])) {
        $_SESSION['tenant_id'] = (int) $user['tenant_id'];
        require_once EFFICASANTE_ROOT . '/includes/saas/TenantContext.php';
        TenantContext::setTenantId((int) $user['tenant_id']);
    }

    if (!pharma_erp_feature_enabled((int) ($user['tenant_id'] ?? 0))) {
        pe_api_error('PharmaPro ERP non activé', 403);
    }

    if (!in_array($user['role'], pharma_erp_allowed_roles(), true)) {
        pe_api_error('Rôle non autorisé pour PharmaPro', 403);
    }

    $pdo = getDB();
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400 * 30);
    $pdo->prepare('INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, ?)')->execute([(int) $user['id'], $token, $expires]);

    pe_api_json([
        'success' => true,
        'token' => $token,
        'expires_at' => $expires,
        'user' => [
            'id' => (int) $user['id'],
            'nom_utilisateur' => $user['nom_utilisateur'],
            'email' => $user['email'],
            'role' => $user['role'],
        ],
        'module' => 'pharma_erp',
    ]);
}

$path = trim($_GET['path'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];

if ($path === 'login' && $method === 'POST') {
    pe_api_do_login();
}

$user = pe_api_user();

require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeProduct.php';
require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeSale.php';
require_once EFFICASANTE_ROOT . '/models/pharma_erp/PePharmacy.php';
require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeStock.php';

function pe_api_report_dates(): array
{
    return [
        $_GET['date_from'] ?? date('Y-01-01'),
        $_GET['date_to'] ?? date('Y-m-d'),
    ];
}

function pe_api_require_accounting_role(array $user): void
{
    if (!in_array($user['role'], ['admin', 'comptable'], true)) {
        pe_api_error('Rôle comptable requis pour les rapports', 403);
    }
}

function pe_api_require_barcode_edit_role(array $user): void
{
    if (!in_array($user['role'], ['admin', 'pharmacien'], true)) {
        pe_api_error('Seuls admin et pharmacien peuvent modifier les codes-barres', 403);
    }
}

function pe_api_barcode_payload(): array
{
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $productId = (int) ($input['product_id'] ?? 0);
    $barcode = trim((string) ($input['barcode'] ?? ''));
    if ($productId <= 0 || $barcode === '') {
        pe_api_error('product_id et barcode requis', 400);
    }
    return [$productId, $barcode];
}

switch ($path) {
    case 'dashboard':
        if ($method === 'GET') {
            pe_api_json(['success' => true, 'data' => (new PeSale())->getDashboardStats(30)]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'products/barcode':
        if ($method !== 'GET') {
            pe_api_error('Méthode non autorisée', 405);
        }
        $code = trim($_GET['code'] ?? '');
        if ($code === '') {
            pe_api_error('Code-barres requis', 400);
        }
        $product = (new PeProduct())->findByBarcode($code);
        if (!$product) {
            pe_api_error('Produit non trouvé', 404);
        }
        pe_api_json(['success' => true, 'data' => $product]);

    case 'products/detail':
        if ($method !== 'GET') {
            pe_api_error('Méthode non autorisée', 405);
        }
        $productId = (int) ($_GET['id'] ?? 0);
        if ($productId <= 0) {
            pe_api_error('ID produit requis', 400);
        }
        $detail = (new PeProduct())->findDetail($productId);
        if (!$detail) {
            pe_api_error('Produit non trouvé', 404);
        }
        pe_api_json(['success' => true, 'data' => $detail]);

    case 'products/barcode/primary':
        if ($method === 'POST') {
            pe_api_require_barcode_edit_role($user);
            [$productId, $barcode] = pe_api_barcode_payload();
            try {
                (new PeProduct())->setPrimaryBarcode($productId, $barcode);
                pe_api_json([
                    'success' => true,
                    'data' => (new PeProduct())->findDetail($productId),
                ]);
            } catch (Throwable $e) {
                pe_api_error($e->getMessage(), 422);
            }
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'products/barcode/add':
        if ($method === 'POST') {
            pe_api_require_barcode_edit_role($user);
            [$productId, $barcode] = pe_api_barcode_payload();
            try {
                (new PeProduct())->addSecondaryBarcode($productId, $barcode);
                pe_api_json([
                    'success' => true,
                    'data' => (new PeProduct())->findDetail($productId),
                ]);
            } catch (Throwable $e) {
                pe_api_error($e->getMessage(), 422);
            }
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'products/barcode/remove':
        if ($method === 'POST') {
            pe_api_require_barcode_edit_role($user);
            [$productId, $barcode] = pe_api_barcode_payload();
            $model = new PeProduct();
            if (!$model->removeBarcode($productId, $barcode)) {
                pe_api_error('Code-barres introuvable sur ce produit', 404);
            }
            pe_api_json(['success' => true, 'data' => $model->findDetail($productId)]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'products':
        if ($method !== 'GET') {
            pe_api_error('Méthode non autorisée', 405);
        }
        $productModel = new PeProduct();
        $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $limit = min(50, max(5, (int) ($_GET['limit'] ?? 20)));
        pe_api_json([
            'success' => true,
            'data' => $productModel->getAll($page, $limit, $search),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $productModel->getCount($search),
            ],
        ]);

    case 'sales/recent':
        if ($method === 'GET') {
            pe_api_json(['success' => true, 'data' => (new PeSale())->getRecentSales(15)]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'sales':
        if ($method === 'GET') {
            pe_api_json(['success' => true, 'data' => (new PeSale())->getRecentSales(20)]);
        }
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $pharmacyModel = new PePharmacy();
            $pharmacy = $pharmacyModel->getDefault();
            if (!$pharmacy) {
                pe_api_error('Officine non configurée', 422);
            }
            $depositId = $pharmacyModel->getDefaultDepositId((int) $pharmacy['id']);
            $registerId = $pharmacyModel->getDefaultRegisterId((int) $pharmacy['id']);
            if (!$depositId || !$registerId) {
                pe_api_error('Caisse ou dépôt manquant', 422);
            }
            try {
                $result = (new PeSale())->createSale(
                    (int) $pharmacy['id'],
                    $registerId,
                    $depositId,
                    $input['lines'] ?? [],
                    array_merge(
                        ['method' => 'cash', 'amount' => $input['amount_paid'] ?? 0],
                        is_array($input['payment'] ?? null) ? $input['payment'] : []
                    ),
                    $input['customer_name'] ?? null
                );
                pe_api_json(['success' => true, 'sale' => $result], 201);
            } catch (Throwable $e) {
                pe_api_error($e->getMessage(), 422);
            }
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'stock/alerts':
        if ($method === 'GET') {
            pe_api_json([
                'success' => true,
                'data' => [
                    'low_stock' => (new PeProduct())->getLowStock(20),
                    'expiry' => (new PeStock())->getExpiryAlerts(90, 20),
                ],
            ]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'reports/bilan':
        if ($method === 'GET') {
            pe_api_require_accounting_role($user);
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeReporting.php';
            [$dateFrom, $dateTo] = pe_api_report_dates();
            pe_api_json(['success' => true, 'data' => (new PeReporting())->getBilan($dateFrom, $dateTo)]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'reports/grand_livre':
        if ($method === 'GET') {
            pe_api_require_accounting_role($user);
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeReporting.php';
            [$dateFrom, $dateTo] = pe_api_report_dates();
            $reporting = new PeReporting();
            pe_api_json([
                'success' => true,
                'data' => [
                    'summary' => $reporting->getGrandLivreGrouped($dateFrom, $dateTo),
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                ],
            ]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'reports/bilan/pdf':
        if ($method === 'GET') {
            pe_api_require_accounting_role($user);
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeReporting.php';
            require_once EFFICASANTE_ROOT . '/includes/pharma_erp/pdf_reports.php';
            [$dateFrom, $dateTo] = pe_api_report_dates();
            header('Content-Type: application/pdf');
            pharma_erp_render_bilan_pdf((new PeReporting())->getBilan($dateFrom, $dateTo));
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'reports/grand_livre/pdf':
        if ($method === 'GET') {
            pe_api_require_accounting_role($user);
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeReporting.php';
            require_once EFFICASANTE_ROOT . '/includes/pharma_erp/pdf_reports.php';
            [$dateFrom, $dateTo] = pe_api_report_dates();
            header('Content-Type: application/pdf');
            $grouped = (new PeReporting())->getGrandLivreGrouped($dateFrom, $dateTo);
            pharma_erp_render_grand_livre_pdf($grouped, $dateFrom, $dateTo);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'features':
        if ($method === 'GET') {
            pe_api_json([
                'success' => true,
                'data' => [
                    'pharma_erp_suite' => true,
                    'payment_finance_sync' => function_exists('payment_finance_sync_enabled') && payment_finance_sync_enabled(),
                ],
            ]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'promotions':
        if ($method === 'GET') {
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PePromotion.php';
            pe_api_json(['success' => true, 'data' => (new PePromotion())->getActive()]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'promotions/validate':
        if ($method === 'GET') {
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PePromotion.php';
            $code = trim($_GET['code'] ?? '');
            $subtotal = (float) ($_GET['subtotal'] ?? 0);
            if ($code === '') {
                pe_api_error('Code requis', 400);
            }
            $promoModel = new PePromotion();
            $promo = $promoModel->findByCode($code);
            if (!$promo) {
                pe_api_error('Code promo invalide', 404);
            }
            pe_api_json([
                'success' => true,
                'data' => [
                    'promo' => $promo,
                    'discount' => $promoModel->calculateDiscount($promo, $subtotal),
                ],
            ]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'prescriptions':
        if ($method === 'GET') {
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeMedical.php';
            $status = trim($_GET['status'] ?? '');
            pe_api_json(['success' => true, 'data' => (new PeMedical())->getPrescriptions(1, 30, $status)]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'inventory':
        if ($method === 'GET') {
            require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeInventory.php';
            pe_api_json(['success' => true, 'data' => (new PeInventory())->getAll(1, 20)]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'customers':
        require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeCustomer.php';
        $customerModel = new PeCustomer();
        if ($method === 'GET') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
            pe_api_json([
                'success' => true,
                'data' => $customerModel->getAll($page, 25, $search),
                'pagination' => [
                    'page' => $page,
                    'total' => $customerModel->getCount($search),
                ],
            ]);
        }
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            try {
                $id = $customerModel->create($input);
                pe_api_json(['success' => true, 'data' => $customerModel->findById($id)], 201);
            } catch (Throwable $e) {
                pe_api_error($e->getMessage(), 422);
            }
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'customers/detail':
        if ($method !== 'GET') {
            pe_api_error('Méthode non autorisée', 405);
        }
        require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeCustomer.php';
        $customerId = (int) ($_GET['id'] ?? 0);
        if ($customerId <= 0) {
            pe_api_error('ID client requis', 400);
        }
        $customer = (new PeCustomer())->findById($customerId);
        if (!$customer) {
            pe_api_error('Client introuvable', 404);
        }
        pe_api_json(['success' => true, 'data' => $customer]);

    case 'returns':
        require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeReturn.php';
        $returnModel = new PeReturn();
        if ($method === 'GET') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
            pe_api_json([
                'success' => true,
                'data' => $returnModel->getAll($page, 25, $search),
                'pagination' => [
                    'page' => $page,
                    'total' => $returnModel->getCount($search),
                ],
            ]);
        }
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $saleId = (int) ($input['sale_id'] ?? 0);
            $lines = $input['lines'] ?? [];
            if ($saleId <= 0 || !is_array($lines) || empty($lines)) {
                pe_api_error('sale_id et lines requis', 400);
            }
            try {
                $result = $returnModel->createFromSale($saleId, $lines, $input['reason'] ?? null);
                pe_api_json(['success' => true, 'data' => $result], 201);
            } catch (Throwable $e) {
                pe_api_error($e->getMessage(), 422);
            }
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'returns/detail':
        if ($method !== 'GET') {
            pe_api_error('Méthode non autorisée', 405);
        }
        require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeReturn.php';
        $returnId = (int) ($_GET['id'] ?? 0);
        if ($returnId <= 0) {
            pe_api_error('ID retour requis', 400);
        }
        $ret = (new PeReturn())->getById($returnId);
        if (!$ret) {
            pe_api_error('Retour introuvable', 404);
        }
        pe_api_json(['success' => true, 'data' => $ret]);

    case 'supplier-invoices':
        if (!in_array($user['role'], ['admin', 'comptable', 'pharmacien', 'pharma_manager'], true)) {
            pe_api_error('Rôle non autorisé pour les factures fournisseur', 403);
        }
        require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeSupplierInvoice.php';
        $invoiceModel = new PeSupplierInvoice();
        if ($method === 'GET') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $status = trim($_GET['status'] ?? '');
            $search = trim($_GET['q'] ?? $_GET['search'] ?? '');
            pe_api_json([
                'success' => true,
                'data' => $invoiceModel->getAll($page, 25, $status, $search),
                'summary' => $invoiceModel->getSummary(),
                'pagination' => [
                    'page' => $page,
                    'total' => $invoiceModel->getCount($status, $search),
                ],
            ]);
        }
        pe_api_error('Méthode non autorisée', 405);

    case 'supplier-invoices/pay':
        if ($method !== 'POST') {
            pe_api_error('Méthode non autorisée', 405);
        }
        if (!in_array($user['role'], ['admin', 'comptable'], true)) {
            pe_api_error('Rôle comptable requis pour payer une facture', 403);
        }
        require_once EFFICASANTE_ROOT . '/models/pharma_erp/PeSupplierInvoice.php';
        $input = json_decode(file_get_contents('php://input'), true) ?: [];
        $invoiceId = (int) ($input['invoice_id'] ?? 0);
        $amount = (float) ($input['amount'] ?? 0);
        if ($invoiceId <= 0 || $amount <= 0) {
            pe_api_error('invoice_id et amount requis', 400);
        }
        try {
            (new PeSupplierInvoice())->recordPayment($invoiceId, $amount, $input['reference'] ?? null);
            pe_api_json(['success' => true]);
        } catch (Throwable $e) {
            pe_api_error($e->getMessage(), 422);
        }

    default:
        pe_api_error('Route non trouvée. path=dashboard|products|sales|customers|returns|supplier-invoices|promotions|prescriptions|inventory|reports/bilan|stock/alerts|features', 404);
}
