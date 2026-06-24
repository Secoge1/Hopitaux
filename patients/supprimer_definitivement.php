<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';

Auth::getInstance()->requireUnRole(['admin']);

require_once __DIR__ . '/../includes/patient_settings.php';
if (!patient_deletion_allowed()) {
    $_SESSION['flash_message'] = 'La suppression des patients est désactivée par l\'administrateur.';
    header('Location: index.php?statut=supprime');
    exit();
}

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../config/db.php';

require_once __DIR__ . '/../includes/saas/TenantScope.php';

$patientModel = new Patient();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    $_SESSION['flash_message'] = "ID patient invalide.";
    header("Location: index.php?statut=supprime");
    exit();
}

$pdo = getDB();
$stmt = $pdo->prepare(
    "SELECT id, nom, prenom, statut FROM patients WHERE id = ? AND statut = 'supprime'"
    . TenantScope::andOwnedByTenant($pdo, 'patients')
);
$stmt->execute(TenantScope::paramsForId($pdo, 'patients', $id));
$patient = $stmt->fetch();

if (!$patient) {
    $message = "Patient introuvable.";
} elseif (($patient['statut'] ?? '') !== 'supprime') {
    $message = "Seuls les patients déjà supprimés (marqués « supprimé ») peuvent être supprimés définitivement.";
} else {
    if ($patientModel->hardDelete($id)) {
        try {
            require_once __DIR__ . '/../includes/CacheSystem.php';
            CacheSystem::getInstance()->invalidateDashboardCache();
        } catch (Exception $e) { /* ignorer */ }
        $message = "Patient et toutes ses données associées ont été supprimés définitivement de la base. Ils ne réapparaîtront plus nulle part.";
    } else {
        $detail = $patientModel->getLastDeleteError();
        $message = 'Erreur lors de la suppression définitive' . ($detail !== '' ? ' : ' . $detail : ' (données liées ou contraintes).');
    }
}

$_SESSION['flash_message'] = $message;
header("Location: index.php?statut=supprime");
exit();
