<?php
/**
 * Endpoint AJAX pour suppression instantanée d'un patient
 * Retourne JSON - pas de redirection
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$auth = module_api_guard('patients');

require_once __DIR__ . '/../includes/patient_settings.php';
if (!patient_deletion_allowed()) {
    echo json_encode(['success' => false, 'message' => 'La suppression des patients est désactivée par l\'administrateur.']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

require_once __DIR__ . '/../models/Patient.php';

$patientModel = new Patient();
$patient = $patientModel->getById($id);

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient introuvable']);
    exit;
}

try {
    // Admin : suppression définitive | Médecin / infirmier : archivage (soft delete)
    if ($auth->estAdmin()) {
        $deleteResult = $patientModel->hardDelete($id);
        $successMessage = 'Patient supprimé définitivement';
    } else {
        $deleteResult = $patientModel->delete($id);
        $successMessage = 'Patient retiré de la liste (archivé)';
    }

    if ($deleteResult) {
        try {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
        } catch (Exception $e) { /* ignorer */ }
        $stats = $patientModel->getStats();
        echo json_encode([
            'success' => true,
            'message' => $successMessage,
            'stats' => [
                'total' => (int)($stats['total'] ?? 0),
                'actif' => (int)($stats['actif'] ?? 0),
                'nouveaux_mois' => (int)($stats['nouveaux_mois'] ?? 0),
                'consultations_moyenne' => (float)($stats['consultations_moyenne'] ?? 0)
            ]
        ]);
    } else {
        $detail = $patientModel->getLastDeleteError();
        $msg = 'Erreur lors de la suppression';
        if ($detail !== '') {
            $msg .= ' : ' . $detail;
        }
        echo json_encode(['success' => false, 'message' => $msg]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur technique: ' . $e->getMessage()]);
}
