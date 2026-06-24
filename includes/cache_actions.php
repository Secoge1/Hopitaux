<?php
/**
 * Gestion des actions AJAX pour le cache
 * Vider le cache, actualiser le cache, récupérer les stats
 */

header('Content-Type: application/json');

// Inclure les systèmes avancés
require_once __DIR__ . '/init.php';

// Vérifier que l'utilisateur est connecté
$auth = Auth::getInstance();
if (!$auth->estConnecte()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// S'assurer que CacheSystem est chargé (indépendamment d'AdvancedConfig)
require_once __DIR__ . '/CacheSystem.php';

// Récupérer l'action demandée
$action = $_REQUEST['action'] ?? '';

// Initialiser le système de cache
$cacheSystem = CacheSystem::getInstance();

// Traiter l'action demandée
switch ($action) {
    case 'clear_cache':
        $success = $cacheSystem->clear();
        echo json_encode(['success' => $success]);
        break;

    case 'refresh_cache':
        // Invalider uniquement le cache dashboard (pas clear() global)
        $cacheSystem->invalidateDashboardCache();
        $dashboardStats = getDashboardStats();
        echo json_encode(['success' => true]);
        break;

    case 'get_stats':
        // Retourner les statistiques fraîches au format JSON (pour mise à jour AJAX sans rechargement)
        $stats = getDashboardStats();
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
        break;
}
?>


