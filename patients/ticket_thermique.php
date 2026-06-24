<?php
/**
 * Ticket thermique 80 mm — accueil → caisse (Xprinter ESC/POS).
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_module_layout.php';
require_once __DIR__ . '/../models/Consultation.php';
require_once __DIR__ . '/../includes/thermal_ticket_render.php';

extract(app_module_context('patients'));

$consultationId = isset($_GET['consultation_id']) ? (int) $_GET['consultation_id'] : 0;
$autoPrint = isset($_GET['print']);
$returnUrl = isset($_GET['return']) ? (string) $_GET['return'] : '';

if (!$consultationId) {
    http_response_code(400);
    die('ID de consultation manquant.');
}

$consultationModel = new Consultation();
$data = thermal_ticket_load_data($consultationModel, $consultationId);

if (!$data) {
    http_response_code(404);
    die('Consultation non trouvée ou accès refusé.');
}

$networkPrinted = false;
if ($autoPrint && thermal_printer_is_configured()) {
    if (!isset($_SESSION['thermal_print_done']) || !is_array($_SESSION['thermal_print_done'])) {
        $_SESSION['thermal_print_done'] = [];
    }
    $printKey = 'consultation:' . $consultationId;
    if (empty($_SESSION['thermal_print_done'][$printKey])) {
        $printResult = thermal_ticket_print($data);
        $networkPrinted = $printResult['ok'];
        if ($networkPrinted) {
            $_SESSION['thermal_print_done'][$printKey] = time();
        }
    } else {
        $networkPrinted = true;
    }
}

// Impression navigateur uniquement si l'impression réseau n'a pas réussi (évite le double tirage)
echo thermal_ticket_render_html(
    $data,
    $autoPrint && !$networkPrinted,
    $returnUrl
);
exit;
