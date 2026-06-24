<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
require_once __DIR__ . '/../includes/consultation_ticket_render.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../includes/staff_scope.php';

extract(app_module_context('patients'));

$consultationId = isset($_GET['consultation_id']) ? (int) $_GET['consultation_id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);
$printMode = isset($_GET['print']);

if (!$consultationId) {
    http_response_code(400);
    die('ID de consultation manquant.');
}

$consultationModel = new Consultation();
$consultation = $consultationModel->getByIdForPatientModule($consultationId);

if (!$consultation) {
    http_response_code(404);
    die('Consultation non trouvée ou accès refusé.');
}

$data = consultation_ticket_load_data($consultationModel, $consultationId);
if (!$data) {
    http_response_code(404);
    die('Consultation non trouvée.');
}

try {
    $consultationModel->saveTicket($consultationId);
} catch (Exception $e) {
    // Affichage du ticket même si la sauvegarde BDD échoue
}

echo consultation_ticket_render_page($data, $printMode, !$printMode);
exit;
