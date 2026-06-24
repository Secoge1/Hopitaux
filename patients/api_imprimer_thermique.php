<?php
/**
 * API — envoi ticket ESC/POS vers imprimante réseau (Xprinter XP-80TS).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../includes/thermal_ticket_render.php';

header('Content-Type: application/json; charset=utf-8');

try {
    extract(app_module_context('patients'));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Méthode non autorisée.');
    }

    $consultationId = isset($_REQUEST['consultation_id']) ? (int) $_REQUEST['consultation_id'] : 0;
    if ($consultationId <= 0) {
        throw new RuntimeException('Consultation invalide.');
    }

    $consultationModel = new Consultation();
    $data = thermal_ticket_load_data($consultationModel, $consultationId);
    if (!$data) {
        throw new RuntimeException('Consultation introuvable.');
    }

    $result = thermal_ticket_print($data);
    echo json_encode([
        'success' => $result['ok'],
        'message' => $result['message'],
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
exit;
