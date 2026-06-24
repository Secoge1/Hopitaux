<?php
/**
 * Suppression de documents patients
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/saas/TenantScope.php';
$auth = Auth::getInstance();
module_require_roles('patients');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_id'])) {
    $document_id = (int)$_POST['document_id'];

    $db = getDB();
    
    $where = ['id = ?'];
    $params = [$document_id];
    TenantScope::appendWhere($db, 'documents_patients', $where, $params);
    $stmt = $db->prepare('SELECT * FROM documents_patients WHERE ' . implode(' AND ', $where));
    $stmt->execute($params);
    $document = $stmt->fetch();
    
    if ($document) {
        // Supprimer le fichier physique
        $filePath = __DIR__ . '/../uploads/patients/' . $document['patient_id'] . '/' . basename($document['nom_fichier']);
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Supprimer de la base de données
        $stmt = $db->prepare('DELETE FROM documents_patients WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        
        // Rediriger avec un message de succès
        header("Location: gestion_documents.php?patient_id={$document['patient_id']}&categorie={$document['categorie']}&success=Document supprimé avec succès");
        exit();
    }
}

// Si on arrive ici, c'est une erreur
header("Location: index.php?error=Document non trouvé");
exit();
?>

