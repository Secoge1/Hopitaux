<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_layout.php';
require_once __DIR__ . '/../includes/module_guard.php';
require_once __DIR__ . '/../includes/consultation_ticket_render.php';
require_once __DIR__ . '/../models/Consultation.php';

$auth = Auth::getInstance();
$auth->requireAuth();
module_require_roles('consultations');

$consultation_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$print_mode = isset($_GET['print']);

if (!$consultation_id) {
    http_response_code(400);
    die('ID de consultation manquant.');
}

$consultationModel = new Consultation();
$data = consultation_ticket_load_data($consultationModel, $consultation_id);

if (!$data) {
    http_response_code(404);
    die('Consultation non trouvée.');
}

try {
    $consultationModel->saveTicket($consultation_id);
} catch (Exception $e) {
    // Affichage du ticket même si la sauvegarde BDD échoue
}

echo consultation_ticket_render_page($data, $print_mode, !$print_mode);
exit;
