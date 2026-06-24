<?php
/**
 * Gestion des actions AJAX pour la maintenance
 * Lancer la maintenance
 */

// Inclure les systèmes avancés
require_once 'init.php';

// Vérifier que l'utilisateur est connecté
$auth = Auth::getInstance();
if (!$auth->estConnecte()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier que l'utilisateur est admin
if (!$auth->estAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé - Droits administrateur requis']);
    exit;
}

// Récupérer l'action demandée
$action = $_REQUEST['action'] ?? '';

// Initialiser le système de maintenance
$maintenanceSystem = MaintenanceSystem::getInstance();

// Traiter l'action demandée
switch ($action) {
    case 'run_maintenance':
        try {
            $success = $maintenanceSystem->runFullMaintenance();
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la maintenance: ' . $e->getMessage()]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}
?>


