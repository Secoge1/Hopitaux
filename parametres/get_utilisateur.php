<?php
/**
 * API pour récupérer les données d'un utilisateur
 * Utilisé par la fonctionnalité de visualisation
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Utilisateur.php';
require_once __DIR__ . '/../includes/staff_link.php';

header('Content-Type: application/json');

$auth = Auth::getInstance();
if (!$auth->estConnecte() || !$auth->estAdmin()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès refusé']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $utilisateurModel = new Utilisateur($db);
    
    // Vérifier que l'ID est fourni
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        echo json_encode([
            'success' => false,
            'message' => 'ID utilisateur manquant'
        ]);
        exit;
    }
    
    $id = (int)$_GET['id'];
    
    // Récupérer les données de l'utilisateur
    $utilisateur = $utilisateurModel->getById($id);
    
    if (!$utilisateur) {
        echo json_encode([
            'success' => false,
            'message' => 'Utilisateur non trouvé'
        ]);
        exit;
    }
    
    // Récupérer des statistiques supplémentaires pour cet utilisateur
    $stats = [];
    
    // Nombre de connexions (si la table existe)
    try {
        require_once __DIR__ . '/../includes/saas/TenantScope.php';
        $where = ['utilisateur_id = ?'];
        $params = [$id];
        TenantScope::appendWhere($db, 'connexions', $where, $params);
        $stmt = $db->prepare('SELECT COUNT(*) as total FROM connexions WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        $stats['connexions'] = $stmt->fetch()['total'];
    } catch (Exception $e) {
        $stats['connexions'] = 0;
    }
    
    // Dernière connexion (si la table existe)
    try {
        $where = ['utilisateur_id = ?'];
        $params = [$id];
        TenantScope::appendWhere($db, 'connexions', $where, $params);
        $stmt = $db->prepare('SELECT date_connexion FROM connexions WHERE ' . implode(' AND ', $where) . ' ORDER BY date_connexion DESC LIMIT 1');
        $stmt->execute($params);
        $derniereConnexion = $stmt->fetch();
        $stats['derniere_connexion'] = $derniereConnexion ? $derniereConnexion['date_connexion'] : null;
    } catch (Exception $e) {
        $stats['derniere_connexion'] = null;
    }
    
    $utilisateur['stats'] = $stats;
    $utilisateur['staff_link'] = StaffLink::getLinkForUser($id);
    
    // Retourner les données
    echo json_encode([
        'success' => true,
        'utilisateur' => $utilisateur
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>

