<?php
/**
 * API pour les suggestions de diagnostic intelligent
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/DiagnosticIntelligence.php';
require_once __DIR__ . '/../includes/MistralAIService.php';

module_api_guard('consultations');

// Fonction pour retourner une réponse JSON
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Méthode non autorisée'], 405);
}

try {
    // Récupérer les paramètres
    $symptomes = $_GET['symptomes'] ?? $_POST['symptomes'] ?? '';
    $patientId = $_GET['patient_id'] ?? $_POST['patient_id'] ?? null;
    $patientAge = null;
    $patientSexe = null;
    
    // Si un patient est spécifié, récupérer ses informations pour personnaliser
    $patientAntecedents = null;
    $patientAllergies = null;
    
    if ($patientId && is_numeric($patientId)) {
        require_once '../models/Patient.php';
        $patientModel = new Patient();
        $patient = $patientModel->getById($patientId);
        
        if ($patient) {
            // Calculer l'âge
            if (!empty($patient['date_naissance'])) {
                $birthDate = new DateTime($patient['date_naissance']);
                $today = new DateTime();
                $patientAge = $today->diff($birthDate)->y;
            }
            
            $patientSexe = $patient['sexe'] ?? null;
            $patientAntecedents = $patient['antecedents_medicaux'] ?? null;
            $patientAllergies = $patient['allergies'] ?? null;
        }
    }
    
    // Valider les symptômes
    if (empty($symptomes)) {
        jsonResponse(['error' => 'Symptômes requis'], 400);
    }
    
    // Minimum de 3 caractères pour déclencher l'analyse
    if (strlen(trim($symptomes)) < 3) {
        jsonResponse(['error' => 'Symptômes trop courts (minimum 3 caractères)'], 400);
    }
    
    // Obtenir les suggestions intelligentes avec vérification des antécédents
    $suggestions = DiagnosticIntelligence::getContextualSuggestions($symptomes, $patientAge, $patientSexe, $patientAntecedents, $patientAllergies);
    
    if (isset($suggestions['error'])) {
        jsonResponse(['error' => $suggestions['error']], 400);
    }

    $mistral = MistralAIService::getInstance();
    $mistralMeta = ['enriched' => false];
    if ($mistral->isEnabledForConsultations()) {
        $mistralMeta = $mistral->enrichConsultationDiagnostic(
            $suggestions,
            $symptomes,
            $patientAge,
            $patientSexe,
            $patientAntecedents,
            $patientAllergies
        );
        if (!empty($mistralMeta['enriched'])) {
            DiagnosticIntelligence::refreshSafetyChecksForSuggestions(
                $suggestions,
                $patientAntecedents,
                $patientAllergies,
                $patientAge,
                $patientSexe
            );
        }
        if (!empty($mistralMeta['error'])) {
            $mistralMeta['error'] = 'Complément Mistral indisponible';
        }
    }
    
    // Ajouter des informations contextuelles
    $response = [
        'success' => true,
        'data' => $suggestions,
        'ia_config' => $mistral->getPublicConfig(),
        'mistral' => $mistralMeta,
        'patient_info' => [
            'age' => $patientAge,
            'sexe' => $patientSexe,
            'antecedents' => $patientAntecedents,
            'allergies' => $patientAllergies,
            'personalized' => ($patientAge !== null || $patientSexe !== null || $patientAntecedents !== null || $patientAllergies !== null)
        ],
        'analysis_info' => [
            'symptomes_length' => strlen($symptomes),
            'symptomes_detectes' => $suggestions['diagnostic']['analysis']['symptomes_detectes'] ?? [],
            'confidence_score' => $suggestions['diagnostic']['analysis']['confidence_score'] ?? 0
        ],
        'safety_info' => [
            'has_warnings' => $suggestions['safety_checks']['has_warnings'] ?? false,
            'has_contraindications' => $suggestions['safety_checks']['has_contraindications'] ?? false,
            'has_interactions' => $suggestions['safety_checks']['has_interactions'] ?? false,
            'warnings_count' => count($suggestions['safety_checks']['warnings'] ?? [])
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    jsonResponse($response);
    
} catch (Exception $e) {
    error_log("Erreur API diagnostic: " . $e->getMessage());
    jsonResponse(['error' => 'Erreur interne du serveur'], 500);
}
?>
