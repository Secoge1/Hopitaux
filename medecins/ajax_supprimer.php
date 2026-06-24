<?php
/**
 * Endpoint AJAX pour suppression définitive d'un médecin
 * Retourne JSON - pas de redirection
 */
require_once __DIR__ . '/../includes/init.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$auth = Auth::getInstance();
if (!$auth->estConnecte()) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}
if (!$auth->estAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Action réservée à l\'administrateur']);
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

require_once __DIR__ . '/../models/Medecin.php';

$medecinModel = new Medecin();
// Accepter la suppression même si déjà marqué supprimé (getById exclut les supprimés)
$medecin = $medecinModel->getByIdIncludeDeleted($id);

if (!$medecin) {
    echo json_encode(['success' => false, 'message' => 'Médecin introuvable']);
    exit;
}

try {
    $deleteResult = $medecinModel->hardDelete($id);
    
    if ($deleteResult) {
        try {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
        } catch (Exception $e) { /* ignorer */ }
        $stats = $medecinModel->getStats();
        $specialitesCount = is_array($stats['specialites'] ?? null) ? count($stats['specialites']) : 0;
        echo json_encode([
            'success' => true,
            'message' => 'Médecin supprimé définitivement',
            'stats' => [
                'total' => (int)($stats['total'] ?? 0),
                'actif' => (int)($stats['actif'] ?? 0),
                'inactif' => (int)($stats['inactif'] ?? 0),
                'conge' => (int)($stats['conge'] ?? $stats['inactif'] ?? 0),
                'specialites' => $specialitesCount
            ]
        ]);
    } else {
        $detail = $medecinModel->getLastDeleteError();
        $msg = 'Erreur lors de la suppression';
        if ($detail !== '') {
            $msg .= ' : ' . $detail;
        }
        echo json_encode(['success' => false, 'message' => $msg]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur technique: ' . $e->getMessage()]);
}
