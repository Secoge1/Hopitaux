<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';

Auth::getInstance()->requireUnRole(['admin']);

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/saas/TenantScope.php';

$patientModel = new Patient();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    $_SESSION['flash_message'] = "ID patient invalide.";
    header("Location: index.php");
    exit();
}

$message = "Patient introuvable ou non supprimé.";
$patient = $patientModel->getById($id);

if (!$patient) {
    $pdo = getDB();
    $stmt = $pdo->prepare(
        "SELECT id, nom, prenom, statut FROM patients WHERE id = ? AND statut = 'supprime'"
        . TenantScope::andOwnedByTenant($pdo, 'patients')
    );
    $stmt->execute(TenantScope::paramsForId($pdo, 'patients', $id));
    $patient = $stmt->fetch();
}

if ($patient && ($patient['statut'] ?? '') === 'supprime') {
    if ($patientModel->restore($id)) {
        try {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
        } catch (Exception $e) { /* ignorer */ }
        $message = "Patient restauré avec succès. Il réapparaît dans la liste des patients actifs.";
    } else {
        $message = "Erreur lors de la restauration du patient.";
    }
}

$_SESSION['flash_message'] = $message;
header("Location: index.php");
exit();
