<?php
/**
 * Test impression thermique — admin uniquement.
 */
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/app_parametres_layout.php';
require_once __DIR__ . '/../includes/EscPosPrinter.php';
require_once __DIR__ . '/../includes/thermal_printer_config.php';
require_once __DIR__ . '/../config/SystemParameters.php';

app_parametres_require_admin();

header('Content-Type: application/json; charset=utf-8');

try {
    $settings = thermal_printer_settings();
    if (!$settings['actif'] || $settings['ip'] === '') {
        throw new RuntimeException('Configurez l\'adresse IP de l\'imprimante dans Paramètres.');
    }

    $sys = SystemParameters::getInstance();
    $nom = $sys->get('nom_etablissement', 'Se.Santé');
    $width = thermal_printer_line_width((int) $settings['largeur_mm']);
    $p = new EscPosPrinter($width);
    $p->init()
        ->align('center')->bold(true)->size(2, 2)->text($nom)->normal()
        ->feed(1)
        ->align('center')->text('TEST IMPRIMANTE THERMIQUE')
        ->text($settings['modele'])
        ->text('IP: ' . $settings['ip'] . ':' . $settings['port'])
        ->feed(1)
        ->align('center')->text(date('d/m/Y H:i:s'))
        ->text('ESC/POS OK')
        ->cut();

    $result = EscPosPrinter::sendToNetwork($settings['ip'], $settings['port'], $p->getBuffer());
    if (!$result['ok']) {
        throw new RuntimeException($result['error'] ?? 'Échec envoi');
    }

    echo json_encode(['success' => true, 'message' => 'Page de test envoyée à l\'imprimante.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit;
