<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';

require_once __DIR__ . '/../models/Maintenance.php';

module_require_roles('maintenance');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
$equipement_id = isset($_POST['equipement_id']) ? (int) $_POST['equipement_id'] : 0;
$action = $_POST['action'] ?? '';

$maintenanceModel = new Maintenance();
$intervention = $id ? $maintenanceModel->getInterventionById($id) : null;

if (!$intervention || ($equipement_id && (int) $intervention['equipement_id'] !== $equipement_id)) {
    $_SESSION['flash_message'] = 'Intervention introuvable.';
    header('Location: ' . ($equipement_id ? "intervention.php?equipement_id=$equipement_id" : 'index.php'));
    exit;
}

$equipement_id = (int) $intervention['equipement_id'];
$redirect = "intervention.php?equipement_id=$equipement_id";

switch ($action) {
    case 'terminer':
        if ($maintenanceModel->setInterventionStatut($id, 'terminee')) {
            $_SESSION['flash_message'] = 'Intervention marquée comme terminée.';
        } else {
            $_SESSION['flash_message'] = 'Impossible de mettre à jour le statut.';
        }
        break;

    case 'en_cours':
        if ($maintenanceModel->setInterventionStatut($id, 'en_cours')) {
            $_SESSION['flash_message'] = 'Intervention passée en cours.';
        } else {
            $_SESSION['flash_message'] = 'Impossible de mettre à jour le statut.';
        }
        break;

    default:
        $_SESSION['flash_message'] = 'Action non reconnue.';
}

header("Location: $redirect");
exit;
