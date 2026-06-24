<?php
/**
 * Fichier de traitement des actions sur les consultations
 * Commencer, terminer, annuler, etc.
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../models/Consultation.php';

$auth = Auth::getInstance();
module_require_roles('consultations');

// Récupérer les informations de l'utilisateur connecté
$utilisateur = $auth->getUtilisateur();

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?error=invalid_method');
    exit;
}

// Vérifier l'action demandée
$action = $_POST['action'] ?? '';
$consultation_id = (int)($_POST['consultation_id'] ?? 0);

if (!$consultation_id) {
    header('Location: index.php?error=invalid_id');
    exit;
}

// Initialiser le modèle
$consultationModel = new Consultation();

// Récupérer la consultation pour vérification
$consultation = $consultationModel->getById($consultation_id);
if (!$consultation) {
    header('Location: index.php?error=consultation_not_found');
    exit;
}

$success = false;
$message = '';
$redirect = '';

try {
    switch ($action) {
        case 'commencer':
            if ($consultation['statut'] === 'planifie') {
                $success = $consultationModel->commencer($consultation_id);
                if ($success) {
                    $message = 'Consultation commencée avec succès !';
                    $redirect = "voir.php?id=$consultation_id&success=started";
                } else {
                    $message = 'Erreur lors du démarrage de la consultation.';
                    $redirect = "voir.php?id=$consultation_id&error=start_failed";
                }
            } else {
                $message = 'Cette consultation ne peut pas être commencée (statut: ' . $consultation['statut'] . ').';
                $redirect = "voir.php?id=$consultation_id&error=invalid_status";
            }
            break;
            
        case 'terminer':
            if ($consultation['statut'] === 'en_cours') {
                $success = $consultationModel->terminer($consultation_id);
                if ($success) {
                    $message = 'Consultation terminée avec succès !';
                    $redirect = "voir.php?id=$consultation_id&success=completed";
                } else {
                    $message = 'Erreur lors de la finalisation de la consultation.';
                    $redirect = "voir.php?id=$consultation_id&error=completion_failed";
                }
            } else {
                $message = 'Cette consultation ne peut pas être terminée (statut: ' . $consultation['statut'] . ').';
                $redirect = "voir.php?id=$consultation_id&error=invalid_status";
            }
            break;
            
        case 'annuler':
            if (in_array($consultation['statut'], ['planifie', 'en_cours'])) {
                $success = $consultationModel->annuler($consultation_id);
                if ($success) {
                    $message = 'Consultation annulée avec succès !';
                    $redirect = "voir.php?id=$consultation_id&success=cancelled";
                } else {
                    $message = 'Erreur lors de l\'annulation de la consultation.';
                    $redirect = "voir.php?id=$consultation_id&error=cancellation_failed";
                }
            } else {
                $message = 'Cette consultation ne peut pas être annulée (statut: ' . $consultation['statut'] . ').';
                $redirect = "voir.php?id=$consultation_id&error=invalid_status";
            }
            break;
            
        default:
            $message = 'Action non reconnue.';
            $redirect = "voir.php?id=$consultation_id&error=unknown_action";
            break;
    }
    
} catch (Exception $e) {
    $message = 'Erreur système : ' . $e->getMessage();
    $redirect = "voir.php?id=$consultation_id&error=system_error";
}

// Rediriger avec le message approprié
header("Location: $redirect");
exit;
?>
