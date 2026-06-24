<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
require_once __DIR__ . '/../models/Analyse.php';
require_once __DIR__ . '/../includes/thermal_lab_ticket_render.php';

extract(app_module_context('patients'));

$analyseId = isset($_GET['analyse_id']) ? (int) $_GET['analyse_id'] : 0;
$autoPrint = isset($_GET['print']);
$returnUrl = isset($_GET['return']) ? (string) $_GET['return'] : '';

if (!$analyseId) {
    http_response_code(400);
    die('ID d\'analyse manquant.');
}

$analyseModel = new Analyse();
$data = thermal_lab_ticket_load_data($analyseModel, $analyseId);

if (!$data) {
    http_response_code(404);
    die('Analyse non trouvée ou accès refusé.');
}

$networkPrinted = false;
if ($autoPrint && thermal_printer_is_configured()) {
    if (!isset($_SESSION['thermal_print_done']) || !is_array($_SESSION['thermal_print_done'])) {
        $_SESSION['thermal_print_done'] = [];
    }
    $printKey = 'analyse:' . $analyseId;
    if (empty($_SESSION['thermal_print_done'][$printKey])) {
        $printResult = thermal_lab_ticket_print($data);
        $networkPrinted = $printResult['ok'];
        if ($networkPrinted) {
            $_SESSION['thermal_print_done'][$printKey] = time();
        }
    } else {
        $networkPrinted = true;
    }
}

echo thermal_lab_ticket_render_html(
    $data,
    $autoPrint && !$networkPrinted,
    $returnUrl
);
exit;
