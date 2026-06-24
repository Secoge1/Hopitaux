<?php
/**
 * Diffusion inline des documents patients (aperçu PDF / image dans iframe).
 */

require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/module_guard.php';

$auth = Auth::getInstance();
module_require_roles('patients');

require_once __DIR__ . '/../models/Patient.php';

$patient_id = isset($_GET['patient_id']) ? (int) $_GET['patient_id'] : 0;
$filename = isset($_GET['file']) ? (string) $_GET['file'] : '';

if (!$patient_id || $filename === '') {
    http_response_code(400);
    die('Paramètres manquants');
}

$patientModel = new Patient();
if (!$patientModel->getById($patient_id)) {
    http_response_code(403);
    die('Accès interdit');
}

$filename = basename($filename);
$filepath = __DIR__ . '/../uploads/patients/' . $patient_id . '/' . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    die('Fichier non trouvé');
}

$realpath = realpath($filepath);
$expected_dir = realpath(__DIR__ . '/../uploads/patients/' . $patient_id);
if ($realpath === false || $expected_dir === false || strpos($realpath, $expected_dir) !== 0) {
    http_response_code(403);
    die('Accès interdit');
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $filepath);
finfo_close($finfo);

$allowed_inline_mimes = [
    'application/pdf',
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'text/plain',
];

if (!in_array($mime_type, $allowed_inline_mimes, true)) {
    http_response_code(415);
    die('Type de fichier non supporté pour la visualisation');
}

$display_name = $filename;
$infoFile = __DIR__ . '/../uploads/patients/' . $patient_id . '/' . $filename . '.info';
if (file_exists($infoFile)) {
    $metadata = json_decode((string) file_get_contents($infoFile), true);
    if (is_array($metadata) && !empty($metadata['original_name'])) {
        $display_name = (string) $metadata['original_name'];
    }
}

// Autoriser l’intégration dans view_document.php (même origine).
header_remove('X-Frame-Options');
header('X-Frame-Options: SAMEORIGIN');
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . str_replace('"', '', $display_name) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');

readfile($filepath);
exit;
