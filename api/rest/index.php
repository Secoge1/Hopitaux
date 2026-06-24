<?php
/**
 * API REST Efficasante - Application mobile Flutter
 * Authentification par token Bearer. Toutes les réponses en JSON.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Répertoire racine du projet (2 niveaux au-dessus de api/rest)
define('EFFICASANTE_ROOT', realpath(__DIR__ . '/../..'));

require_once EFFICASANTE_ROOT . '/config/db.php';
require_once EFFICASANTE_ROOT . '/config/database.php';
require_once EFFICASANTE_ROOT . '/models/Utilisateur.php';
require_once EFFICASANTE_ROOT . '/models/Patient.php';
require_once EFFICASANTE_ROOT . '/models/RendezVous.php';
require_once EFFICASANTE_ROOT . '/models/Consultation.php';
require_once EFFICASANTE_ROOT . '/models/Laboratoire.php';
require_once EFFICASANTE_ROOT . '/includes/init.php';

// Créer la table api_tokens si nécessaire
try {
    $pdo = getDB();
    $pdo->exec("CREATE TABLE IF NOT EXISTS api_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_token (token),
        INDEX idx_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError($message, $code = 400) {
    jsonResponse(['success' => false, 'error' => $message], $code);
}

function getBearerToken() {
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        if (preg_match('/Bearer\s+(.+)$/i', $headers['Authorization'], $m)) {
            return trim($m[1]);
        }
    }
    return null;
}

function validateToken($pdo) {
    $token = getBearerToken();
    if (!$token) {
        jsonError('Token manquant', 401);
    }
    $stmt = $pdo->prepare("SELECT user_id, expires_at FROM api_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonError('Token invalide ou expiré', 401);
    }
    $stmt = $pdo->prepare("SELECT id, nom_utilisateur, email, role, statut, tenant_id FROM utilisateurs WHERE id = ? AND statut = 'actif'");
    $stmt->execute([$row['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        jsonError('Utilisateur inactif', 401);
    }
    bindApiUserContext($user);
    return $user;
}

function bindApiUserContext(array $user): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_connected'] = true;

    if (!empty($user['tenant_id'])) {
        require_once EFFICASANTE_ROOT . '/includes/saas/TenantContext.php';
        TenantContext::setTenantId((int) $user['tenant_id']);
    }
}

// Route : /api/rest/login (POST)
function doLogin() {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    if (empty($email) || empty($password)) {
        jsonError('Email et mot de passe requis', 400);
    }
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        jsonError('Erreur base de données', 500);
    }
    $userModel = new Utilisateur($db);
    $user = $userModel->authentifier($email, $password);
    if (!$user) {
        jsonError('Email ou mot de passe incorrect', 401);
    }
    $pdo = getDB();
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 86400 * 30); // 30 jours
    $stmt = $pdo->prepare("INSERT INTO api_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$user['id'], $token, $expires]);
    unset($user['mot_de_passe']);
    bindApiUserContext($user);
    jsonResponse([
        'success' => true,
        'token' => $token,
        'expires_at' => $expires,
        'user' => [
            'id' => (int)$user['id'],
            'nom_utilisateur' => $user['nom_utilisateur'],
            'email' => $user['email'],
            'role' => $user['role'],
            'statut' => $user['statut'],
        ]
    ]);
}

// Route : /api/rest/dashboard/stats (GET)
function doDashboardStats($user) {
    $stats = getDashboardStats();
    jsonResponse(['success' => true, 'data' => $stats]);
}

// Route : /api/rest/patients (GET list, GET ?id= pour détail)
function doPatients($user, $pdo) {
    $patientModel = new Patient();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    if ($id) {
        $patient = $patientModel->getById($id);
        if (!$patient) {
            jsonError('Patient non trouvé', 404);
        }
        jsonResponse(['success' => true, 'data' => $patient]);
    }
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $search = trim($_GET['search'] ?? '');
    $statut = trim($_GET['statut'] ?? '');
    $list = $patientModel->getAll($page, $limit, $search, $statut);
    $total = $patientModel->getCount($search, $statut);
    jsonResponse([
        'success' => true,
        'data' => $list,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
        ]
    ]);
}

// Route : /api/rest/rendez-vous (GET)
function doRendezVous($user, $pdo) {
    $rdvModel = new RendezVous();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $search = trim($_GET['search'] ?? '');
    $statut = trim($_GET['statut'] ?? '');
    $date = trim($_GET['date'] ?? '');
    $list = $rdvModel->getAll($page, $limit, $search, $statut, $date);
    $total = $rdvModel->getCount($search, $statut, $date);
    jsonResponse([
        'success' => true,
        'data' => $list,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
        ]
    ]);
}

// Route : /api/rest/consultations (GET)
function doConsultations($user, $pdo) {
    $consultModel = new Consultation();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 20)));
    $search = trim($_GET['search'] ?? '');
    $statut = trim($_GET['statut'] ?? '');
    $date = trim($_GET['date'] ?? '');
    $list = $consultModel->getAll($page, $limit, $search, $statut, $date);
    $total = $consultModel->getCount($search, $statut, $date);
    jsonResponse([
        'success' => true,
        'data' => $list,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => (int)ceil($total / $limit),
        ]
    ]);
}

// Route : /api/rest/tenant/notices (GET) — avis feature flags (badge mobile)
function doTenantNotices(array $user): void
{
    require_once EFFICASANTE_ROOT . '/includes/saas/PlatformTenantFeatures.php';
    require_once EFFICASANTE_ROOT . '/includes/saas/saas_helpers.php';

    $key = PlatformTenantFeatures::PAYMENT_FINANCE_SYNC;
    $enabled = payment_finance_sync_enabled();
    $stamp = $enabled ? PlatformTenantFeatures::getEnabledStamp($key) : null;

    jsonResponse([
        'success' => true,
        'data' => [
            'user_id' => (int) $user['id'],
            'notices' => [
                [
                    'key' => $key,
                    'enabled' => $enabled,
                    'stamp' => $stamp,
                    'title' => 'Nouveau — synchronisation Paiements & Comptabilité',
                    'message' => 'Générez un paiement depuis une consultation ou une analyse labo. '
                        . 'Au statut Payé, l\'écriture comptable est créée automatiquement.',
                    'duration_ms' => 10000,
                ],
            ],
        ],
    ]);
}

// Route : /api/rest/laboratoire (GET)
function doLaboratoire($user) {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        jsonError('Erreur base de données', 500);
    }
    $labModel = new Laboratoire($db);
    $limit = min(50, max(5, (int)($_GET['limit'] ?? 30)));
    $statut = trim($_GET['statut'] ?? '');
    $search = trim($_GET['search'] ?? '');
    if ($search !== '') {
        $list = $labModel->search($search);
        $list = array_slice($list, 0, $limit);
    } elseif ($statut !== '') {
        $list = $labModel->getByStatus($statut);
        $list = array_slice($list, 0, $limit);
    } else {
        $list = $labModel->getRecent($limit);
    }
    $total = $labModel->getCount();
    jsonResponse([
        'success' => true,
        'data' => $list,
        'pagination' => [
            'limit' => $limit,
            'total' => (int)$total,
        ]
    ]);
}

// Router
$path = isset($_GET['path']) ? trim($_GET['path'], '/') : '';
$method = $_SERVER['REQUEST_METHOD'];

if ($path === 'login' && $method === 'POST') {
    doLogin();
}

$pdo = getDB();
$user = validateToken($pdo);

switch ($path) {
    case 'dashboard/stats':
        if ($method === 'GET') {
            doDashboardStats($user);
        }
        break;
    case 'patients':
        if ($method === 'GET') {
            doPatients($user, $pdo);
        }
        break;
    case 'rendez-vous':
        if ($method === 'GET') {
            doRendezVous($user, $pdo);
        }
        break;
    case 'consultations':
        if ($method === 'GET') {
            doConsultations($user, $pdo);
        }
        break;
    case 'tenant/notices':
        if ($method === 'GET') {
            doTenantNotices($user);
        }
        break;
    case 'laboratoire':
        if ($method === 'GET') {
            doLaboratoire($user);
        }
        break;
    default:
        jsonError('Route non trouvée. Utilisez ?path=login|dashboard/stats|tenant/notices|patients|rendez-vous|consultations|laboratoire', 404);
}

jsonError('Méthode non autorisée', 405);
