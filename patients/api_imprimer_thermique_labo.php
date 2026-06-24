<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../includes/thermal_lab_ticket_render.php';

header('Content-Type: application/json; charset=utf-8');

try {
    extract(app_module_context('patients'));

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Méthode non autorisée.');
    }

    $analyseId = isset($_REQUEST['analyse_id']) ? (int) $_REQUEST['analyse_id'] : 0;
    if ($analyseId <= 0) {
        throw new RuntimeException('Analyse invalide.');
    }

    $analyseModel = new Analyse();
    $data = thermal_lab_ticket_load_data($analyseModel, $analyseId);
    if (!$data) {
        throw new RuntimeException('Analyse introuvable.');
    }

    $result = thermal_lab_ticket_print($data);
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
