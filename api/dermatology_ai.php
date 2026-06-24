<?php
/**
 * API de Diagnostic Dermatologique par IA
 * Classification des lésions cutanées basée sur HAM10000/ISIC
 * 
 * Endpoints :
 * - POST ?action=analyze : Analyser une image de lésion
 * - POST ?action=evaluate_abcde : Évaluer selon la règle ABCDE
 * - GET  ?action=get_lesion_info&code=xxx : Obtenir les infos d'un type de lésion
 * - GET  ?action=get_all_types : Obtenir tous les types de lésions
 * - POST ?action=save_diagnostic : Sauvegarder un diagnostic
 * - GET  ?action=get_history : Obtenir l'historique des diagnostics
 */

// Configuration des erreurs pour le développement
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Inclure les fichiers nécessaires
require_once '../config/config.php';
require_once '../includes/init.php';
require_once '../includes/DermatologyAI.php';

// Configuration CORS sécurisée
$allowedOrigins = [
    'http://localhost',
    'http://127.0.0.1',
    'https://efficasante.com',
    'capacitor://localhost', // Pour les apps mobiles Capacitor
    'ionic://localhost'      // Pour les apps Ionic
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins) || strpos($origin, 'localhost') !== false) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: *'); // Fallback pour dev
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');
header('Content-Type: application/json; charset=utf-8');

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Rate limiting simple (en production, utiliser Redis)
session_start();
$rateLimitKey = 'dermatology_api_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateLimit = 30; // requêtes par minute
$ratePeriod = 60; // secondes

if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'start' => time()];
}

$rateData = $_SESSION[$rateLimitKey];
if (time() - $rateData['start'] > $ratePeriod) {
    $_SESSION[$rateLimitKey] = ['count' => 1, 'start' => time()];
} else {
    $_SESSION[$rateLimitKey]['count']++;
    if ($_SESSION[$rateLimitKey]['count'] > $rateLimit) {
        sendResponse('error', 'Trop de requêtes. Veuillez patienter.', [], 429);
    }
}

// Récupérer l'action demandée
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Connexion à la base de données
$db = null;
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Erreur DB Dermatology API: " . $e->getMessage());
    sendResponse('error', 'Service temporairement indisponible', [], 503);
}

// Router les actions
switch ($action) {
    case 'analyze':
        analyzeImage();
        break;
    
    case 'analyze_with_features':
        analyzeWithFeatures();
        break;
    
    case 'evaluate_abcde':
        evaluateABCDE();
        break;
    
    case 'get_lesion_info':
        getLesionInfo();
        break;
    
    case 'get_all_types':
        getAllLesionTypes();
        break;
    
    case 'save_diagnostic':
        saveDiagnostic();
        break;
    
    case 'get_history':
        getHistory();
        break;
    
    case 'get_diagnostic':
        getDiagnostic();
        break;
    
    case 'health':
        sendResponse('success', 'API Dermatologie opérationnelle', [
            'version' => '2.0.0',
            'model' => 'HAM10000-based',
            'categories' => 7
        ]);
        break;
    
    default:
        sendResponse('error', 'Action non reconnue', ['available_actions' => [
            'analyze', 'analyze_with_features', 'evaluate_abcde', 
            'get_lesion_info', 'get_all_types', 'save_diagnostic', 
            'get_history', 'get_diagnostic', 'health'
        ]], 400);
        break;
}

/**
 * Analyser une image de lésion cutanée
 */
function analyzeImage() {
    // Récupérer les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || empty($data['image'])) {
        sendResponse('error', 'Image requise pour l\'analyse', [], 400);
    }
    
    // Valider le format de l'image
    $imageData = $data['image'];
    if (!preg_match('/^data:image\/(jpeg|png|gif|webp);base64,/', $imageData)) {
        sendResponse('error', 'Format d\'image invalide. Formats acceptés: JPEG, PNG, GIF, WebP', [], 400);
    }
    
    // Vérifier la taille de l'image (max 10MB)
    $imageSize = strlen(base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData)));
    if ($imageSize > 10 * 1024 * 1024) {
        sendResponse('error', 'Image trop volumineuse. Maximum 10MB.', [], 400);
    }
    
    // Récupérer les métadonnées optionnelles fournies par l'utilisateur
    $metadata = [
        'symmetry' => $data['symmetry'] ?? null,
        'border_regularity' => $data['border_regularity'] ?? null,
        'size_mm' => $data['size_mm'] ?? null,
        'texture' => $data['texture'] ?? null,
        'location' => $data['location'] ?? null,
        'patient_age' => $data['patient_age'] ?? null,
        'evolution_months' => $data['evolution_months'] ?? null
    ];
    
    // Filtrer les valeurs null
    $metadata = array_filter($metadata, function($v) { return $v !== null; });
    
    try {
        // Analyser l'image
        $result = DermatologyAI::analyzeImage($imageData, $metadata);
        
        sendResponse('success', 'Analyse terminée', [
            'predictions' => $result['predictions'],
            'report' => $result['report'],
            'features_detected' => $result['features'],
            'analysis_timestamp' => date('Y-m-d H:i:s'),
            'model_info' => [
                'name' => 'DermatologyAI HAM10000',
                'version' => '2.0.0',
                'categories' => 7
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur analyse dermato: " . $e->getMessage());
        sendResponse('error', 'Erreur lors de l\'analyse de l\'image', [], 500);
    }
}

/**
 * Analyser avec des caractéristiques fournies manuellement
 * Utile quand le modèle TensorFlow.js frontend extrait les features
 */
function analyzeWithFeatures() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data || empty($data['features'])) {
        sendResponse('error', 'Caractéristiques requises pour l\'analyse', [], 400);
    }
    
    $features = $data['features'];
    
    // Valider les caractéristiques
    $requiredFeatures = ['dominant_colors'];
    foreach ($requiredFeatures as $feature) {
        if (!isset($features[$feature])) {
            sendResponse('error', "Caractéristique manquante: $feature", [], 400);
        }
    }
    
    try {
        // Analyser les caractéristiques
        $predictions = DermatologyAI::analyzeFeatures($features);
        $report = DermatologyAI::generateReport($predictions);
        
        sendResponse('success', 'Analyse terminée', [
            'predictions' => $predictions,
            'report' => $report,
            'analysis_timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        error_log("Erreur analyse features: " . $e->getMessage());
        sendResponse('error', 'Erreur lors de l\'analyse', [], 500);
    }
}

/**
 * Évaluer selon la règle ABCDE
 */
function evaluateABCDE() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        sendResponse('error', 'Données ABCDE requises', [], 400);
    }
    
    // Extraire les critères
    $criteria = [
        'a' => $data['asymmetry'] ?? $data['a'] ?? false,
        'b' => $data['borders'] ?? $data['b'] ?? false,
        'c' => $data['color'] ?? $data['c'] ?? false,
        'd' => $data['diameter'] ?? $data['d'] ?? false,
        'e' => $data['evolution'] ?? $data['e'] ?? false
    ];
    
    $result = DermatologyAI::evaluateABCDE($criteria);
    
    sendResponse('success', 'Évaluation ABCDE terminée', $result);
}

/**
 * Obtenir les informations d'un type de lésion
 */
function getLesionInfo() {
    $code = $_GET['code'] ?? '';
    
    if (empty($code)) {
        sendResponse('error', 'Code de lésion requis', [], 400);
    }
    
    // Sanitize
    $code = preg_replace('/[^a-z]/i', '', strtolower($code));
    
    $info = DermatologyAI::getLesionInfo($code);
    
    if (!$info) {
        sendResponse('error', 'Type de lésion non trouvé', [
            'available_codes' => ['nv', 'mel', 'bkl', 'bcc', 'akiec', 'vasc', 'df']
        ], 404);
    }
    
    sendResponse('success', 'Informations récupérées', $info);
}

/**
 * Obtenir tous les types de lésions
 */
function getAllLesionTypes() {
    $types = DermatologyAI::getAllLesionTypes();
    
    // Simplifier pour la réponse API
    $simplified = [];
    foreach ($types as $code => $type) {
        $simplified[] = [
            'code' => $code,
            'nom' => $type['nom'],
            'nom_commun' => $type['nom_commun'],
            'gravite' => $type['gravite'],
            'urgence' => $type['urgence'],
            'prevalence' => $type['prevalence']
        ];
    }
    
    sendResponse('success', 'Types de lésions récupérés', [
        'count' => count($simplified),
        'types' => $simplified
    ]);
}

/**
 * Sauvegarder un diagnostic
 */
function saveDiagnostic() {
    global $db;
    
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        sendResponse('error', 'Données invalides', [], 400);
    }
    
    // Valider les données requises
    $required = ['image', 'predictions'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            sendResponse('error', "Champ requis manquant: $field", [], 400);
        }
    }
    
    try {
        // Créer la table si nécessaire
        ensureDiagnosticTableExists();
        
        // Sauvegarder l'image
        $uploadDir = '../uploads/dermatology_diagnostics/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $imageFileName = 'derm_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $imagePath = $uploadDir . $imageFileName;
        
        // Décoder et sauvegarder l'image
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $data['image']));
        if (!file_put_contents($imagePath, $imageData)) {
            sendResponse('error', 'Erreur lors de la sauvegarde de l\'image', [], 500);
        }
        
        // Préparer les données
        $topPrediction = $data['predictions'][0] ?? [];
        $patientId = $data['patient_id'] ?? null;
        $userId = $data['user_id'] ?? null;
        $notes = $data['notes'] ?? '';
        $abcdeScore = $data['abcde_score'] ?? null;
        
        // Insérer en base
        $stmt = $db->prepare("
            INSERT INTO dermatology_diagnostics 
            (image_path, lesion_code, lesion_name, confidence_score, 
             predictions_json, abcde_score, patient_id, user_id, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $imagePath,
            $topPrediction['code'] ?? 'unknown',
            $topPrediction['nom'] ?? 'Inconnu',
            $topPrediction['pourcentage'] ?? 0,
            json_encode($data['predictions']),
            $abcdeScore,
            $patientId,
            $userId,
            $notes
        ]);
        
        $diagnosticId = $db->lastInsertId();
        
        sendResponse('success', 'Diagnostic enregistré avec succès', [
            'diagnostic_id' => $diagnosticId,
            'image_path' => $imagePath
        ]);
        
    } catch (PDOException $e) {
        error_log("Erreur save diagnostic: " . $e->getMessage());
        sendResponse('error', 'Erreur lors de l\'enregistrement', [], 500);
    }
}

/**
 * Récupérer l'historique des diagnostics
 */
function getHistory() {
    global $db;
    
    try {
        ensureDiagnosticTableExists();
        
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $offset = max(0, intval($_GET['offset'] ?? 0));
        $patientId = $_GET['patient_id'] ?? null;
        
        $sql = "SELECT id, lesion_code, lesion_name, confidence_score, 
                       abcde_score, patient_id, created_at
                FROM dermatology_diagnostics";
        $params = [];
        
        if ($patientId) {
            $sql .= " WHERE patient_id = ?";
            $params[] = $patientId;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $diagnostics = $stmt->fetchAll();
        
        // Compter le total
        $countSql = "SELECT COUNT(*) FROM dermatology_diagnostics";
        if ($patientId) {
            $countSql .= " WHERE patient_id = ?";
            $total = $db->prepare($countSql);
            $total->execute([$patientId]);
        } else {
            $total = $db->query($countSql);
        }
        $totalCount = $total->fetchColumn();
        
        sendResponse('success', 'Historique récupéré', [
            'diagnostics' => $diagnostics,
            'count' => count($diagnostics),
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset
        ]);
        
    } catch (PDOException $e) {
        error_log("Erreur get history: " . $e->getMessage());
        sendResponse('error', 'Erreur lors de la récupération', [], 500);
    }
}

/**
 * Récupérer un diagnostic spécifique
 */
function getDiagnostic() {
    global $db;
    
    $id = intval($_GET['id'] ?? 0);
    
    if (!$id) {
        sendResponse('error', 'ID de diagnostic requis', [], 400);
    }
    
    try {
        ensureDiagnosticTableExists();
        
        $stmt = $db->prepare("SELECT * FROM dermatology_diagnostics WHERE id = ?");
        $stmt->execute([$id]);
        $diagnostic = $stmt->fetch();
        
        if (!$diagnostic) {
            sendResponse('error', 'Diagnostic non trouvé', [], 404);
        }
        
        // Décoder les prédictions
        $diagnostic['predictions'] = json_decode($diagnostic['predictions_json'], true);
        unset($diagnostic['predictions_json']);
        
        // Ajouter les infos détaillées sur le type de lésion
        $lesionInfo = DermatologyAI::getLesionInfo($diagnostic['lesion_code']);
        $diagnostic['lesion_details'] = $lesionInfo;
        
        sendResponse('success', 'Diagnostic récupéré', $diagnostic);
        
    } catch (PDOException $e) {
        error_log("Erreur get diagnostic: " . $e->getMessage());
        sendResponse('error', 'Erreur lors de la récupération', [], 500);
    }
}

/**
 * Créer la table de diagnostics si elle n'existe pas
 */
function ensureDiagnosticTableExists() {
    global $db;
    
    $sql = "
    CREATE TABLE IF NOT EXISTS dermatology_diagnostics (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(500) NOT NULL,
        lesion_code VARCHAR(20) NOT NULL,
        lesion_name VARCHAR(100) NOT NULL,
        confidence_score DECIMAL(5,2) NOT NULL,
        predictions_json TEXT NOT NULL,
        abcde_score INT DEFAULT NULL,
        patient_id INT DEFAULT NULL,
        user_id INT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_lesion_code (lesion_code),
        INDEX idx_patient_id (patient_id),
        INDEX idx_created_at (created_at),
        INDEX idx_confidence (confidence_score)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $db->exec($sql);
}

/**
 * Envoyer une réponse JSON
 */
function sendResponse(string $status, string $message, array $data = [], int $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = [
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
