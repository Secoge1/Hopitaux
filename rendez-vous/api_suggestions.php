<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/RendezVousIntelligence.php';

module_api_guard('rdv');

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $medecinId = $_GET['medecin_id'] ?? $_POST['medecin_id'] ?? null;
    $patientId = $_GET['patient_id'] ?? $_POST['patient_id'] ?? null;
    $typeConsultation = $_GET['type_consultation'] ?? $_POST['type_consultation'] ?? 'consultation_generale';
    $preferredDate = $_GET['preferred_date'] ?? $_POST['preferred_date'] ?? null;
    
    switch ($action) {
        case 'suggest_slots':
            if (!$medecinId || !$patientId) {
                jsonResponse(['error' => 'Médecin et patient requis'], 400);
            }
            
            $suggestions = RendezVousIntelligence::suggestOptimalSlots($medecinId, $patientId, $typeConsultation, $preferredDate);
            jsonResponse($suggestions);
            
        case 'analyze_schedule':
            if (!$medecinId) {
                jsonResponse(['error' => 'Médecin requis'], 400);
            }
            
            $analysis = RendezVousIntelligence::analyzeAndOptimize($medecinId, 7);
            jsonResponse($analysis);
            
        case 'detect_conflicts':
            if (!$medecinId) {
                jsonResponse(['error' => 'Médecin requis'], 400);
            }
            
            $conflicts = RendezVousIntelligence::detectConflicts($medecinId, 7);
            jsonResponse($conflicts);
            
        default:
            jsonResponse(['error' => 'Action non reconnue'], 400);
    }
    
} catch (Exception $e) {
    error_log("Erreur API rendez-vous: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur interne du serveur'], 500);
}
?>



