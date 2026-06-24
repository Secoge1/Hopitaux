<?php
/**
 * API analyse de risque patient — authentifiée, sans CORS ouvert.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/PatientRiskIntelligence.php';
require_once __DIR__ . '/../models/Patient.php';

module_api_guard('patients');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    module_api_json(['error' => 'Méthode non autorisée'], 405);
}

try {
    $action = $_GET['action'] ?? $_POST['action'] ?? '';
    $patientId = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : (isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0);
    $timeframe = (int) ($_GET['timeframe'] ?? $_POST['timeframe'] ?? 12);

    if (!$patientId && in_array($action, ['analyze_risk', 'predict_future', 'follow_up_plan'], true)) {
        module_api_json(['error' => 'ID patient requis'], 400);
    }

    if ($patientId) {
        $patientModel = new Patient();
        $patient = $patientModel->getById($patientId);
        if (!$patient) {
            module_api_json(['error' => 'Patient introuvable'], 404);
        }
    }

    switch ($action) {
        case 'analyze_risk':
            module_api_json(PatientRiskIntelligence::analyzePatientRisk($patientId));

        case 'predict_future':
            module_api_json(PatientRiskIntelligence::predictFutureRisks($patientId, $timeframe));

        case 'follow_up_plan':
            module_api_json(PatientRiskIntelligence::recommendFollowUpPlan($patientId));

        default:
            module_api_json(['error' => 'Action non reconnue'], 400);
    }
} catch (Exception $e) {
    error_log('Erreur API analyse risque patient: ' . $e->getMessage());
    module_api_json(['error' => 'Erreur interne du serveur'], 500);
}
