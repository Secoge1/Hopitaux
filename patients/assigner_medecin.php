<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
extract(app_module_context('patients'));

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../includes/staff_scope.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!StaffScope::canAssignPatientMedecin()) {
    $_SESSION['flash_message'] = 'Vous n\'êtes pas autorisé à assigner un médecin référent.';
    $_SESSION['flash_type'] = 'danger';
    header('Location: index.php');
    exit;
}

$patientId = isset($_POST['patient_id']) ? (int) $_POST['patient_id'] : 0;
$medecinId = isset($_POST['medecin_referent_id']) ? (int) $_POST['medecin_referent_id'] : 0;
$redirect = trim((string) ($_POST['redirect'] ?? ''));
if ($redirect === '' || strpos($redirect, '..') !== false) {
    $redirect = 'voir.php?id=' . $patientId;
}

if (!$patientId) {
    header('Location: index.php');
    exit;
}

$patientModel = new Patient();
$ok = $patientModel->assignMedecinReferent($patientId, $medecinId > 0 ? $medecinId : null);

if ($ok) {
    $_SESSION['flash_message'] = $medecinId > 0
        ? 'Médecin référent assigné avec succès.'
        : 'Médecin référent retiré.';
    $_SESSION['flash_type'] = 'success';
} else {
    $_SESSION['flash_message'] = 'Impossible d\'assigner le médecin référent.';
    $_SESSION['flash_type'] = 'danger';
}

header('Location: ' . $redirect);
exit;
