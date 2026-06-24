<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';
module_require_write('finances');
$auth = Auth::getInstance();

require_once __DIR__ . '/../models/Finances.php';

$financesModel = new Finances();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    header("Location: index.php?error=invalid_id");
    exit;
}

// Récupérer l'écriture pour vérification
try {
    $ecriture = $financesModel->getEcritureById($id);
    if (!$ecriture) {
        header("Location: index.php?error=ecriture_not_found");
        exit;
    }
    
    // Vérifier si l'écriture est déjà validée
    if ($ecriture['valide']) {
        header("Location: voir_ecriture.php?id=$id&error=already_validated");
        exit;
    }
    
    // Récupérer l'utilisateur connecté
    $utilisateur = $auth->getUtilisateur();
    $user_id = $utilisateur ? $utilisateur['id'] : null;
    
    if (!$user_id) {
        header("Location: voir_ecriture.php?id=$id&error=user_not_found");
        exit;
    }
    
    // Valider l'écriture
    if ($financesModel->validerEcriture($id, $user_id)) {
        header("Location: voir_ecriture.php?id=$id&success=validated");
        exit;
    } else {
        header("Location: voir_ecriture.php?id=$id&error=validation_failed");
        exit;
    }
    
} catch (Exception $e) {
    header("Location: voir_ecriture.php?id=$id&error=" . urlencode($e->getMessage()));
    exit;
}
?>
