<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';

module_require_roles('patients');

require_once __DIR__ . '/../includes/patient_settings.php';
if (!patient_deletion_allowed()) {
    $_SESSION['flash_message'] = 'La suppression des patients est désactivée par l\'administrateur.';
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../config/db.php';

// Fonction pour logger les erreurs
function logError($message, $context = []) {
    $log_message = date('Y-m-d H:i:s') . " - " . $message;
    if (!empty($context)) {
        $log_message .= " - Context: " . json_encode($context);
    }
    error_log($log_message);
}

$patientModel = new Patient();

// Récupérer l'ID du patient
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id) {
    try {
        // Vérifier d'abord que le patient existe
        $patient = $patientModel->getById($id);
        
        if (!$patient) {
            logError("Tentative de suppression d'un patient inexistant", ['patient_id' => $id, 'user_id' => $_SESSION['user_id']]);
            $message = "Patient introuvable.";
        } else {
            logError("Début de suppression du patient", [
                'patient_id' => $id, 
                'patient_name' => $patient['nom'] . ' ' . $patient['prenom'],
                'user_id' => $_SESSION['user_id'],
                'environment' => 'web'
            ]);
            
            // Supprimer le patient
            $deleteResult = $patientModel->delete($id);
            
            if ($deleteResult) {
                // Invalider le cache du dashboard pour que le compteur patients soit à jour
                try {
                    require_once __DIR__ . '/../includes/CacheSystem.php';
                    CacheSystem::getInstance()->invalidateDashboardCache();
                } catch (Exception $e) { /* ignorer */ }
                logError("Suppression du patient réussie", [
                    'patient_id' => $id,
                    'patient_name' => $patient['nom'] . ' ' . $patient['prenom'],
                    'user_id' => $_SESSION['user_id']
                ]);
                $message = "Patient supprimé avec succès !";
            } else {
                logError("Échec de la suppression du patient", [
                    'patient_id' => $id,
                    'patient_name' => $patient['nom'] . ' ' . $patient['prenom'],
                    'user_id' => $_SESSION['user_id']
                ]);
                $message = "Erreur lors de la suppression du patient.";
            }
        }
        
    } catch (Exception $e) {
        logError("Exception lors de la suppression du patient", [
            'patient_id' => $id,
            'user_id' => $_SESSION['user_id'],
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        $message = "Erreur technique lors de la suppression du patient.";
    }
} else {
    logError("Tentative de suppression sans ID de patient", ['user_id' => $_SESSION['user_id']]);
    $message = "ID du patient non valide.";
}

$_SESSION['flash_message'] = $message;
header("Location: index.php");
exit();
?>

