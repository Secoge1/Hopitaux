<?php
/**
 * API pour les diagnostics IA
 * Gère la sauvegarde et la récupération des analyses d'images médicales
 */

// Inclure les fichiers de configuration
require_once '../config/config.php';
require_once '../includes/init.php';

// Configuration CORS pour permettre les requêtes depuis l'app mobile
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Récupérer l'action demandée
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Connexion à la base de données
try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    sendResponse('error', 'Erreur de connexion à la base de données', ['error' => $e->getMessage()]);
}

// Router les actions
switch ($action) {
    case 'save_ai_diagnostic':
        saveAIDiagnostic();
        break;
    
    case 'get_diagnostics':
        getDiagnostics();
        break;
    
    case 'get_diagnostic_details':
        getDiagnosticDetails();
        break;
    
    default:
        sendResponse('error', 'Action non reconnue');
        break;
}

/**
 * Sauvegarder un diagnostic IA
 */
function saveAIDiagnostic() {
    global $db;
    
    // Récupérer les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        sendResponse('error', 'Données invalides');
    }
    
    // Extraire les données
    $imageData = $data['image'] ?? '';
    $predictions = $data['predictions'] ?? [];
    $timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
    
    if (empty($imageData) || empty($predictions)) {
        sendResponse('error', 'Image ou prédictions manquantes');
    }
    
    try {
        // Créer le dossier de stockage si nécessaire
        $uploadDir = '../uploads/ia_diagnostics/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Sauvegarder l'image
        $imageFileName = 'ia_diag_' . uniqid() . '.jpg';
        $imagePath = $uploadDir . $imageFileName;
        
        // Décoder l'image base64
        $imageDataDecoded = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $imageData));
        file_put_contents($imagePath, $imageDataDecoded);
        
        // Préparer les données pour la base de données
        $topPrediction = $predictions[0];
        $diagnosticPrincipal = $topPrediction['className'];
        $confiance = round($topPrediction['probability'] * 100, 2);
        $predictionsJson = json_encode($predictions);
        
        // Insérer dans la base de données
        $stmt = $db->prepare("
            INSERT INTO ia_diagnostics_realtime 
            (image_path, diagnostic_principal, confiance_score, predictions_json, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $imagePath,
            $diagnosticPrincipal,
            $confiance,
            $predictionsJson
        ]);
        
        $diagnosticId = $db->lastInsertId();
        
        sendResponse('success', 'Diagnostic enregistré avec succès', [
            'diagnostic_id' => $diagnosticId,
            'image_path' => $imagePath,
            'confiance' => $confiance
        ]);
        
    } catch (PDOException $e) {
        // Si la table n'existe pas, la créer automatiquement
        if (strpos($e->getMessage(), 'Base table or view not found') !== false) {
            createDiagnosticTable();
            // Réessayer
            saveAIDiagnostic();
        } else {
            sendResponse('error', 'Erreur lors de l\'enregistrement', ['error' => $e->getMessage()]);
        }
    } catch (Exception $e) {
        sendResponse('error', 'Erreur lors de la sauvegarde de l\'image', ['error' => $e->getMessage()]);
    }
}

/**
 * Récupérer la liste des diagnostics
 */
function getDiagnostics() {
    global $db;
    
    try {
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        $stmt = $db->prepare("
            SELECT 
                id,
                diagnostic_principal,
                confiance_score,
                created_at,
                image_path
            FROM ia_diagnostics_realtime
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        
        $stmt->execute([$limit, $offset]);
        $diagnostics = $stmt->fetchAll();
        
        sendResponse('success', 'Diagnostics récupérés', [
            'diagnostics' => $diagnostics,
            'count' => count($diagnostics)
        ]);
        
    } catch (PDOException $e) {
        sendResponse('error', 'Erreur lors de la récupération', ['error' => $e->getMessage()]);
    }
}

/**
 * Récupérer les détails d'un diagnostic
 */
function getDiagnosticDetails() {
    global $db;
    
    $diagnosticId = $_GET['id'] ?? 0;
    
    if (!$diagnosticId) {
        sendResponse('error', 'ID de diagnostic manquant');
    }
    
    try {
        $stmt = $db->prepare("
            SELECT *
            FROM ia_diagnostics_realtime
            WHERE id = ?
        ");
        
        $stmt->execute([$diagnosticId]);
        $diagnostic = $stmt->fetch();
        
        if (!$diagnostic) {
            sendResponse('error', 'Diagnostic non trouvé');
        }
        
        // Décoder les prédictions JSON
        $diagnostic['predictions'] = json_decode($diagnostic['predictions_json'], true);
        
        sendResponse('success', 'Diagnostic récupéré', ['diagnostic' => $diagnostic]);
        
    } catch (PDOException $e) {
        sendResponse('error', 'Erreur lors de la récupération', ['error' => $e->getMessage()]);
    }
}

/**
 * Créer la table de diagnostics si elle n'existe pas
 */
function createDiagnosticTable() {
    global $db;
    
    $sql = "
    CREATE TABLE IF NOT EXISTS ia_diagnostics_realtime (
        id INT AUTO_INCREMENT PRIMARY KEY,
        image_path VARCHAR(500) NOT NULL,
        diagnostic_principal VARCHAR(255) NOT NULL,
        confiance_score DECIMAL(5,2) NOT NULL,
        predictions_json TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_created_at (created_at),
        INDEX idx_confiance (confiance_score)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ";
    
    try {
        $db->exec($sql);
    } catch (PDOException $e) {
        error_log("Erreur création table: " . $e->getMessage());
    }
}

/**
 * Envoyer une réponse JSON
 */
function sendResponse($status, $message, $data = []) {
    $response = [
        'status' => $status,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit();
}
?>



